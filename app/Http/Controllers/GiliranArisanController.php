<?php

namespace App\Http\Controllers;

use App\Models\GiliranArisan;
use App\Models\PeriodeWarga;
use App\Models\Warga;
use Illuminate\Http\Request;
use Carbon\Carbon;

class GiliranArisanController extends Controller
{
    /**
     * 🔹 Ambil semua giliran (dengan filter opsional periode)
     */
    public function index(Request $request)
    {
        $query = GiliranArisan::with(['warga', 'periode']);

        if ($request->filled('periode_id')) {
            $query->where('periode_id', $request->periode_id);
        }

        $data = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Data giliran berhasil diambil',
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

        $belumDapat = GiliranArisan::with('warga')
            ->where('periode_id', $request->periode_id)
            ->where('status', 'belum_dapat')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar warga yang belum dapat giliran',
            'data' => $belumDapat,
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

        $giliran = GiliranArisan::where('warga_id', $request->warga_id)
            ->where('periode_id', $request->periode_id)
            ->first();

        if (!$giliran) {
            return response()->json([
                'success' => false,
                'message' => 'Warga belum terdaftar dalam periode ini.',
            ], 404);
        }

        // Update status giliran
        $giliran->update([
            'status' => 'sudah_dapat',
            'tanggal_dapat' => Carbon::now(),
        ]);

        // Sinkron ke tabel periode_warga
        PeriodeWarga::where('warga_id', $request->warga_id)
            ->where('periode_id', $request->periode_id)
            ->update(['status_arisan' => 'sudah_dapat']);

        return response()->json([
            'success' => true,
            'message' => 'Giliran warga berhasil diperbarui dan disinkron ke periode_warga',
            'data' => $giliran,
        ]);
    }
}
