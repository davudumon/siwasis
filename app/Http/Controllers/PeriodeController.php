<?php

namespace App\Http\Controllers;

use App\Models\ArisanTransaction;
use App\Models\Periode;
use App\Models\Warga;
use App\Models\KasWarga;
use App\Models\PeriodeWarga;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PeriodeController extends Controller
{
    // 🔹 Ambil semua periode
    public function index()
    {
        $periodes = Periode::latest()->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar semua periode',
            'data' => $periodes,
        ]);
    }

    // 🔹 Buat periode baru
    public function store(Request $request)
    {
        $request->validate([
            'nama'             => 'required|string|max:255|unique:periode,nama',
            'nominal'          => 'required|numeric|min:0',
            'tanggal_mulai'    => 'required|date',
            'tanggal_selesai'  => 'required|date|after:tanggal_mulai',
        ]);

        DB::beginTransaction();

        try {
            // 1️⃣ Buat periode baru
            $periode = Periode::create([
                'nama'            => $request->nama,
                'nominal'         => $request->nominal,
                'tanggal_mulai'   => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
            ]);

            $now = now();
            $adminId = $request->user()->id ?? null;

            // 2️⃣ Ambil semua warga
            $allWarga = Warga::all();
            $wargaArisan = $allWarga->where('tipe_warga', 'arisan');

            // 3️⃣ Tambahkan ke pivot periode_warga (status default: belum_dapat)
            $dataPivot = [];
            foreach ($wargaArisan as $w) {
                $dataPivot[$w->id] = ['status_arisan' => 'belum_dapat'];
            }
            $periode->warga()->attach($dataPivot);

            // 4️⃣ Insert ke KasWarga
            $kasData = [];
            foreach ($allWarga as $w) {
                $kasData[] = [
                    'warga_id'   => $w->id,
                    'periode_id' => $periode->id,
                    'admin_id'   => $adminId,
                    'jumlah'     => $w->tipe_warga === 'arisan' ? $request->nominal : 0,
                    'tanggal'    => $now,
                    'status'     => 'belum_bayar',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            KasWarga::insert($kasData);

            // 5️⃣ Insert ke ArisanTransaction untuk semua warga arisan
            $transaksiData = [];
            foreach ($wargaArisan as $w) {
                $transaksiData[] = [
                    'warga_id'   => $w->id,
                    'periode_id' => $periode->id,
                    'admin_id'   => $adminId,
                    'jumlah'     => $request->nominal,
                    'tanggal'    => $now,
                    'status'     => 'belum_bayar',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            ArisanTransaction::insert($transaksiData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Periode baru berhasil dibuat. Data peserta, kas, dan transaksi arisan sudah disiapkan.',
                'data' => $periode,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat periode. ' . $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    // 🔹 Detail periode
    public function show($id)
    {
        $periode = Periode::with('warga')->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail periode',
            'data' => $periode,
        ]);
    }

    // 🔹 Update periode
    public function update(Request $request, $id)
    {
        $periode = Periode::findOrFail($id);

        $request->validate([
            'nama'            => 'required|string|max:255|unique:periode,nama,' . $periode->id,
            'nominal'         => 'required|numeric|min:0',
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after:tanggal_mulai',
        ]);

        $periode->update([
            'nama'            => $request->nama,
            'nominal'         => $request->nominal,
            'tanggal_mulai'   => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Periode berhasil diperbarui',
            'data' => $periode,
        ]);
    }

    // 🔹 Hapus periode
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $periode = Periode::findOrFail($id);

            // Hapus semua data terkait
            KasWarga::where('periode_id', $id)->delete();
            ArisanTransaction::where('periode_id', $id)->delete();
            PeriodeWarga::where('periode_id', $id)->delete();

            $periode->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Periode dan semua data terkait berhasil dihapus.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus periode. ' . $e->getMessage(),
            ], 500);
        }
    }
}
