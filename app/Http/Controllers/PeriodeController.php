<?php

namespace App\Http\Controllers;

use App\Models\ArisanTransaction;
use App\Models\Periode;
use App\Models\Warga;
use App\Models\KasWarga;
// Use PeriodeWarga model if we needed to create its entry directly, 
// but we will use the attach() method for better Eloquent integration.
use App\Models\GiliranArisan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // Digunakan untuk transaksi

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
            $periode = Periode::create([
                'nama'            => $request->nama,
                'nominal'         => $request->nominal,
                'tanggal_mulai'   => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
            ]);

            $now = now();
            $adminId = $request->user()->id ?? null;

            // Ambil semua warga dan warga arisan
            $allWarga = Warga::all();
            $wargaArisan = $allWarga->where('tipe_warga', 'arisan');

            // 1️⃣ Tambahkan ke pivot periode_warga
            $dataPivot = [];
            foreach ($wargaArisan as $w) {
                $dataPivot[$w->id] = ['status_arisan' => 'belum_dapat'];
            }
            $periode->warga()->attach($dataPivot);

            // 2️⃣ Insert KasWarga untuk semua warga
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

            // Insert GiliranArisan hanya untuk warga arisan
            $giliranData = [];
            foreach ($wargaArisan as $w) {
                $giliranData[] = [
                    'warga_id'   => $w->id,
                    'periode_id' => $periode->id,
                    'admin_id'   => $adminId,
                    'status'     => 'belum_dapat',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            GiliranArisan::insert($giliranData);

            $transaksiData = [];
            foreach ($wargaArisan as $w) {
                $transaksiData[] = [
                    'warga_id'   => $w->id,
                    'periode_id' => $periode->id,
                    'admin_id'   => $adminId,
                    'jumlah'     => $request->nominal, // nominal arisan per periode
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
                'message' => 'Periode baru berhasil dibuat, peserta, kas, dan giliran awal telah disiapkan.',
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
    // Detail periode
    public function show($id)
    {
        // Tambahkan with(['warga']) untuk memuat peserta saat detail diakses
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

        // Standarisasi validasi sesuai dengan kolom yang ada di Model Periode Anda
        $request->validate([
            'nama'    => 'required|string|max:255|unique:periode,nama,' . $periode->id,
            'nominal'         => 'required|numeric|min:0',
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after:tanggal_mulai',
        ]);

        $periode->update([
            'nama'    => $request->nama,
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

            // ⚠️ PENTING: Hapus semua data terkait di tabel pivot dan tabel transaksi lainnya
            // Laravel secara otomatis menghapus relasi Many-to-Many di tabel 'periode_warga' 
            // jika relasi di Periode diatur. Namun, untuk KasWarga dan GiliranArisan, kita hapus manual.

            // Hapus entri KasWarga yang terkait
            KasWarga::where('periode_id', $id)->delete();

            // Hapus entri GiliranArisan yang terkait
            GiliranArisan::where('periode_id', $id)->delete();

            // Hapus Periode
            $periode->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Periode dan semua data transaksi terkait berhasil dihapus',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus periode.',
            ], 500);
        }
    }
}
