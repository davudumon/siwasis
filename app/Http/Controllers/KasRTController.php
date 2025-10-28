<?php

namespace App\Http\Controllers;

use App\Models\KasRt;
use Illuminate\Http\Request;
use Carbon\Carbon;

class KasRtController extends Controller
{
    // Ambil semua transaksi kas RT
    public function index(Request $request)
    {
        $query = KasRt::query();

        // Filter berdasarkan tanggal jika dikirim
        if ($request->has('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        }

        // Filter tipe: pemasukan / pengeluaran
        if ($request->has('tipe')) {
            $query->where('tipe', $request->tipe);
        }

        $kas = $query->orderBy('tanggal', 'desc')->get();

        return response()->json([
            'message' => 'Data kas RT berhasil diambil',
            'data' => $kas
        ]);
    }

    // Tambah transaksi kas RT baru
    public function store(Request $request)
    {
        $request->validate([
            'tipe' => 'required|in:pemasukan,pengeluaran',
            'jumlah' => 'required|numeric|min:0',
            'keterangan' => 'required|string',
            'tanggal' => 'nullable|date'
        ]);

        $kas = KasRt::create([
            'admin_id' => $request->user()->id,
            'tipe' => $request->tipe,
            'jumlah' => $request->jumlah,
            'keterangan' => $request->keterangan,
            'tanggal' => $request->tanggal,
        ]);

        return response()->json([
            'message' => 'Transaksi kas RT berhasil ditambahkan',
            'data' => $kas
        ], 201);
    }

    // Update transaksi kas RT
    public function update(Request $request, $id)
    {
        $kas = KasRt::findOrFail($id);

        $request->validate([
            'tipe' => 'sometimes|in:pemasukan,pengeluaran',
            'jumlah' => 'sometimes|numeric|min:0',
            'keterangan' => 'sometimes|string',
            'tanggal' => 'sometimes|date'
        ]);

        $kas->update([
            'tipe' => $request->tipe ?? $kas->tipe,
            'jumlah' => $request->jumlah ?? $kas->jumlah,
            'keterangan' => $request->keterangan ?? $kas->keterangan,
            'tanggal' => $request->tanggal ?? $kas->tanggal,
        ]);

        return response()->json([
            'message' => 'Transaksi kas RT berhasil diperbarui',
            'data' => $kas
        ]);
    }

    // Hapus transaksi kas RT
    public function destroy($id)
    {
        $kas = KasRt::findOrFail($id);
        $kas->delete();

        return response()->json(['message' => 'Transaksi kas RT berhasil dihapus']);
    }

    // Ringkasan total kas RT
    public function summary()
    {
        $totalPemasukan = KasRt::where('tipe', 'pemasukan')->sum('jumlah');
        $totalPengeluaran = KasRt::where('tipe', 'pengeluaran')->sum('jumlah');
        $saldo = $totalPemasukan - $totalPengeluaran;

        return response()->json([
            'message' => 'Ringkasan kas RT',
            'data' => [
                'total_pemasukan' => $totalPemasukan,
                'total_pengeluaran' => $totalPengeluaran,
                'saldo' => $saldo,
            ]
        ]);
    }
}
