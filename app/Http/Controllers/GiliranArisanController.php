<?php

namespace App\Http\Controllers;

use App\Models\GiliranArisan;
use App\Models\Warga;
use Illuminate\Http\Request;
use Carbon\Carbon;

class GiliranArisanController extends Controller
{
    // Ambil semua giliran
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => GiliranArisan::with('warga')->get()
        ]);
    }

    // Ambil warga yang belum dapat giliran
    public function getBelumDapat(Request $request)
    {
        $request->validate([
            'periode' => 'required|string'
        ]);

        $belum_dapat = GiliranArisan::with('warga')
            ->where('periode', $request->periode)
            ->where('status', 'belum_dapat')
            ->get();

        return response()->json([
            'success' => true,
            'message' => "Daftar warga yang belum dapat giliran untuk periode {$request->periode}",
            'data' => $belum_dapat
        ]);
    }

    // Simpan hasil spinwheel (warga terpilih)
    public function store(Request $request)
    {
        $request->validate([
            'warga_id' => 'required|exists:warga,id',
            'periode' => 'required|string',
        ]);

        $giliran = GiliranArisan::where('warga_id', $request->warga_id)
            ->where('periode', $request->periode)
            ->first();

        if (!$giliran) {
            return response()->json(['success' => false, 'message' => 'Warga tidak ditemukan dalam giliran'], 404);
        }

        $giliran->update([
            'status' => 'sudah_dapat',
            'tanggal_dapat' => Carbon::now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Giliran warga berhasil diperbarui',
            'data' => $giliran,
        ]);
    }

    // Generate giliran baru untuk satu periode
    public function generatePeriode(Request $request)
    {
        $request->validate([
            'periode' => 'required|string'
        ]);

        $existing = GiliranArisan::where('periode', $request->periode)->exists();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => "Periode {$request->periode} sudah ada."
            ], 409);
        }

        $warga_list = Warga::all();

        foreach ($warga_list as $warga) {
            GiliranArisan::create([
                'admin_id' => $request->user()->id,
                'warga_id' => $warga->id,
                'periode' => $request->periode,
                'status' => "belum_dapat",
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data giliran periode baru berhasil dibuat',
            'periode' => $request->periode,
        ], 201);
    }

    // Reset giliran untuk periode tertentu
    public function resetPeriode(Request $request)
    {
        $request->validate([
            'periode' => 'required|string',
        ]);

        GiliranArisan::where('periode', $request->periode)
            ->update([
                'status' => 'belum_dapat',
                'tanggal_dapat' => null,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Giliran periode berhasil direset',
        ]);
    }
}

