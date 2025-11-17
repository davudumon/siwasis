<?php

namespace App\Http\Controllers;

use App\Models\JimpitanTransaction;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JimpitanTransactionController extends Controller
{
    /**
     * Mengambil daftar transaksi jimpitan dengan filter, pencarian, dan paginasi.
     * Endpoint: GET /api/jimpitan/laporan
     * Filter: tipe, tanggal, year, q, page
     */
    public function index(Request $request)
    {
        // 🔹 Ambil parameter paginasi
        $perPage = $request->input('per_page', 10);

        // 🔹 Query dasar
        $query = JimpitanTransaction::with('admin')->orderBy('tanggal', 'desc');

        // 🔹 Filter berdasarkan 'tipe'
        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe);
        }

        // 🔹 Filter berdasarkan 'tanggal'
        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        }

        // 🔹 Filter berdasarkan 'year'
        if ($request->filled('year')) {
            $query->whereYear('tanggal', $request->year);
        }

        // 🔹 Filter pencarian (keterangan)
        if ($request->filled('q')) {
            $query->where('keterangan', 'like', '%' . $request->q . '%');
        }

        // ======================================================
        // 🔹 Ambil data paginated (DESC untuk tampilan)
        // ======================================================
        $transactions = $query->paginate($perPage);

        // ======================================================
        // 🔹 Hitung saldo sementara (HANYA dalam halaman)
        // ======================================================
        $items = collect($transactions->items());

        // Urut ASC untuk menghitung saldo berurutan
        $sortedAsc = $items->sortBy('tanggal');

        $saldo = 0;

        foreach ($sortedAsc as $item) {
            if ($item->tipe === 'masuk' || $item->tipe === 'pemasukan') {
                $saldo += $item->jumlah;
            } else {
                $saldo -= $item->jumlah;
            }

            $item->saldo_sementara = $saldo;
        }

        // Kembalikan ke DESC (seperti tampilan normal)
        $finalItems = $sortedAsc->sortByDesc('tanggal')->values();

        // Total saldo global
        $totalSaldo = $this->calculateTotalBalance() ?? 0;

        // ======================================================
        // 🔹 Return response
        // ======================================================
        return response()->json([
            'message' => 'Data transaksi jimpitan berhasil diambil',
            'saldo_akhir_total' => $totalSaldo,

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

            'data' => $finalItems,
        ]);
    }



    /**
     * Menghitung saldo akhir total dari semua transaksi.
     */
    private function calculateTotalBalance()
    {
        $allTransactions = JimpitanTransaction::all();
        $saldo = 0;
        foreach ($allTransactions as $trx) {
            $saldo += $trx->tipe === 'pemasukan' ? $trx->jumlah : -$trx->jumlah;
        }
        return $saldo;
    }

    /**
     * Membuat transaksi baru.
     * Endpoint: POST /api/jimpitan/create
     */
    public function store(Request $request)
    {
        $request->validate([
            'jumlah' => 'required|numeric|min:0',
            'tipe' => 'required|in:pemasukan,pengeluaran',
            'keterangan' => 'required|string',
            'tanggal' => 'required|date',
        ]);

        $data = JimpitanTransaction::create([
            'admin_id' => $request->user()->id,
            'jumlah' => $request->jumlah,
            'tipe' => $request->tipe,
            'keterangan' => $request->keterangan,
            'tanggal' => $request->tanggal,
        ]);

        return response()->json([
            'message' => 'Transaksi jimpitan berhasil ditambahkan',
            'data' => $data
        ], 201);
    }

    /**
     * Menampilkan satu transaksi berdasarkan ID.
     * Endpoint: GET /api/jimpitan/{id}
     */
    public function show($id)
    {
        $data = JimpitanTransaction::with('admin')->findOrFail($id);
        return response()->json($data);
    }

    /**
     * Memperbarui transaksi berdasarkan ID.
     * Endpoint: PUT /api/jimpitan/update/{id}
     */
    public function update(Request $request, $id)
    {
        $data = JimpitanTransaction::findOrFail($id);

        $request->validate([
            'jumlah' => 'sometimes|numeric|min:0',
            'tipe' => 'sometimes|in:pemasukan,pengeluaran',
            'keterangan' => 'sometimes|string',
            'tanggal' => 'sometimes|date',
        ]);

        $data->update([
            'jumlah' => $request->jumlah ?? $data->jumlah,
            'tipe' => $request->tipe ?? $data->tipe,
            'keterangan' => $request->keterangan ?? $data->keterangan,
            'tanggal' => $request->tanggal ?? $data->tanggal,
            'admin_id' => $request->user()->id, // admin terakhir yang update
        ]);

        return response()->json([
            'message' => 'Transaksi jimpitan berhasil diperbarui',
            'data' => $data
        ]);
    }

    /**
     * Menghapus transaksi berdasarkan ID.
     * Endpoint: DELETE /api/jimpitan/delete/{id}
     */
    public function destroy($id)
    {
        $data = JimpitanTransaction::findOrFail($id);
        $data->delete();

        return response()->json(['message' => 'Transaksi jimpitan berhasil dihapus']);
    }

    /**
     * Mengekspor data laporan ke format CSV.
     * Endpoint: GET /api/jimpitan/laporan/export
     */
    public function export(Request $request)
    {
        // Siapkan Query (Sama seperti index, tetapi tanpa paginasi)
        $query = JimpitanTransaction::with('admin');

        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe);
        }
        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        }
        if ($request->filled('year')) {
            $query->whereYear('tanggal', $request->year);
        }
        if ($request->filled('q')) {
            $query->where('keterangan', 'like', '%' . $request->q . '%');
        }

        $transaksi = $query->orderBy('tanggal', 'asc')->get();

        $fileName = 'laporan_jimpitan_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        // Buat StreamedResponse untuk efisiensi memori
        $callback = function () use ($transaksi) {
            $file = fopen('php://output', 'w');

            // Header Kolom CSV
            fputcsv($file, ['ID', 'Admin', 'Tipe', 'Jumlah', 'Keterangan', 'Tanggal Transaksi', 'Waktu Input']);

            // Data Saldo Akhir (opsional, dihitung di akhir file)
            $saldo = 0;

            // Isi Data Transaksi
            foreach ($transaksi as $trx) {
                $saldo += $trx->tipe === 'pemasukan' ? $trx->jumlah : -$trx->jumlah;

                fputcsv($file, [
                    $trx->id,
                    $trx->admin->name ?? 'N/A',
                    $trx->tipe,
                    $trx->jumlah,
                    $trx->keterangan,
                    $trx->tanggal,
                    $trx->created_at,
                ]);
            }

            // Baris Saldo Akhir Total
            fputcsv($file, ['', '', 'SALDO AKHIR TOTAL', $saldo, '', '', '']);

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
