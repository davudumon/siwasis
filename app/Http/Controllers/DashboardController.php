<?php

namespace App\Http\Controllers;

use App\Models\KasRT;
use App\Models\KasWarga;
use App\Models\Periode;
use App\Models\PeriodeWarga;
use App\Models\Warga;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        /**
         * 1️⃣ Tentukan periode otomatis jika tidak dikirim
         */
        $periode = null;
        if ($request->filled('periode')) {
            $periode = Periode::find($request->periode);
        }
        if (!$periode) {
            $periode = Periode::latest()->first();
        }

        $from = $request->from ?? ($periode?->tanggal_mulai ?? now()->startOfYear());
        $to   = $request->to ?? ($periode?->tanggal_selesai ?? now()->endOfYear());

        /**
         * 2️⃣ RINGKASAN KEUANGAN (KAS RT + ARISAN)
         */
        $totalPemasukanKas = KasRT::where('tipe', 'pemasukan')
            ->whereBetween('tanggal', [$from, $to])
            ->sum('jumlah');

        $totalPengeluaranKas = KasRT::where('tipe', 'pengeluaran')
            ->whereBetween('tanggal', [$from, $to])
            ->sum('jumlah');

        $totalSetoranArisan = KasWarga::where('status', 'sudah_bayar')
            ->whereHas('warga', fn($q) => $q->where('role', 'arisan'))
            ->whereBetween('tanggal', [$from, $to])
            ->sum('jumlah');

        $totalPemasukan = $totalPemasukanKas + $totalSetoranArisan;
        $saldo = $totalPemasukan - $totalPengeluaranKas;

        /**
         * 3️⃣ CHART PEMASUKAN VS PENGELUARAN PER BULAN
         */
        $chartKeuangan = KasRT::select(
            DB::raw('DATE_FORMAT(tanggal, "%M %Y") as bulan'),
            DB::raw('SUM(CASE WHEN tipe = "pemasukan" THEN jumlah ELSE 0 END) as total_pemasukan'),
            DB::raw('SUM(CASE WHEN tipe = "pengeluaran" THEN jumlah ELSE 0 END) as total_pengeluaran')
        )
            ->whereBetween('tanggal', [$from, $to])
            ->groupBy('bulan')
            ->orderByRaw('MIN(tanggal)')
            ->get();

        /**
         * 4️⃣ REKAP KAS SEMUA WARGA
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
         * 5️⃣ STATUS ARISAN (PAKAI periode_warga, BUKAN giliran_arisan)
         */
        if ($periode) {
            $data = PeriodeWarga::with('warga')
                ->where('periode_id', $periode->id)
                ->get(['warga_id', 'status']);

            $statusArisan = $data->map(fn($item) => [
                'nama' => $item->warga->nama ?? '-',
                'status' => $item->status ?? 'belum_dapat',
            ]);
        } else {
            $statusArisan = collect([]);
        }

        /**
         * 6️⃣ KEMBALIKAN SEMUA DATA
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
