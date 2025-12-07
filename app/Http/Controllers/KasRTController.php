<?php

namespace App\Http\Controllers;

use App\Models\KasRT;
use App\Models\Periode;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KasRtController extends Controller
{
    /**
     * GET /api/kas-rt
     * Ambil semua transaksi kas RT (pemasukan & pengeluaran)
     */
    public function index(Request $request)
    {
        $query = KasRT::query();

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
        // 🔹 Tentukan rentang tanggal default berdasarkan periode
        //    (bisa ditimpa oleh from & to manual)
        // =====================================================
        $from = $request->from
            ? Carbon::parse($request->from)->startOfDay()
            : ($periode?->tanggal_mulai ? Carbon::parse($periode->tanggal_mulai)->startOfDay() : now()->startOfYear());

        $to = $request->to
            ? Carbon::parse($request->to)->endOfDay()
            : ($periode?->tanggal_selesai ? Carbon::parse($periode->tanggal_selesai)->endOfDay() : now()->endOfYear());

        // =====================================================
        // 🔹 Filter tanggal (range atau tanggal tunggal)
        // =====================================================
        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('tanggal', [$from, $to]);
        } elseif ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        } else {
            $query->whereBetween('tanggal', [$from, $to]);
        }

        // =====================================================
        // 🔹 Filter tipe pemasukan/pengeluaran
        // =====================================================
        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe);
        }

        // =====================================================
        // 🔹 Search by keterangan
        // =====================================================
        if ($request->filled('q')) {
            $query->where('keterangan', 'like', '%' . $request->q . '%');
        }

        // =====================================================
        // 🔹 Pagination
        // =====================================================
        $perPage = $request->get('per_page', 10);
        $kas = $query->orderBy('tanggal', 'desc')->paginate($perPage);

        // =====================================================
        // 🔹 Hitung saldo berjalan (ASC)
        // =====================================================
        $items = collect($kas->items());
        $sorted = $items->sortBy('tanggal');

        $saldo = 0;
        foreach ($sorted as $item) {
            $saldo += ($item->tipe === 'pemasukan') ? $item->jumlah : -$item->jumlah;
            $item->saldo_sementara = $saldo;
        }

        $finalItems = $sorted->sortByDesc('tanggal')->values();

        return response()->json([
            'message' => 'Data kas RT berhasil diambil',
            'periode' => [
                'id'   => $periode?->id,
                'nama' => $periode?->nama,
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'filter' => [
                'from' => $request->from ?? null,
                'to' => $request->to ?? null,
                'tanggal' => $request->tanggal ?? null,
                'tipe' => $request->tipe ?? null,
                'q' => $request->q ?? null,
            ],
            'pagination' => [
                'current_page' => $kas->currentPage(),
                'per_page' => $kas->perPage(),
                'total' => $kas->total(),
                'last_page' => $kas->lastPage(),
            ],
            'data' => $finalItems,
        ]);
    }





    /**
     * POST /api/kas-rt
     * Tambah transaksi kas RT baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'tipe' => 'required|in:pemasukan,pengeluaran',
            'jumlah' => 'required|numeric|min:0',
            'keterangan' => 'required|string|max:255',
            'tanggal' => 'required|date',
        ]);

        $kas = KasRt::create([
            'admin_id' => $request->user()->id,
            'tipe' => $request->tipe,
            'jumlah' => $request->jumlah,
            'keterangan' => $request->keterangan,
            'tanggal' => $request->tanggal,
        ]);

        return response()->json([
            'message' => 'Transaksi kas RT berhasil ditambahkan',
            'data' => $kas
        ], 201);
    }

    /**
     * PUT /api/kas-rt/{id}
     * Update transaksi kas RT
     */
    public function update(Request $request, $id)
    {
        $kas = KasRt::findOrFail($id);

        $request->validate([
            'tipe' => 'sometimes|in:pemasukan,pengeluaran',
            'jumlah' => 'sometimes|numeric|min:0',
            'keterangan' => 'sometimes|string|max:255',
            'tanggal' => 'sometimes|date',
        ]);

        $kas->update([
            'tipe' => $request->tipe ?? $kas->tipe,
            'jumlah' => $request->jumlah ?? $kas->jumlah,
            'keterangan' => $request->keterangan ?? $kas->keterangan,
            'tanggal' => $request->tanggal ?? $kas->tanggal,
        ]);

        return response()->json([
            'message' => 'Transaksi kas RT berhasil diperbarui',
            'data' => $kas
        ]);
    }

    /**
     * DELETE /api/kas-rt/{id}
     * Hapus transaksi kas RT
     */
    public function destroy($id)
    {
        $kas = KasRt::findOrFail($id);
        $kas->delete();

        return response()->json(['message' => 'Transaksi kas RT berhasil dihapus']);
    }

    /**
     * GET /api/kas-rt/summary
     * Ringkasan total kas RT (pemasukan, pengeluaran, saldo)
     */
    public function summary(Request $request)
    {
        $query = KasRt::query();

        // Filter berdasarkan tahun jika dikirim
        if ($request->filled('year')) {
            $query->whereYear('tanggal', $request->year);
        }

        $totalPemasukan = (clone $query)->where('tipe', 'pemasukan')->sum('jumlah');
        $totalPengeluaran = (clone $query)->where('tipe', 'pengeluaran')->sum('jumlah');
        $saldo = $totalPemasukan - $totalPengeluaran;

        return response()->json([
            'message' => 'Ringkasan kas RT berhasil diambil',
            'data' => [
                'total_pemasukan' => $totalPemasukan,
                'total_pengeluaran' => $totalPengeluaran,
                'saldo' => $saldo,
            ]
        ]);
    }

    public function export(Request $request)
    {
        $kas = $this->filteredQuery($request)
            ->orderBy('tanggal')
            ->get(['tanggal', 'tipe', 'jumlah', 'keterangan']);

        $filename = 'kas_rt_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($kas) {
            $handle = fopen('php://output', 'w');
            // Header CSV
            fputcsv($handle, ['Tanggal', 'Tipe', 'Jumlah', 'Keterangan']);

            foreach ($kas as $row) {
                fputcsv($handle, [
                    $row->tanggal,
                    ucfirst($row->tipe),
                    number_format($row->jumlah, 0, ',', '.'),
                    $row->keterangan
                ]);
            }

            fclose($handle);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
    private function filteredQuery(Request $request)
    {
        $query = KasRt::query();

        if ($request->filled('year')) {
            $query->whereYear('tanggal', $request->year);
        }

        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('tanggal', [$request->from, $request->to]);
        } elseif ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        }

        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe);
        }

        return $query;
    }
}
