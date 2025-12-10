<?php

namespace App\Http\Controllers;

use App\Models\Periode;
use App\Models\SampahTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SampahTransactionController extends Controller
{

    // F. Sampah (SampahController) - GET /api/sampah/laporan
    public function index(Request $request)
    {
        // Ambil parameter pagination
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
        // 🔹 Tentukan rentang tanggal default berdasarkan periode
        // =====================================================
        $from = $request->from
            ? Carbon::parse($request->from)->startOfDay()
            : ($periode?->tanggal_mulai ? Carbon::parse($periode->tanggal_mulai)->startOfDay() : now()->startOfYear());

        $to = $request->to
            ? Carbon::parse($request->to)->endOfDay()
            : ($periode?->tanggal_selesai ? Carbon::parse($periode->tanggal_selesai)->endOfDay() : now()->endOfYear());

        // =====================================================
        // 🔹 Query utama
        // =====================================================
        $query = SampahTransaction::with('admin')->whereBetween('tanggal', [$from, $to]);

        // Filter tanggal tunggal (override)
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

        // Pencarian
        if ($request->filled('q')) {
            $query->where('title', 'like', '%' . $request->q . '%');
        }

        // =====================================================
        // 🔹 Pagination result (ASC untuk run saldo)
        // =====================================================
        $transactions = $query->orderBy('tanggal', 'asc')->paginate($perPage);

        // ======================================================
        // 🔥 SALDO GLOBAL SAMPah
        // ======================================================
        $saldoAkhirTotal = SampahTransaction::sum(DB::raw("
        CASE 
            WHEN tipe = 'pemasukan' THEN jumlah 
            ELSE -jumlah 
        END
    "));

        // 🔥 SALDO filtered
        $saldoFiltered = $query->clone()->sum(DB::raw("
        CASE 
            WHEN tipe = 'pemasukan' THEN jumlah 
            ELSE -jumlah 
        END
    "));

        // ======================================================
        // 🔥 Tambahkan SALDO SEMENTARA (Running balance)
        // ======================================================
        $filteredAll = $query->clone()->orderBy('tanggal', 'asc')->get();

        $saldoSementara = 0;
        $mapSaldo = [];

        foreach ($filteredAll as $item) {
            $saldoSementara += ($item->tipe === 'pemasukan' ? $item->jumlah : -$item->jumlah);
            $mapSaldo[$item->id] = $saldoSementara;
        }

        // Isi value saldo sementara pada hasil pagination
        $dataWithSaldo = collect($transactions->items())->map(function ($trx) use ($mapSaldo) {
            $trx->saldo_sementara = $mapSaldo[$trx->id] ?? 0;
            return $trx;
        });

        // ======================================================
        // 🔥 Return JSON Response
        // ======================================================
        return response()->json([
            'message' => 'Data transaksi sampah berhasil diambil',

            'periode' => [
                'id'   => $periode?->id,
                'nama' => $periode?->nama,
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],

            'saldo_akhir_total' => $saldoAkhirTotal,
            'saldo_filter' => $saldoFiltered,

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

            'data' => $dataWithSaldo,
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

        $data = $query->orderBy('tanggal')->get();

        // ===== XML ROWS =====
        $xml = '';

        // Header
        $xml .= '<row>';
        $xml .= '<c t="inlineStr"><is><t>ID</t></is></c>';
        $xml .= '<c t="inlineStr"><is><t>Admin</t></is></c>';
        $xml .= '<c t="inlineStr"><is><t>Tipe</t></is></c>';
        $xml .= '<c t="inlineStr"><is><t>Jumlah</t></is></c>';
        $xml .= '<c t="inlineStr"><is><t>Keterangan</t></is></c>';
        $xml .= '<c t="inlineStr"><is><t>Tanggal</t></is></c>';
        $xml .= '<c t="inlineStr"><is><t>Dibuat Pada</t></is></c>';
        $xml .= '</row>';

        // Data
        foreach ($data as $trx) {
            $xml .= '<row>';

            $xml .= '<c t="inlineStr"><is><t>' . $trx->id . '</t></is></c>';
            $xml .= '<c t="inlineStr"><is><t>' . htmlspecialchars($trx->admin->name ?? 'N/A') . '</t></is></c>';
            $xml .= '<c t="inlineStr"><is><t>' . ucfirst($trx->tipe) . '</t></is></c>';
            $xml .= '<c t="inlineStr"><is><t>' . $trx->jumlah . '</t></is></c>';
            $xml .= '<c t="inlineStr"><is><t>' . htmlspecialchars($trx->keterangan) . '</t></is></c>';
            $xml .= '<c t="inlineStr"><is><t>' . $trx->tanggal . '</t></is></c>';
            $xml .= '<c t="inlineStr"><is><t>' . $trx->created_at . '</t></is></c>';

            $xml .= '</row>';
        }

        // ===== ZIP XLSX =====
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);

        // Fixed content (sama kayak rekap arisan)
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1"
      Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"
      Target="xl/workbook.xml"/>
</Relationships>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1"
      Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"
      Target="worksheets/sheet1.xml"/>
</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Laporan Sampah" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>');

        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>' . $xml . '</sheetData>
</worksheet>');

        $zip->close();

        $filename = 'laporan_sampah_' . now()->format('Ymd_His') . '.xlsx';

        return response()->download($tmp, $filename, [
            "Content-Type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        ])->deleteFileAfterSend(true);
    }
}
