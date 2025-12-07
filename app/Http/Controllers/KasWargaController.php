<?php

namespace App\Http\Controllers;

use App\Models\KasWarga;
use App\Models\Periode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KasWargaController extends Controller
{
    /**
     * Helper untuk mengumpulkan data rekap Kas Warga
     */
    private function getRekapData(Request $request, $isPaginated = true)
    {
        // Validasi
        $request->validate([
            'periode_id' => 'nullable|exists:periode,id',
            'year'       => 'nullable|digits:4',
            'page'       => 'nullable|integer|min:1',
            'q'          => 'nullable|string',
            'rt'         => 'nullable|string',
            'from'       => 'nullable|date',
            'to'         => 'nullable|date',
            'min'        => 'nullable|numeric|min:0',
            'max'        => 'nullable|numeric|min:0',
        ]);

        // ============================
        // Tentukan periode
        // ============================
        $periode = null;

        if ($request->filled('periode_id')) {
            $periode = Periode::find($request->periode_id);
            if (!$periode) {
                return [
                    'error' => true,
                    'message' => 'Periode tidak ditemukan.',
                    'code' => 404
                ];
            }
        } elseif (!$request->filled('year')) {
            // fallback → periode terbaru
            $periode = Periode::orderByDesc('tanggal_mulai')->first();
            if (!$periode) {
                return [
                    'error' => true,
                    'message' => 'Tidak ada periode tersedia.',
                    'code' => 404
                ];
            }
        }

        if ($periode) {
            $startDate = Carbon::parse($periode->tanggal_mulai)->startOfDay();
            $endDate   = Carbon::parse($periode->tanggal_selesai)->endOfDay();
            $periodeNama = $periode->nama;
            $periodeId   = $periode->id;
            $nominalKas  = $periode->nominal ?? 0; 
        } elseif ($request->filled('year')) {
            $year = $request->year;
            $startDate = Carbon::create($year, 1, 1)->startOfDay();
            $endDate   = Carbon::create($year, 12, 31)->endOfDay();
            $periodeNama = "Tahun $year";
            $periodeId = null;
            $nominalKas = 0;
        } else {
            return [
                'error' => true,
                'message' => 'Harus mengirim periode_id atau year.',
                'code' => 422
            ];
        }

        // ============================
        // Generate tanggal interval 14 hari
        // ============================
        $dates = collect();
        $period = CarbonPeriod::create($startDate, '14 days', $endDate);

        foreach ($period as $d) {
            $dates->push($d->toDateString());
        }

        // ============================
        // Query data kas warga
        // ============================
        $rawData = DB::table('warga')
            ->select(
                'warga.id as warga_id',
                'warga.nama',
                'warga.rt',
                'kas_warga.tanggal',
                'kas_warga.status',
                'kas_warga.jumlah'
            )
            ->leftJoin('kas_warga', function ($join) use ($periodeId, $dates) {
                $join->on('warga.id', '=', 'kas_warga.warga_id')
                    ->whereIn('kas_warga.tanggal', $dates);

                if ($periodeId) {
                    $join->where('kas_warga.periode_id', $periodeId);
                }
            })
            ->when(
                $request->q,
                fn($q) =>
                $q->where('warga.nama', 'like', "%{$request->q}%")
            )
            ->when(
                $request->rt,
                fn($q) =>
                $q->where('warga.rt', $request->rt)
            )
            ->when(
                $request->min,
                fn($q) =>
                $q->where('kas_warga.jumlah', '>=', $request->min)
            )
            ->when(
                $request->max,
                fn($q) =>
                $q->where('kas_warga.jumlah', '<=', $request->max)
            )
            ->when(
                $request->from && $request->to,
                fn($q) => $q->whereBetween('kas_warga.tanggal', [$request->from, $request->to])
            )
            ->orderBy('warga.rt')
            ->orderBy('warga.nama');

        $data = $rawData->get();

        return [
            'error' => false,
            'data' => $data,
            'dates' => $dates,
            'periodeNama' => $periodeNama,
            'periodeId' => $periodeId,
            'nominalKas' => $nominalKas,
            'filters' => [
                'periode_id' => $periodeId,
                'year'       => $request->year,
                'rt'         => $request->rt,
                'q'          => $request->q,
                'from'       => $request->from,
                'to'         => $request->to,
                'min'        => $request->min,
                'max'        => $request->max,
            ]
        ];
    }

    /**
     * API GET rekap kas warga
     */
    public function rekap(Request $request)
    {
        // Ambil semua data transaksi dari getRekapData (TANPA pagination)
        $result = $this->getRekapData($request, false);

        if ($result['error']) {
            return response()->json(['message' => $result['message']], $result['code']);
        }

        $rawTransactions = collect($result['data']); // semua transaksi

        // ============================================================
        // GROUPING PER WARGA
        // ============================================================
        $groupedData = $rawTransactions->groupBy('warga_id')->map(function ($items) use ($result) {

            $warga = $items->first();
            $total = 0;
            $paymentStatus = [];

            foreach ($result['dates'] as $date) {
                $trx = $items->firstWhere('tanggal', $date);

                if ($trx) {
                    $total += $trx->jumlah;
                    $paymentStatus[$date] = [
                        'status' => $trx->status,
                        'jumlah' => $trx->jumlah,
                    ];
                } else {
                    $paymentStatus[$date] = [
                        'status' => 'belum_bayar',
                        'jumlah' => 0,
                    ];
                }
            }

            return [
                'warga_id' => $warga->warga_id,
                'nama' => $warga->nama,
                'rt' => $warga->rt,
                'total_setoran' => $total,
                'payment_status' => $paymentStatus,
            ];
        })->values();


        // ============================================================
        // PAGINATE PER WARGA (BUKAN PER TRANSAKSI)
        // ============================================================
        $page    = $request->get('page', 1);
        $perPage = 10;

        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $groupedData->slice(($page - 1) * $perPage, $perPage)->values(),
            $groupedData->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // ============================================================
        // RETURN JSON
        // ============================================================
        return response()->json([
            'message' => 'Rekap kas warga berhasil diambil',
            'periode' => $result['periodeNama'],
            'nominal_kas' => $result['nominalKas'],
            'dates' => $result['dates'],
            'filters' => $result['filters'],
            'data' => $paginated,
        ]);
    }




    /**
     * API POST simpan/update rekap kas warga
     */
    public function rekapSave(Request $request)
    {
        $request->validate([
            'periode_id'            => 'required|exists:periode,id',
            'updates'               => 'required|array',
            'updates.*.warga_id'    => 'required|exists:warga,id',
            'updates.*.tanggal'     => 'required|date',
            'updates.*.status'      => 'required|in:sudah_bayar,belum_bayar',
            'updates.*.jumlah'      => 'nullable|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $periode = Periode::find($request->periode_id);
            $defaultJumlah = $periode->nominal_kas;

            foreach ($request->updates as $i) {

                $jumlah = $i['jumlah'] ?? $defaultJumlah;
                if ($jumlah === null || $jumlah === "") {
                    $jumlah = $defaultJumlah;
                }

                KasWarga::updateOrCreate(
                    [
                        'warga_id' => $i['warga_id'],
                        'periode_id' => $request->periode_id,
                        'tanggal' => $i['tanggal'],
                    ],
                    [
                        'status' => $i['status'],
                        'jumlah' => $jumlah,
                        'admin_id' => $request->user()->id,
                        'updated_at' => now(),
                    ]
                );
            }

            DB::commit();
            return response()->json(['message' => 'Rekap kas warga berhasil disimpan']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan rekap kas warga.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Export CSV
     */
    public function exportRekap(Request $request)
    {
        $result = $this->getRekapData($request, false);

        if ($result['error']) {
            return response()->json(['message' => $result['message']], $result['code']);
        }

        $all = $result['data'];
        $dates = $result['dates'];
        $periodeNama = $result['periodeNama'];
        $nominalKas = $result['nominalKas'];

        // susun data per warga
        $rekap = $all->groupBy('warga_id')->map(
            function ($items) use ($dates) {
                $warga = $items->first();
                $total = 0;
                $payment = [];

                foreach ($dates as $date) {
                    $trx = $items->firstWhere('tanggal', $date);

                    if ($trx) {
                        $total += $trx->jumlah;
                        $payment[$date] = [
                            'status' => $trx->status === 'sudah_bayar' ? '✅' : '❌',
                            'jumlah' => $trx->jumlah,
                        ];
                    } else {
                        $payment[$date] = [
                            'status' => '⬜',
                            'jumlah' => 0,
                        ];
                    }
                }

                return [
                    'id' => $warga->warga_id,
                    'nama' => $warga->nama,
                    'rt' => $warga->rt,
                    'total_setoran' => $total,
                    'payment_status' => $payment,
                ];
            }
        )->values();

        $fileName = 'rekap_kas_' . Str::slug($periodeNama) . '_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$fileName\""
        ];

        $callback = function () use ($rekap, $dates, $nominalKas) {
            $file = fopen('php://output', 'w');

            // header
            $mainHeader = ['Nama Warga', 'RT', 'Total Setoran'];
            $dateHeaders = [];

            foreach ($dates as $date) {
                $dateHeaders[] = Carbon::parse($date)->format('d/m/Y');
            }

            fputcsv($file, array_merge($mainHeader, $dateHeaders));

            foreach ($rekap as $row) {
                $line = [
                    $row['nama'],
                    $row['rt'],
                    $row['total_setoran'],
                ];

                foreach ($dates as $d) {
                    $line[] = $row['payment_status'][$d]['status'] ?? '⬜';
                }

                fputcsv($file, $line);
            }

            // summary
            fputcsv($file, []);
            fputcsv($file, ['Total Semua:', '', $rekap->sum('total_setoran')]);
            fputcsv($file, ['Nominal Kas Per Periode:', '', $nominalKas]);

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
