<?php

namespace App\Http\Controllers;

use App\Models\SampahTransaction;
use Illuminate\Http\Request;

class SampahTransactionController extends Controller
{
    
    public function index(Request $request)
    {
        $query = SampahTransaction::with('admin');

        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        }
        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe);
        }

        $transaksi = $query->orderBy('tanggal', 'asc')->get();

        // Hitung saldo akhir
        $saldo = 0;
        foreach ($transaksi as $trx) {
            $saldo += $trx->tipe === 'pemasukan' ? $trx->jumlah : -$trx->jumlah;
            $trx->saldo_akhir = $saldo; // tidak disimpan, hanya dikirim ke frontend
        }

        return response()->json([
            'message' => 'Data transaksi sampah berhasil diambil',
            'saldo_akhir' => $saldo,
            'data' => $transaksi
        ]);
    }

    // Tambah transaksi baru
    public function store(Request $request)
    {
        $request->validate([
            'tipe' => 'required|in:pemasukan,pengeluaran',
            'jumlah' => 'required|numeric|min:0',
            'keterangan' => 'nullable|string',
            'tanggal' => 'required|date',
        ]);

        $transaksi = SampahTransaction::create([
            'admin_id' => $request->user()->id,
            'tipe' => $request->tipe,
            'jumlah' => $request->jumlah,
            'keterangan' => $request->keterangan,
            'tanggal' => $request->tanggal,
        ]);

        return response()->json([
            'message' => 'Transaksi sampah berhasil ditambahkan',
            'data' => $transaksi
        ], 201);
    }

    // Edit transaksi
    public function update(Request $request, $id)
    {
        $transaksi = SampahTransaction::findOrFail($id);

        $request->validate([
            'tipe' => 'sometimes|in:pemasukan,pengeluaran',
            'jumlah' => 'sometimes|numeric|min:0',
            'keterangan' => 'nullable|string',
            'tanggal' => 'sometimes|date',
        ]);

        $transaksi->update($request->only(['tipe', 'jumlah', 'keterangan', 'tanggal']));

        return response()->json([
            'message' => 'Transaksi sampah berhasil diperbarui',
            'data' => $transaksi
        ]);
    }

    // Hapus transaksi
    public function destroy($id)
    {
        $transaksi = SampahTransaction::findOrFail($id);
        $transaksi->delete();

        return response()->json(['message' => 'Transaksi sampah berhasil dihapus']);
    }
}
