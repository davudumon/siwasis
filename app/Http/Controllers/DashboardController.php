<?php

namespace App\Http\Controllers;

use App\Models\JimpitanTransaction;
use App\Models\KasRT;
use App\Models\KasWarga;
use App\Models\Periode;
use App\Models\PeriodeWarga;
use App\Models\SampahTransaction;
use App\Models\Warga;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
            ->whereDoesntHave('warga', fn($q) => $q->where('role', 'arisan')) 
            ->whereBetween('tanggal', [$from, $to])
            ->sum('jumlah');

        // --- ARISAN (HANYA Pemasukan dari setoran/iuran) ---
        $totalSetoranArisan = KasWarga::where('status', 'sudah_bayar')
            ->whereHas('warga', fn($q) => $q->where('role', 'arisan'))
            ->whereBetween('tanggal', [$from, $to])
            ->sum('jumlah');

        // --- SAMPAH (Pemasukan & Pengeluaran) ---
        $totalPemasukanSampah = SampahTransaction::where('tipe', 'pemasukan')
            ->whereBetween('tanggal', [$from, $to])
            ->sum('jumlah');

        $totalPengeluaranSampah = SampahTransaction::where('tipe', 'pengeluaran')
            ->whereBetween('tanggal', [$from, $to])
            ->sum('jumlah');

        // --- JIMPITAN (Pemasukan & Pengeluaran) ---
        $totalPemasukanJimpitan = JimpitanTransaction::where('tipe', 'pemasukan')
            ->whereBetween('tanggal', [$from, $to])
            ->sum('jumlah');

        $totalPengeluaranJimpitan = JimpitanTransaction::where('tipe', 'pengeluaran')
            ->whereBetween('tanggal', [$from, $to])
            ->sum('jumlah');

        // --- PERHITUNGAN TOTAL ---
        $totalPemasukan = $totalPemasukanKasRT + $totalPemasukanKasWarga + $totalSetoranArisan + $totalPemasukanSampah + $totalPemasukanJimpitan;
        $totalPengeluaran = $totalPengeluaranKasRT + $totalPengeluaranSampah + $totalPengeluaranJimpitan; 

        $saldoAkhir = $totalPemasukan - $totalPengeluaran;

        // --- KAS WARGA DAN ARISAN UNTUK CHART ---
        // Dipetakan agar memiliki kolom 'tipe' untuk di-merge
        $kasWargaChart = KasWarga::select('tanggal', 'jumlah')
            ->where('status', 'sudah_bayar')
            ->whereBetween('tanggal', [$from, $to])
            ->get()
            ->map(function ($item) {
                return [
                    'tanggal' => $item->tanggal,
                    'tipe' => 'pemasukan',
                    'jumlah' => $item->jumlah,
                ];
            });

        /**
         * 3️⃣ CHART PEMASUKAN VS PENGELUARAN PER BULAN (GABUNGAN SEMUA)
         */
        
        // 1. Ambil data dari KasRT, Sampah, dan Jimpitan (sudah punya kolom tipe)
        $kasRTQuery = DB::table('kas_rt')
            ->select('tanggal', 'tipe', 'jumlah')
            ->whereBetween('tanggal', [$from, $to]);

        $sampahQuery = DB::table('sampah_transactions')
            ->select('tanggal', 'tipe', 'jumlah')
            ->whereBetween('tanggal', [$from, $to]);

        $jimpitanQuery = DB::table('jimpitan_transactions')
            ->select('tanggal', 'tipe', 'jumlah')
            ->whereBetween('tanggal', [$from, $to]);
            
        // 2. Gabungkan data KasRT, Sampah, dan Jimpitan
        $allTransactions = $kasRTQuery
            ->unionAll($sampahQuery)
            ->unionAll($jimpitanQuery)
            ->get();
            
        // 3. Tambahkan data KasWarga (Arisan + Non-Arisan)
        $allTransactions = $allTransactions->merge($kasWargaChart);

        // 4. Lakukan pengelompokan (grouping) dan agregasi di sisi PHP
        $chartDataGrouped = $allTransactions
            ->groupBy(function ($item) {
                // PERBAIKAN: Menggunakan is_object() untuk memilih cara akses yang benar (objek/array)
                $tanggal = is_object($item) ? $item->tanggal : $item['tanggal'];
                return Carbon::parse($tanggal)->isoFormat('MMMM YYYY');
            })
            ->map(function ($transactions, $bulan) {
                // Gunakan custom sum closure untuk menangani campuran objek dan array
                
                // Total Pemasukan
                $totalPemasukan = $transactions->sum(function ($transaction) {
                    $tipe = is_object($transaction) ? $transaction->tipe : $transaction['tipe'];
                    $jumlah = is_object($transaction) ? $transaction->jumlah : $transaction['jumlah'];
                    return $tipe === 'pemasukan' ? $jumlah : 0;
                });
                
                // Total Pengeluaran
                $totalPengeluaran = $transactions->sum(function ($transaction) {
                    $tipe = is_object($transaction) ? $transaction->tipe : $transaction['tipe'];
                    $jumlah = is_object($transaction) ? $transaction->jumlah : $transaction['jumlah'];
                    return $tipe === 'pengeluaran' ? $jumlah : 0;
                });
                
                return [
                    'bulan' => $bulan,
                    'total_pemasukan' => (int) $totalPemasukan,
                    'total_pengeluaran' => (int) $totalPengeluaran,
                ];
            });

        // 5. GENERATE SEMUA BULAN DALAM RENTANG WAKTU (termasuk yang bernilai 0)
        $allMonthsData = collect();
        $start = $from->copy()->startOfMonth();
        $end = $to->copy()->endOfMonth();
        
        while ($start->lte($end)) {
            $monthKey = $start->isoFormat('MMMM YYYY');
            
            // Template data bulan
            $monthData = [
                'bulan' => $monthKey,
                'total_pemasukan' => 0,
                'total_pengeluaran' => 0,
            ];
            
            // Gabungkan dengan data transaksi yang sudah dihitung
            if ($chartDataGrouped->has($monthKey)) {
                $monthData = $chartDataGrouped->get($monthKey);
            }
            
            $allMonthsData->push($monthData);
            
            // Pindah ke bulan berikutnya
            $start->addMonthNoOverflow();
        }
        
        $chartKeuangan = $allMonthsData->values();

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
         * 5️⃣ STATUS ARISAN
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