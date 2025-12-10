<?php

namespace App\Http\Controllers;

use App\Models\ArisanTransaction;
use App\Models\Periode;
use App\Models\Warga;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArisanTransactionController extends Controller
{
    /**
     * Helper function untuk mendapatkan data rekapitulasi Arisan (Digunakan oleh rekap dan exportRekap).
     */
    private function getRekapData(Request $request)
    {
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
        // Tentukan Periode
        // ============================
        $periode = null;

        if ($request->filled('periode_id')) {
            $periode = Periode::find($request->periode_id);
            if (!$periode) {
                return ['error' => true, 'message' => 'Periode tidak ditemukan', 'code' => 404];
            }
        } elseif (!$request->filled('year')) {
            // fallback → periode terbaru
            $periode = Periode::orderByDesc('tanggal_mulai')->first();
            if (!$periode) {
                return ['error' => true, 'message' => 'Tidak ada periode tersedia', 'code' => 404];
            }
        }

        if ($periode) {
            $startDate = Carbon::parse($periode->tanggal_mulai)->startOfDay();
            $endDate   = Carbon::parse($periode->tanggal_selesai)->endOfDay();
            $periodeNama = $periode->nama;
            $periodeId   = $periode->id;
            $nominal     = $periode->nominal ?? 0;
        } elseif ($request->filled('year')) {
            $year = $request->year;
            $startDate = Carbon::create($year, 1, 1)->startOfDay();
            $endDate   = Carbon::create($year, 12, 31)->endOfDay();
            $periodeNama = "Tahun $year";
            $periodeId = null;
            $nominal = 0;
        }

        // ============================
        // Generate interval tanggal 14 hari
        // ============================
        $dates = collect();
        $period = CarbonPeriod::create($startDate, '14 days', $endDate);
        foreach ($period as $d) {
            $dates->push($d->toDateString());
        }

        // ============================
        // Query data transaksi
        // ============================
        $raw = DB::table('warga')
            ->select(
                'warga.id as warga_id',
                'warga.nama',
                'warga.rt',
                'arisan_transactions.tanggal',
                'arisan_transactions.status',
                'arisan_transactions.jumlah'
            )
            ->leftJoin('arisan_transactions', function ($join) use ($periodeId, $dates) {
                $join->on('warga.id', '=', 'arisan_transactions.warga_id')
                    ->whereIn('arisan_transactions.tanggal', $dates);

                if ($periodeId) {
                    $join->where('arisan_transactions.periode_id', $periodeId);
                }
            })
            ->when($request->q, fn($q) => $q->where('warga.nama', 'like', "%{$request->q}%"))
            ->when($request->rt, fn($q) => $q->where('warga.rt', $request->rt))
            ->when($request->min, fn($q) => $q->where('arisan_transactions.jumlah', '>=', $request->min))
            ->when($request->max, fn($q) => $q->where('arisan_transactions.jumlah', '<=', $request->max))
            ->when(
                $request->from && $request->to,
                fn($q) => $q->whereBetween('arisan_transactions.tanggal', [$request->from, $request->to])
            )
            ->orderBy('warga.rt')
            ->orderBy('warga.nama')
            ->get();

        return [
            'error'        => false,
            'data'         => $raw,
            'dates'        => $dates,
            'periodeNama'  => $periodeNama,
            'periodeId'    => $periodeId,
            'nominal'      => $nominal,
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
     * Rekap Arisan (disamakan struktur dengan KasWargaController)
     */
    public function rekap(Request $request)
    {
        $result = $this->getRekapData($request);

        if ($result['error']) {
            return response()->json(['message' => $result['message']], $result['code']);
        }

        $raw = collect($result['data']);

        // ============================
        // GROUP PER WARGA
        // ============================
        $grouped = $raw->groupBy('warga_id')->map(function ($items) use ($result) {

            $warga = $items->first();
            $total = 0;
            $paymentStatus = [];

            foreach ($result['dates'] as $date) {
                $trx = $items->firstWhere('tanggal', $date);

                if ($trx) {
                    $total += $trx->jumlah;
                    $paymentStatus[$date] = [
                        'status' => $trx->status,
                        'jumlah' => $trx->jumlah
                    ];
                } else {
                    $paymentStatus[$date] = [
                        'status' => 'belum_bayar',
                        'jumlah' => 0
                    ];
                }
            }

            return [
                'warga_id'       => $warga->warga_id,
                'nama'          => $warga->nama,
                'rt'            => $warga->rt,
                'total_setoran' => $total,
                'payment_status' => $paymentStatus
            ];
        })->values();

        // ============================
        // PAGINATION (PER WARGA)
        // ============================
        $page    = $request->get('page', 1);
        $perPage = 10;

        $paginated = new LengthAwarePaginator(
            $grouped->slice(($page - 1) * $perPage, $perPage)->values(),
            $grouped->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $listRT = Warga::select('rt')->distinct()->pluck('rt');

        return response()->json([
            'message' => 'Rekap arisan berhasil diambil',
            'periode' => $result['periodeNama'],
            'list_rt' => $listRT,
            'nominal_arisan' => $result['nominal'],
            'dates'   => $result['dates'],
            'filters' => $result['filters'],
            'data'    => $paginated,
        ]);
    }

    public function rekapSave(Request $request)
    {
        $request->validate([
            'periode_id' => 'required|exists:periode,id',
            'updates' => 'required|array',
            'updates.*.warga_id' => 'required|exists:warga,id',
            'updates.*.tanggal' => 'required|date',
            'updates.*.status' => 'required|in:sudah_bayar,belum_bayar',
            'updates.*.jumlah' => 'nullable|numeric|min:0', // boleh null
        ]);

        try {
            DB::beginTransaction();

            // Ambil nominal default dari tabel periode
            $periode = Periode::find($request->periode_id);
            $defaultJumlah = $periode->nominal;

            foreach ($request->updates as $item) {

                // Jika null → ganti pakai nominal periode
                $jumlah = $item['jumlah'] ?? $defaultJumlah;
                if ($jumlah === null || $jumlah === "") {
                    $jumlah = $defaultJumlah;
                }

                ArisanTransaction::updateOrCreate(
                    [
                        'warga_id' => $item['warga_id'],
                        'periode_id' => $request->periode_id,
                        'tanggal' => $item['tanggal'],
                    ],
                    [
                        'status' => $item['status'],
                        'jumlah' => $jumlah,
                        'admin_id' => $request->user()->id,
                        'updated_at' => now(),
                    ]
                );
            }

            DB::commit();
            return response()->json(['message' => 'Rekap arisan berhasil disimpan']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan rekap arisan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Mengekspor data rekapitulasi arisan ke format CSV.
     * Endpoint: GET /api/arisan/rekap/export
     */
    public function exportRekap(Request $request)
    {
        $result = $this->getRekapData($request);

        if ($result['error']) {
            return response()->json(['message' => $result['message']], $result['code']);
        }

        $allTransactions = $result['data'];
        $dates = $result['dates'];
        $periodeNama = $result['periodeNama'];

        // same grouping logic you used
        $rekapData = $allTransactions->groupBy('warga_id')->map(function ($transactions) use ($dates) {
            $warga = $transactions->first();
            $totalSetoran = 0;
            $paymentStatus = [];

            foreach ($dates as $d) {
                $trx = $transactions->firstWhere('tanggal', $d);

                if ($trx) {
                    $status = $trx->status === 'sudah_bayar' ? '✔' : '○';
                    $totalSetoran += $trx->jumlah;
                } else {
                    $status = '○';
                }

                $paymentStatus[$d] = $status;
            }

            return [
                'nama' => $warga->nama,
                'rt' => $warga->rt,
                'total_setoran' => $totalSetoran,
                'payment_status' => $paymentStatus,
            ];
        })->values();


        // ===============================
        // Generate XML untuk sheet Excel
        // ===============================
        $xmlRows = '';

        // header
        $xmlRows .= '<row>';
        $xmlRows .= '<c t="inlineStr"><is><t>Nama Warga</t></is></c>';
        $xmlRows .= '<c t="inlineStr"><is><t>RT</t></is></c>';
        $xmlRows .= '<c t="inlineStr"><is><t>Total Setoran</t></is></c>';

        foreach ($dates as $date) {
            $xmlRows .= '<c t="inlineStr"><is><t>' . \Carbon\Carbon::parse($date)->format("d/m/Y") . '</t></is></c>';
        }
        $xmlRows .= '</row>';


        // data rows
        foreach ($rekapData as $item) {
            $xmlRows .= '<row>';

            $xmlRows .= '<c t="inlineStr"><is><t>' . htmlspecialchars($item['nama']) . '</t></is></c>';
            $xmlRows .= '<c t="inlineStr"><is><t>' . $item['rt'] . '</t></is></c>';
            $xmlRows .= '<c t="inlineStr"><is><t>' . $item['total_setoran'] . '</t></is></c>';

            foreach ($dates as $d) {
                $xmlRows .= '<c t="inlineStr"><is><t>' . $item['payment_status'][$d] . '</t></is></c>';
            }

            $xmlRows .= '</row>';
        }


        // ===============================
        // Buat ZIP struktur XLSX
        // ===============================

        // Temp file
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');

        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);

        // [Content_Types].xml
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
</Types>');

        // rels
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

        // workbook relationships
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>');

        // workbook xml
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
 xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Rekap" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>');

        // sheet XML (isi tabel)
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>'
            . $xmlRows .
            '</sheetData>
</worksheet>');

        $zip->close();


        // ===============================
        // Return ke browser
        // ===============================
        $fileName = "rekap_arisan_" . Str::slug($periodeNama) . "_" . now()->format("Ymd_His") . ".xlsx";

        return response()->download($tmp, $fileName, [
            "Content-Type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        ])->deleteFileAfterSend(true);
    }
}
