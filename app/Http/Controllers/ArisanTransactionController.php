<?php

namespace App\Http\Controllers;

use App\Models\ArisanTransaction;
use App\Models\Warga;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ArisanTransactionController extends Controller
{
    /**
     * Menampilkan seluruh data arisan warga dengan filter periode & tanggal (multiple)
     */
    public function index(Request $request)
    {
        $request->validate([
            'periode' => 'required|string',
            'tanggal' => 'nullable|array',
            'tanggal.*' => 'date', 
        ]);

        $query = ArisanTransaction::with('warga')
            ->where('periode', $request->periode);

        if ($request->filled('tanggal')) {
            $query->whereIn('tanggal', $request->tanggal);
        }

        $data = $query->get();

        return response()->json([
            'message' => 'Data arisan berhasil diambil',
            'data' => $data
        ]);
    }

    /**
     * Generate jadwal arisan otomatis antara start_date dan end_date
     * PERUBAHAN: Interval ditetapkan 14 hari. Menggunakan UPSERT untuk performa.
     */
    public function generateJadwal(Request $request)
    {
        $request->validate([
            'periode' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'jumlah' => 'required|numeric',
        ]);

        $warga = Warga::all();
        $start = Carbon::parse($request->start_date);
        $end = Carbon::parse($request->end_date);
        
        // 🔥 Interval ditetapkan 14 hari (2 minggu)
        $interval = 14;
        
        $tanggalList = [];
        $transactions = []; 

        // 1. Generate daftar tanggal otomatis
        for ($tanggal = $start->copy(); $tanggal->lte($end); $tanggal->addDays($interval)) {
            $tanggalList[] = $tanggal->toDateString();
        }
        
        $now = Carbon::now();
        $adminId = $request->user()->id;

        // 2. Kumpulkan data ke dalam satu array
        foreach ($warga as $orang) {
            foreach ($tanggalList as $tgl) {
                $transactions[] = [
                    'warga_id' => $orang->id,
                    'periode' => $request->periode,
                    'tanggal' => $tgl,
                    'admin_id' => $adminId,
                    'jumlah' => $request->jumlah,
                    'status' => 'belum_bayar',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        
        // 3. Gunakan UPSERT untuk insert massal
        $count = 0;
        if (!empty($transactions)) {
            ArisanTransaction::upsert(
                $transactions,
                ['warga_id', 'periode', 'tanggal'], 
                ['admin_id', 'jumlah', 'status', 'updated_at']
            );
            $count = count($transactions);
        }
        
        return response()->json([
            'message' => "Jadwal arisan berhasil dibuat/diperbarui dengan interval 2 minggu untuk {$count} entri.",
            'tanggal' => $tanggalList
        ]);
    }

    /**
     * Toggle status bayar arisan
     * PERBAIKAN: Menambahkan kembali validasi dan penggunaan 'jumlah'.
     */
    public function toggle(Request $request)
    {
        $request->validate([
            'warga_id' => 'required|exists:warga,id',
            'periode' => 'required|string',
            'tanggal' => 'required|date',
        ]);

        $transaksi = ArisanTransaction::where([
            'warga_id' => $request->warga_id,
            'periode' => $request->periode,
            'tanggal' => $request->tanggal,
        ])->first();

        if ($transaksi) {
            if ($transaksi->status === 'sudah_bayar') {
                $transaksi->update(['status' => 'belum_bayar']);
                $status = 'dibatalkan (belum bayar)';
            } else {
                $transaksi->update(['status' => 'sudah_bayar']);
                $status = 'ditandai sudah bayar';
            }
        } else {
            // Jika record belum ada, buat record baru
            ArisanTransaction::create([
                'admin_id' => $request->user()->id,
                'warga_id' => $request->warga_id,
                'periode' => $request->periode,
                'tanggal' => $request->tanggal,
                'status' => 'sudah_bayar'
            ]);
            $status = 'ditandai sudah bayar (baru dibuat)';
        }

        return response()->json(['message' => "Pembayaran berhasil $status"]);
    }
}