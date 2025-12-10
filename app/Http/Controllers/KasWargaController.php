<?php

namespace App\Http\Controllers;

use App\Models\KasWarga;
use App\Models\Periode;
use App\Models\Warga;
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

        if ($request->filled('min')) {
            $min = (float) $request->min;
            $groupedData = $groupedData->filter(function ($row) use ($min) {
                return ($row['total_setoran'] ?? 0) >= $min;
            })->values();
        }

        if ($request->filled('max')) {
            $max = (float) $request->max;
            $groupedData = $groupedData->filter(function ($row) use ($max) {
                return ($row['total_setoran'] ?? 0) <= $max;
            })->values();
        }
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

        $listRt = Warga::select('rt')->distinct()->pluck('rt');

        // ============================================================
        // RETURN JSON
        // ============================================================
        return response()->json([
            'message' => 'Rekap kas warga berhasil diambil',
            'periode' => $result['periodeNama'],
            'list_rt' => $listRt,
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


        // ========= SUSUN DATA =============
        $rekap = $all->groupBy('warga_id')->map(
            function ($items) use ($dates) {

                $warga = $items->first();
                $total = 0;
                $payment = [];

                foreach ($dates as $date) {
                    $trx = $items->firstWhere('tanggal', $date);

                    if ($trx) {
                        $total += $trx->jumlah;
                        $payment[$date] = $trx->status === 'sudah_bayar' ? '✔' : '○';
                    } else {
                        $payment[$date] = '○';
                    }
                }

                return [
                    'nama'          => $warga->nama,
                    'rt'            => $warga->rt,
                    'total'         => $total,
                    'payment'       => $payment,
                ];
            }
        )->values();


        // ======= BUAT XML SHEET ===========
        $xmlRows = '';

        // header row
        $xmlRows .= '<row>';
        $xmlRows .= '<c t="inlineStr"><is><t>Nama Warga</t></is></c>';
        $xmlRows .= '<c t="inlineStr"><is><t>RT</t></is></c>';
        $xmlRows .= '<c t="inlineStr"><is><t>Total Setoran</t></is></c>';

        foreach ($dates as $d) {
            $xmlRows .= '<c t="inlineStr"><is><t>' . \Carbon\Carbon::parse($d)->format('d/m/Y') . '</t></is></c>';
        }
        $xmlRows .= '</row>';

        // data rows
        foreach ($rekap as $item) {
            $xmlRows .= '<row>';

            $xmlRows .= '<c t="inlineStr"><is><t>' . htmlspecialchars($item['nama']) . '</t></is></c>';
            $xmlRows .= '<c t="inlineStr"><is><t>' . $item['rt'] . '</t></is></c>';
            $xmlRows .= '<c t="inlineStr"><is><t>' . $item['total'] . '</t></is></c>';

            foreach ($dates as $dt) {
                $xmlRows .= '<c t="inlineStr"><is><t>' . $item['payment'][$dt] . '</t></is></c>';
            }

            $xmlRows .= '</row>';
        }

        // Summary
        $xmlRows .= '<row></row>';
        $xmlRows .= '<row>
                    <c t="inlineStr"><is><t>Total Semua</t></is></c>
                    <c></c>
                    <c t="inlineStr"><is><t>' . $rekap->sum('total') . '</t></is></c>
                 </row>';
        $xmlRows .= '<row>
                    <c t="inlineStr"><is><t>Nominal Kas Per Periode</t></is></c>
                    <c></c>
                    <c t="inlineStr"><is><t>' . $nominalKas . '</t></is></c>
                 </row>';


        // ===== BUAT FILE XLSX (ZIP + XML) =====
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');

        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);

        // Required files
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>
    <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
        <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
        <Default Extension="xml" ContentType="application/xml"/>
        <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
        <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    </Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
        <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
    </Relationships>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
        <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    </Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>
    <workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
      xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
        <sheets>
            <sheet name="Rekap Kas" sheetId="1" r:id="rId1"/>
        </sheets>
    </workbook>');

        // sheet data
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?>
    <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
        <sheetData>' . $xmlRows . '</sheetData>
    </worksheet>');

        $zip->close();


        // send to browser
        $filename = 'rekap_kas_' . Str::slug($periodeNama) . '_' . now()->format('Ymd_His') . '.xlsx';

        return response()->download($tmp, $filename, [
            "Content-Type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        ])->deleteFileAfterSend(true);
    }
}
