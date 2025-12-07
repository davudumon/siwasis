<?php

namespace App\Http\Controllers;

use App\Models\JimpitanTransaction;
use App\Models\Periode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        // Ambil parameter paginasi
        $perPage = $request->input('per_page', 10);

        // =====================================================
        // 🔹 Ambil Periode berdasarkan periode_id
        // =====================================================
        $periode = null;
        if ($request->filled('periode_id')) {
            $periode = Periode::find($request->periode_id);
        }
        if (!$periode) {
            $periode = Periode::latest()->first(); // default periode terakhir
        }

        // =====================================================
        // 🔹 Tentukan range tanggal default berdasarkan periode
        // =====================================================
        $from = $request->from
            ? Carbon::parse($request->from)->startOfDay()
            : ($periode?->tanggal_mulai ? Carbon::parse($periode->tanggal_mulai)->startOfDay() : now()->startOfYear());

        $to = $request->to
            ? Carbon::parse($request->to)->endOfDay()
            : ($periode?->tanggal_selesai ? Carbon::parse($periode->tanggal_selesai)->endOfDay() : now()->endOfYear());

        // =====================================================
        // 🔹 Query utama + default range periode
        // =====================================================
        $query = JimpitanTransaction::with('admin')->whereBetween('tanggal', [$from, $to]);

        // Filter tanggal tunggal
        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        }

        // Filter tipe
        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe);
        }

        if ($request->filled('min')) {
            $query->where('jumlah', '>=', $request->min);
        }
        if ($request->filled('max')) {
            $query->where('jumlah', '<=', $request->max);
        }

        // Search
        if ($request->filled('q')) {
            $query->where('keterangan', 'like', '%' . $request->q . '%');
        }

        // Ambil data paginated (DESC untuk tampilan)
        $transactions = $query->orderBy('tanggal', 'desc')->paginate($perPage);

        // ======================================================
        // 🔥 Hitung SALDO filter + saldo sementara (full filtered)
        // ======================================================
        $filteredAll = $query->clone()->orderBy('tanggal', 'asc')->get();

        $saldo = 0;
        $mapSaldo = [];

        foreach ($filteredAll as $item) {
            $saldo += ($item->tipe === 'masuk' || $item->tipe === 'pemasukan')
                ? $item->jumlah
                : -$item->jumlah;

            $mapSaldo[$item->id] = $saldo;
        }

        // Isi saldo sementara pada hasil pagination
        $finalItems = collect($transactions->items())->map(function ($trx) use ($mapSaldo) {
            $trx->saldo_sementara = $mapSaldo[$trx->id] ?? 0;
            return $trx;
        });

        // ======================================================
        // 🔥 Hitung total saldo global
        // ======================================================
        $totalSaldo = JimpitanTransaction::sum(DB::raw("
        CASE 
            WHEN tipe = 'masuk' OR tipe = 'pemasukan' THEN jumlah 
            ELSE -jumlah 
        END
    "));

        // ======================================================
        // 🔥 Return response
        // ======================================================
        return response()->json([
            'message' => 'Data transaksi jimpitan berhasil diambil',

            'periode' => [
                'id'   => $periode?->id,
                'nama' => $periode?->nama,
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],

            'saldo_akhir_total' => $totalSaldo,
            'saldo_filter' => $saldo,

            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
            ],

            'filters' => [
                'periode_id' => $periode?->id,
                'from'       => $request->from ?? null,
                'to'         => $request->to ?? null,
                'tanggal'    => $request->tanggal ?? null,
                'tipe'       => $request->tipe ?? null,
                'min'        => $request->min ?? null,
                'max'        => $request->max ?? null,
                'q'          => $request->q ?? null,
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
