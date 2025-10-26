<?php

namespace App\Http\Controllers;

use App\Models\KasWarga;
use App\Models\Warga;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class KasWargaController extends Controller
{
    /**
     * GET: Ambil rekap kas warga (bisa difilter berdasarkan periode & tanggal multiple)
     */
    public function index(Request $request)
    {
        $request->validate([
            'periode' => 'nullable|string',
            'tanggal' => 'nullable|array',
            'tanggal.*' => 'date',
        ]);

        $query = KasWarga::with('warga');

        if ($request->filled('periode')) {
            $query->where('periode', $request->periode);
        }

        // Menggunakan whereIn untuk filter multiple choice tanggal
        if ($request->filled('tanggal')) {
            $query->whereIn('tanggal', $request->tanggal);
        }

        $data = $query->get();

        return response()->json([
            'message' => 'Data kas berhasil diambil',
            'data' => $data
        ]);
    }

    /**
     * POST: Generate jadwal kas otomatis (menggunakan upsert untuk performa)
     */
    // ... dalam class KasWargaController

    /**
     * POST: Generate jadwal kas otomatis (dengan interval 14 hari)
     */
    public function generateKasOtomatis(Request $request)
    {
        $request->validate([
            'periode' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'jumlah' => 'required|numeric|min:0',
        ]);

        $start = Carbon::parse($request->start_date);
        $end = Carbon::parse($request->end_date);

        // 🔥 PERUBAHAN UTAMA: Interval ditetapkan 14 hari (2 minggu)
        $interval = 14;

        $tanggal_list = [];
        $transactions = [];
        $current = $start->copy();

        // 1. Generate daftar tanggal
        while ($current->lte($end)) {
            $tanggal_list[] = $current->toDateString();
            // Menggunakan $interval = 14
            $current->addDays($interval);
        }

        $warga_list = Warga::all();
        $now = Carbon::now();
        // Menggunakan ID admin yang sedang login
        $adminId = $request->user()->id;

        // 2. Kumpulkan data ke dalam array
        foreach ($warga_list as $warga) {
            foreach ($tanggal_list as $tanggal) {
                $transactions[] = [
                    'admin_id' => $adminId,
                    'warga_id' => $warga->id,
                    'periode'  => $request->periode,
                    'tanggal'  => $tanggal,
                    'jumlah'   => $request->jumlah,
                    'status'   => 'belum_bayar',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // 3. Gunakan UPSERT untuk insert massal sekaligus mencegah duplikasi
        $count = 0;
        if (!empty($transactions)) {
            KasWarga::upsert( // Pastikan menggunakan namespace model yang benar
                $transactions,
                ['warga_id', 'periode', 'tanggal'],
                ['admin_id', 'jumlah', 'status', 'updated_at']
            );
            $count = count($transactions);
        }

        return response()->json([
            'message' => "Jadwal Kas berhasil dibuat/diperbarui dengan interval 2 minggu untuk {$count} entri.",
            'tanggal_dibuat' => $tanggal_list,
        ], 201);
    }

    // ... fungsi lainnya

    /**
     * PUT: Toggle status bayar kas (mengganti updateStatus)
     */
    public function toggleStatus(Request $request)
    {
        $request->validate([
            'warga_id' => 'required|exists:warga,id',
            'periode' => 'required|string',
            'tanggal' => 'required|date',
        ]);

        $kas = KasWarga::where([
            'warga_id' => $request->warga_id,
            'periode' => $request->periode,
            'tanggal' => $request->tanggal,
        ])->first();

        if ($kas) {
            if ($kas->status === 'sudah_bayar') {
                $kas->update(['status' => 'belum_bayar']);
                $status = 'dibatalkan (belum bayar)';
            } else {
                $kas->update(['status' => 'sudah_bayar']);
                $status = 'ditandai sudah bayar';
            }
        } else {
            KasWarga::create([
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
