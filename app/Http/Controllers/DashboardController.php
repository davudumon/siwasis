<?php

namespace App\Http\Controllers;

use App\Models\KasRt;
use App\Models\KasWarga;
use App\Models\GiliranArisan;
use App\Models\Periode;
use App\Models\Warga;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        /**
         *  Tentukan periode otomatis jika tidak dikirim
         */
        $periode = null;
        if ($request->filled('periode')) {
            $periode = Periode::where('id', $request->periode)->first();
        }
        if (!$periode) {
            $periode = Periode::latest()->first();
        }

        $from = $request->from ?? ($periode?->tanggal_mulai ?? now()->startOfYear());
        $to   = $request->to ?? ($periode?->tanggal_selesai ?? now()->endOfYear());

        /**
         *  RINGKASAN KEUANGAN (KAS RT + ARISAN)
         */
        $totalPemasukanKas = KasRt::where('tipe', 'pemasukan')
            ->whereBetween('tanggal', [$from, $to])
            ->sum('jumlah');

        $totalPengeluaranKas = KasRt::where('tipe', 'pengeluaran')
            ->whereBetween('tanggal', [$from, $to])
            ->sum('jumlah');

        $totalSetoranArisan = KasWarga::where('status', 'sudah_bayar')
            ->whereHas('warga', function ($q) {
                $q->where('role', 'arisan');
            })
            ->whereBetween('tanggal', [$from, $to])
            ->sum('jumlah');

        $totalPemasukan = $totalPemasukanKas + $totalSetoranArisan;
        $saldo = $totalPemasukan - $totalPengeluaranKas;

        /**
         *  CHART PEMASUKAN VS PENGELUARAN PER BULAN
         */
        $chartKeuangan = KasRt::select(
                DB::raw('DATE_FORMAT(tanggal, "%M %Y") as bulan'),
                DB::raw('SUM(CASE WHEN tipe = "pemasukan" THEN jumlah ELSE 0 END) as total_pemasukan'),
                DB::raw('SUM(CASE WHEN tipe = "pengeluaran" THEN jumlah ELSE 0 END) as total_pengeluaran')
            )
            ->whereBetween('tanggal', [$from, $to])
            ->groupBy('bulan')
            ->orderByRaw('MIN(tanggal)')
            ->get();

        /**
         *  REKAP KAS SEMUA WARGA
         */
        $rekapKasWarga = Warga::select(
                'warga.id',
                'warga.rt',
                'warga.nama',
                'warga.role',
                DB::raw('COUNT(kas_warga.id) as jumlah_setoran'),
                DB::raw('COALESCE(SUM(kas_warga.jumlah), 0) as total_setoran'),
                DB::raw('GROUP_CONCAT(DATE_FORMAT(kas_warga.tanggal, "%Y-%m-%d") ORDER BY kas_warga.tanggal ASC SEPARATOR ", ") as tanggal_setoran')
            )
            ->leftJoin('kas_warga', function ($join) use ($from, $to) {
                $join->on('kas_warga.warga_id', '=', 'warga.id')
                    ->where('kas_warga.status', 'sudah_bayar')
                    ->whereBetween('kas_warga.tanggal', [$from, $to]);
            })
            ->where('warga.role', '!=', 'admin')
            ->groupBy('warga.id', 'warga.rt', 'warga.nama', 'warga.role')
            ->orderBy('warga.rt')
            ->orderBy('warga.nama')
            ->get();

        /**
         * 4️⃣ STATUS GILIRAN ARISAN (KHUSUS WARGA ARISAN)
         */
        $data = GiliranArisan::with('warga')->where('periode_id', $periode->id)->get(['warga_id', 'status']);

        $statusArisan = $data->map(function ($item) {
            return [
                'nama' => $item->warga->nama ?? '-',
                'status' => $item->status ?? '-',
            ];
        });

        /**
         * KEMBALIKAN SEMUA DATA
         */
        return response()->json([
            'message' => 'Ringkasan dashboard berhasil diambil',
            'periode' => [
                'from' => $from,
                'to'   => $to,
                'nama' => $periode?->nama,
            ],
            'data' => [
                'kas_total' => [
                    'pemasukan' => (int) $totalPemasukanKas,
                    'pengeluaran' => (int) $totalPengeluaranKas,
                    'pemasukan_arisan' => (int) $totalSetoranArisan,
                    'saldo' => (int) $saldo,
                ],
                'chart_keuangan' => $chartKeuangan,
                'rekap_kas_warga' => $rekapKasWarga,
                'status_arisan' => $statusArisan,
            ]
        ], 200);
    }
}
