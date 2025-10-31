<?php

namespace App\Http\Controllers;

use App\Models\ArisanTransaction;
use App\Models\GiliranArisan;
use App\Models\JimpitanTransaction;
use App\Models\KasRt;
use App\Models\KasWarga;
use App\Models\SampahTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Ambil filter dari request
        $tahun = $request->input('tahun');
        $periode = $request->input('periode'); // contoh: "Januari 2025"
        $start = $request->input('start');
        $end = $request->input('end');

        // Tentukan range tanggal
        if ($start && $end) {
            // kalau dikirim dua-duanya
            $startDate = Carbon::parse($start)->startOfDay();
            $endDate = Carbon::parse($end)->endOfDay();
        } elseif ($start && !$end) {
            // kalau hanya start → sampai sekarang
            $startDate = Carbon::parse($start)->startOfDay();
            $endDate = now()->endOfDay();
        } elseif (!$start && $end) {
            // kalau hanya end → dari awal tahun sampai end
            $startDate = now()->startOfYear();
            $endDate = Carbon::parse($end)->endOfDay();
        } elseif ($periode) {
            // kalau kirim periode (misal "Januari 2025")
            try {
                $periodeDate = Carbon::parse('1 ' . $periode);
                $startDate = $periodeDate->copy()->startOfMonth();
                $endDate = $periodeDate->copy()->endOfMonth();
            } catch (\Exception $e) {
                return response()->json(['error' => 'Format periode tidak valid. Gunakan format "Januari 2025".'], 422);
            }
        } elseif ($tahun) {
            // kalau kirim tahun aja
            $startDate = Carbon::create($tahun, 1, 1)->startOfDay();
            $endDate = Carbon::create($tahun, 12, 31)->endOfDay();
        } else {
            // default: tahun ini
            $startDate = now()->startOfYear();
            $endDate = now()->endOfYear();
        }

        // Closure filter tanggal
        $filterTanggal = function ($query) use ($startDate, $endDate) {
            return $query->whereBetween('created_at', [$startDate, $endDate]);
        };

        // Hitung semua total
        $pemasukan_kas_rt = $filterTanggal(KasRt::where('tipe', 'pemasukan'))->sum('jumlah');
        $pengeluaran_kas_rt = $filterTanggal(KasRt::where('tipe', 'pengeluaran'))->sum('jumlah');

        $pemasukan_sampah = $filterTanggal(SampahTransaction::where('tipe', 'pemasukan'))->sum('jumlah');
        $pengeluaran_sampah = $filterTanggal(SampahTransaction::where('tipe', 'pengeluaran'))->sum('jumlah');

        $pemasukan_jimpitan = $filterTanggal(JimpitanTransaction::where('tipe', 'pemasukan'))->sum('jumlah');
        $pengeluaran_jimpitan = $filterTanggal(JimpitanTransaction::where('tipe', 'pengeluaran'))->sum('jumlah');

        $saldo_arisan = $filterTanggal(ArisanTransaction::query())->sum('jumlah');
        $saldo_kas_warga = $filterTanggal(KasWarga::query())->sum('jumlah');

        // Hitung total keseluruhan
        $total_pemasukan = $pemasukan_kas_rt + $pemasukan_sampah + $pemasukan_jimpitan + $saldo_arisan + $saldo_kas_warga;
        $total_pengeluaran = $pengeluaran_kas_rt + $pengeluaran_sampah + $pengeluaran_jimpitan;
        $total_saldo = $total_pemasukan - $total_pengeluaran;


        $data_kas_warga = KasWarga::with('warga')
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->get();
        $data_giliran_arisan = GiliranArisan::with('warga')
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->get();

        return response()->json([
            'filter' => [
                'periode' => $periode,
                'tahun' => $tahun ?? now()->year,
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'total_pemasukan' => $total_pemasukan,
            'total_pengeluaran' => $total_pengeluaran,
            'total_saldo' => $total_saldo,
            'giliran_arisan' => $data_giliran_arisan,
            'kas_warga' => $data_kas_warga
        ]);
    }
}
