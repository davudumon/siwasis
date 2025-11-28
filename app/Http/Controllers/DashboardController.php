<?php

namespace App\Http\Controllers;

use App\Models\JimpitanTransaction;
use App\Models\KasRT;
use App\Models\KasWarga;
use App\Models\Periode;
use App\Models\PeriodeWarga;
use App\Models\SampahTransaction;
use App\Models\Warga; // 👈 ASUMSI MODEL
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon; // Digunakan untuk penentuan tanggal

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        /**
         * 1️⃣ Tentukan periode otomatis jika tidak dikirim
         */
        $periode = null;
        if ($request->filled('periode_id')) {
            $periode = Periode::find($request->periode_id);
        }
        if (!$periode) {
            $periode = Periode::latest()->first();
        }

        // Tentukan rentang tanggal
        $from = $request->from ? Carbon::parse($request->from)->startOfDay() : ($periode?->tanggal_mulai ? Carbon::parse($periode->tanggal_mulai)->startOfDay() : now()->startOfYear());
        $to   = $request->to ? Carbon::parse($request->to)->endOfDay() : ($periode?->tanggal_selesai ? Carbon::parse($periode->tanggal_selesai)->endOfDay() : now()->endOfYear());

        /**
         * 2️⃣ RINGKASAN KEUANGAN
         */

        // --- KAS RT (Pemasukan & Pengeluaran) ---
        $totalPemasukanKasRT = KasRT::where('tipe', 'pemasukan')
            ->whereBetween('tanggal', [$from, $to])
            ->sum('jumlah');

        $totalPengeluaranKasRT = KasRT::where('tipe', 'pengeluaran')
            ->whereBetween('tanggal', [$from, $to])
            ->sum('jumlah');

        // --- KAS WARGA (HANYA Pemasukan dari iuran/setoran - asumsi 'sudah_bayar' adalah pemasukan) ---
        $totalPemasukanKasWarga = KasWarga::where('status', 'sudah_bayar')
            ->whereDoesntHave('warga', fn($q) => $q->where('role', 'arisan')) // Exclude Arisan setoran
            ->whereBetween('tanggal', [$from, $to])
            ->sum('jumlah');

        // --- ARISAN (HANYA Pemasukan dari setoran/iuran - asumsi role 'arisan' dan status 'sudah_bayar') ---
        $totalSetoranArisan = KasWarga::where('status', 'sudah_bayar')
            ->whereHas('warga', fn($q) => $q->where('role', 'arisan'))
            ->whereBetween('tanggal', [$from, $to])
            ->sum('jumlah');

        // --- SAMPAH (Pemasukan & Pengeluaran) ---
        // 2️⃣ Asumsi perhitungan untuk Sampah
        $totalPemasukanSampah = SampahTransaction::where('tipe', 'pemasukan')
            ->whereBetween('tanggal', [$from, $to])
            ->sum('jumlah');

        $totalPengeluaranSampah = SampahTransaction::where('tipe', 'pengeluaran')
            ->whereBetween('tanggal', [$from, $to])
            ->sum('jumlah');

        // --- JIMPITAN (Pemasukan & Pengeluaran) ---
        // 2️⃣ Asumsi perhitungan untuk Jimpitan
        $totalPemasukanJimpitan = JimpitanTransaction::where('tipe', 'pemasukan')
            ->whereBetween('tanggal', [$from, $to])
            ->sum('jumlah');

        $totalPengeluaranJimpitan = JimpitanTransaction::where('tipe', 'pengeluaran')
            ->whereBetween('tanggal', [$from, $to])
            ->sum('jumlah');

        // --- PERHITUNGAN TOTAL ---
        $totalPemasukan = $totalPemasukanKasRT + $totalPemasukanKasWarga + $totalSetoranArisan + $totalPemasukanSampah + $totalPemasukanJimpitan;
        $totalPengeluaran = $totalPengeluaranKasRT + $totalPengeluaranSampah + $totalPengeluaranJimpitan; // KasWarga dan Arisan tidak memiliki Pengeluaran di sini (diasumsikan pengeluaran Arisan/Kas Warga diwakili oleh KasRT/lainnya)

        $saldoAkhir = $totalPemasukan - $totalPengeluaran; // 👈 Saldo Akhir yang DITAMBAHKAN

        /**
         * 3️⃣ CHART PEMASUKAN VS PENGELUARAN PER BULAN (Hanya Kas RT - Jika ingin gabungan, perlu kueri yang lebih kompleks)
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
            
        // Catatan: Jika ingin chart yang menggabungkan SEMUA transaksi (KasRT, Sampah, Jimpitan), kueri harus menggunakan UNION ALL pada semua tabel transaksi sebelum melakukan GROUP BY.

        /**
         * 4️⃣ REKAP KAS SEMUA WARGA (Tidak diubah, tetap fokus pada Kas Warga/Arisan)
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
         * 5️⃣ STATUS ARISAN (Tidak diubah)
         */
        if ($periode) {
            $data = PeriodeWarga::with('warga')
                ->where('periode_id', $periode->id)
                ->get(['warga_id', 'status_arisan']);

            $statusArisan = $data->map(fn($item) => [
                'nama' => $item->warga->nama ?? '-',
                'status_arisan' => $item->status_arisan ?? 'belum_dapat',
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
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
                'nama' => $periode?->nama,
            ],
            'data' => [
                'kas_total' => [
                    'pemasukan_kas_rt' => (int) $totalPemasukanKasRT,
                    'pengeluaran_kas_rt' => (int) $totalPengeluaranKasRT,
                    'pemasukan_kas_warga' => (int) $totalPemasukanKasWarga,
                    'pemasukan_arisan' => (int) $totalSetoranArisan,
                    'pemasukan_sampah' => (int) $totalPemasukanSampah,
                    'pengeluaran_sampah' => (int) $totalPengeluaranSampah,
                    'pemasukan_jimpitan' => (int) $totalPemasukanJimpitan,
                    'pengeluaran_jimpitan' => (int) $totalPengeluaranJimpitan,
                    'total_pemasukan_semua' => (int) $totalPemasukan,
                    'total_pengeluaran_semua' => (int) $totalPengeluaran,
                    'saldo_akhir_semua' => (int) $saldoAkhir,
                ],
                'chart_keuangan' => $chartKeuangan,
                'rekap_kas_warga' => $rekapKasWarga,
                'status_arisan' => $statusArisan,
            ]
        ], 200);
    }
}
