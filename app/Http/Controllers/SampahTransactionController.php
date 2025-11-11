<?php

namespace App\Http\Controllers;

use App\Models\SampahTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SampahTransactionController extends Controller
{

    // F. Sampah (SampahController) - GET /api/sampah/laporan
    public function index(Request $request)
    {
        // 🔹 Ambil parameter filter dan paginasi
        $perPage = $request->input('per_page', 10); // Default 10 item per halaman
        $query = SampahTransaction::with('admin');

        // 🔹 Filter berdasarkan 'tanggal'
        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        }

        // 🔹 Filter berdasarkan 'tipe' (pemasukan/pengeluaran)
        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe);
        }

        // 🔹 Filter berdasarkan 'year' (tahun)
        if ($request->filled('year')) {
            $query->whereYear('tanggal', $request->year);
        }

        // 🔹 Filter berdasarkan 'q' (pencarian di kolom title)
        if ($request->filled('q')) {
            $searchTerm = '%' . $request->q . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', $searchTerm);
            });
        }

        // 🔹 Ambil data terurut dan dipaginasi
        $transactions = $query->orderBy('tanggal', 'asc')->paginate($perPage);

        // 🔹 Hitung saldo akhir total (tanpa pagination)
        $saldo = SampahTransaction::sum(DB::raw("CASE WHEN tipe = 'pemasukan' THEN jumlah ELSE -jumlah END"));

        return response()->json([
            'message' => 'Data transaksi sampah berhasil diambil',
            'saldo_akhir_total' => $saldo,
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
            ],
            'filters' => [
                'tanggal' => $request->tanggal ?? null,
                'year' => $request->year ?? null,
                'tipe' => $request->tipe ?? null,
                'q' => $request->q ?? null,
            ],
            'data' => $transactions->items(),
        ]);
    }


    // Tambah transaksi baru
    public function store(Request $request)
    {
        $request->validate([
            'tipe' => 'required|in:pemasukan,pengeluaran',
            'jumlah' => 'required|numeric|min:0',
            'keterangan' => 'nullable|string',
            'tanggal' => 'required|date',
        ]);

        $transaksi = SampahTransaction::create([
            'admin_id' => $request->user()->id,
            'tipe' => $request->tipe,
            'jumlah' => $request->jumlah,
            'keterangan' => $request->keterangan,
            'tanggal' => $request->tanggal,
        ]);

        return response()->json([
            'message' => 'Transaksi sampah berhasil ditambahkan',
            'data' => $transaksi
        ], 201);
    }

    // Edit transaksi
    public function update(Request $request, $id)
    {
        $transaksi = SampahTransaction::findOrFail($id);

        $request->validate([
            'tipe' => 'sometimes|in:pemasukan,pengeluaran',
            'jumlah' => 'sometimes|numeric|min:0',
            'keterangan' => 'nullable|string',
            'tanggal' => 'sometimes|date',
        ]);

        $transaksi->update($request->only(['tipe', 'jumlah', 'keterangan', 'tanggal']));

        return response()->json([
            'message' => 'Transaksi sampah berhasil diperbarui',
            'data' => $transaksi
        ]);
    }

    // Hapus transaksi
    public function destroy($id)
    {
        $transaksi = SampahTransaction::findOrFail($id);
        $transaksi->delete();

        return response()->json(['message' => 'Transaksi sampah berhasil dihapus']);
    }

    public function export(Request $request)
    {
        // Ambil SEMUA data (tanpa paginasi, tapi dengan filter jika ada)
        $query = SampahTransaction::with('admin');

        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        }
        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe);
        }
        if ($request->filled('year')) {
            $query->whereYear('tanggal', $request->year);
        }
        // Filter lain jika diperlukan

        $transaksi = $query->orderBy('tanggal', 'asc')->get();

        // Tentukan nama file CSV
        $fileName = 'laporan_sampah_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        // Fungsi untuk membuat stream CSV
        $callback = function () use ($transaksi) {
            $file = fopen('php://output', 'w');

            // Header Kolom CSV
            fputcsv($file, ['ID', 'Admin', 'Tipe', 'Jumlah', 'Keterangan', 'Tanggal', 'Dibuat Pada']);

            // Data Saldo Akhir (opsional)
            $saldo = 0;

            // Isi Data Transaksi
            foreach ($transaksi as $trx) {
                $saldo += $trx->tipe === 'pemasukan' ? $trx->jumlah : -$trx->jumlah;

                fputcsv($file, [
                    $trx->id,
                    $trx->admin->name ?? 'N/A', // Asumsi relasi admin ada
                    $trx->tipe,
                    $trx->jumlah,
                    $trx->keterangan,
                    $trx->tanggal,
                    $trx->created_at,
                    // Kolom saldo akhir per baris TIDAK disertakan karena akan salah
                    // jika file dibuka di Excel dan diurutkan. Lebih baik hitung di Excel/frontend.
                ]);
            }

            // Baris Saldo Akhir Total
            fputcsv($file, ['', '', 'SALDO AKHIR TOTAL', $saldo, '', '', '']);

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
