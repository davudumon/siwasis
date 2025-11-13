<?php

namespace App\Http\Controllers;

use App\Models\PeriodeWarga;
use Illuminate\Http\Request;
use Carbon\Carbon;

class GiliranArisanController extends Controller
{
    /**
     * 🔹 Ambil semua giliran (dengan filter opsional periode)
     */
    public function index(Request $request)
    {
        $query = PeriodeWarga::with(['warga', 'periode']);

        if ($request->filled('periode_id')) {
            $query->where('periode_id', $request->periode_id);
        }

        $data = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Data giliran arisan berhasil diambil (dari periode_warga)',
            'data' => $data,
        ]);
    }


    /**
     * 🔹 Ambil daftar warga yang belum dapat giliran dalam periode tertentu
     */
    public function getBelumDapat(Request $request)
    {
        $request->validate([
            'periode_id' => 'required|exists:periode,id',
        ]);

        // Ambil warga dari periode_warga yang belum dapat giliran
        $belumDapat = PeriodeWarga::with('warga')
            ->where('periode_id', $request->periode_id)
            ->where('status_arisan', 'belum_dapat')
            ->get();

        // FE spinwheel biasa pakai field 'warga', jadi kita sesuaikan response-nya
        $formatted = $belumDapat->map(function ($item) {
            return [
                'id' => $item->warga->id,
                'nama' => $item->warga->nama,
                'tipe_warga' => $item->warga->tipe_warga,
                'status_arisan' => $item->status_arisan,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar warga yang belum dapat giliran (berdasarkan periode_warga)',
            'data' => $formatted,
        ]);
    }


    /**
     * 🔹 Tandai warga sudah dapat giliran (hasil spin)
     */
    public function store(Request $request)
    {
        $request->validate([
            'warga_id' => 'required|exists:warga,id',
            'periode_id' => 'required|exists:periode,id',
        ]);

        $periodeWarga = PeriodeWarga::where('warga_id', $request->warga_id)
            ->where('periode_id', $request->periode_id)
            ->first();

        if (!$periodeWarga) {
            return response()->json([
                'success' => false,
                'message' => 'Warga belum terdaftar dalam periode ini.',
            ], 404);
        }

        $periodeWarga->update([
            'status_arisan' => 'sudah_dapat',
            'tanggal_dapat' => Carbon::now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status arisan warga berhasil diperbarui.',
            'data' => $periodeWarga->load('warga'),
        ]);
    }
}
