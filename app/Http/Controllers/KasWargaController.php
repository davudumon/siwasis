<?php

namespace App\Http\Controllers;

use App\Models\KasWarga;
use App\Models\Periode;
use App\Models\Warga;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KasWargaController extends Controller
{
    /**
     * GET: Ambil rekap kas warga (bisa difilter berdasarkan periode & tanggal multiple)
     */
    public function rekap(Request $request)
    {
        $request->validate([
            'periode_id' => 'nullable|exists:periode,id',
            'year' => 'nullable|digits:4',
            'page' => 'nullable|integer|min:1',
            'q' => 'nullable|string',
            'rt' => 'nullable|string',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'min' => 'nullable|numeric|min:0',
            'max' => 'nullable|numeric|min:0',
        ]);

        // 🔹 Ambil tanggal dari periode atau tahun
        if ($request->filled('periode_id')) {
            $periode = Periode::find($request->periode_id);
            $startDate = Carbon::parse($periode->start_date);
            $endDate = Carbon::parse($periode->end_date);
            $periodeNama = $periode->nama;
            $periodeId = $periode->id;
        } elseif ($request->filled('year')) {
            $year = $request->year;
            $startDate = Carbon::create($year, 1, 1);
            $endDate = Carbon::create($year, 12, 31);
            $periodeNama = "Tahun $year";
            $periodeId = null;
        } else {
            return response()->json(['message' => 'Harus mengirim periode_id atau year'], 422);
        }

        
        $dates = collect();
        $period = CarbonPeriod::create($startDate, '14 days', $endDate);
        foreach ($period as $date) {
            $dates->push($date->toDateString());
        }

        // 🔹 Ambil data warga + status bayar dari kas_warga
        $query = DB::table('warga')
            ->leftJoin('kas_warga', function ($join) use ($periodeId, $dates) {
                $join->on('warga.id', '=', 'kas_warga.warga_id')
                    ->whereIn('kas_warga.tanggal', $dates);
                if ($periodeId) {
                    $join->where('kas_warga.periode_id', $periodeId);
                }
            })
            ->select(
                'warga.id',
                'warga.nama',
                'warga.rt',
                'kas_warga.tanggal',
                'kas_warga.status',
                'kas_warga.jumlah'
            )
            ->when($request->q, fn($q) => $q->where('warga.nama', 'like', "%{$request->q}%"))
            ->when($request->rt, fn($q) => $q->where('warga.rt', $request->rt))
            ->when($request->min, fn($q) => $q->where('kas_warga.jumlah', '>=', $request->min))
            ->when($request->max, fn($q) => $q->where('kas_warga.jumlah', '<=', $request->max))
            ->when($request->from && $request->to, fn($q) => $q->whereBetween('kas_warga.tanggal', [$request->from, $request->to]))
            ->orderBy('warga.rt')
            ->orderBy('warga.nama');

        // Pagination opsional
        $data = $request->filled('page')
            ? $query->paginate(10)
            : $query->get();

        return response()->json([
            'message' => 'Rekap kas warga berhasil diambil',
            'periode' => $periodeNama,
            'dates' => $dates,
            'filters' => [
                'periode_id' => $periodeId,
                'year' => $request->year,
                'rt' => $request->rt,
                'q' => $request->q,
                'from' => $request->from,
                'to' => $request->to,
                'min' => $request->min,
                'max' => $request->max,
            ],
            'data' => $data,
        ]);
    }

    /**
     * POST /api/kas/rekap/save
     * Simpan hasil centang status bayar
     */
    public function rekapSave(Request $request)
    {
        $request->validate([
            'periode_id' => 'required|exists:periode,id',
            'updates' => 'required|array',
            'updates.*.warga_id' => 'required|exists:warga,id',
            'updates.*.tanggal' => 'required|date',
            'updates.*.status' => 'required|in:sudah_bayar,belum_bayar',
        ]);

        $now = now();
        $adminId = $request->user()->id;

        // Bentuk array untuk upsert
        $data = array_map(function ($item) use ($request, $adminId, $now) {
            return [
                'warga_id' => $item['warga_id'],
                'periode_id' => $request->periode_id,
                'tanggal' => $item['tanggal'],
                'status' => $item['status'],
                'admin_id' => $adminId,
                'updated_at' => $now,
                'created_at' => $now,
            ];
        }, $request->updates);

        // 🔥 Upsert massal (insert/update sekaligus)
        KasWarga::upsert(
            $data,
            ['warga_id', 'periode_id', 'tanggal'], // unique keys
            ['status', 'admin_id', 'updated_at']   // columns to update if duplicate
        );

        return response()->json([
            'message' => 'Rekap kas berhasil disimpan (batch upsert)',
            'count' => count($data),
        ]);
    }

    public function export(Request $request)
    {
        $request->validate([
            'periode_id' => 'nullable|exists:periode,id',
            'year' => 'nullable|digits:4',
        ]);

        // 🔹 Gunakan query yang sama dengan rekap
        $data = $this->getFilteredKas($request)->orderBy('warga.rt')->orderBy('warga.nama')->get();

        $filename = 'rekap_kas_warga_' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($data) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF"); // biar kebaca di Excel UTF-8

            fputcsv($handle, ['Nama', 'RT', 'Tanggal', 'Status', 'Jumlah']);

            foreach ($data as $row) {
                fputcsv($handle, [
                    $row->nama,
                    $row->rt,
                    $row->tanggal,
                    ucfirst(str_replace('_', ' ', $row->status)),
                    number_format($row->jumlah ?? 0, 0, ',', '.'),
                ]);
            }

            fclose($handle);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    /**
     * 🔹 Helper internal untuk rekap/export agar query konsisten
     */
    private function getFilteredKas(Request $request)
    {
        $periodeId = $request->periode_id;
        $dates = collect();

        if ($periodeId) {
            $periode = Periode::find($periodeId);
            $startDate = Carbon::parse($periode->start_date);
            $endDate = Carbon::parse($periode->end_date);
        } elseif ($request->filled('year')) {
            $year = $request->year;
            $startDate = Carbon::create($year, 1, 1);
            $endDate = Carbon::create($year, 12, 31);
        } else {
            $startDate = now()->startOfYear();
            $endDate = now()->endOfYear();
        }

        $period = CarbonPeriod::create($startDate, '14 days', $endDate);
        foreach ($period as $date) {
            $dates->push($date->toDateString());
        }

        return DB::table('warga')
            ->leftJoin('kas_warga', function ($join) use ($periodeId, $dates) {
                $join->on('warga.id', '=', 'kas_warga.warga_id')
                    ->whereIn('kas_warga.tanggal', $dates);
                if ($periodeId) {
                    $join->where('kas_warga.periode_id', $periodeId);
                }
            })
            ->select(
                'warga.nama',
                'warga.rt',
                'kas_warga.tanggal',
                'kas_warga.status',
                'kas_warga.jumlah'
            );
    }
}
