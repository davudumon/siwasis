<?php

namespace App\Http\Controllers;

use App\Models\JimpitanTransaction;
use Illuminate\Http\Request;

class JimpitanTransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = JimpitanTransaction::orderBy('tanggal', 'desc');

        // Optional filter
        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe);
        }

        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        }

        $data = $query->get();

        return response()->json([
            'message' => 'Data transaksi jimpitan berhasil diambil',
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'jumlah' => 'required|numeric',
            'tipe' => 'required|in:pemasukan,pengeluaran',
            'keterangan' => 'required|string',
            'tanggal' => 'required|date',
        ]);

        $data = JimpitanTransaction::create([
            'admin_id' => $request->user()->id,
            'jumlah' => $request->jumlah,
            'tipe' => $request->tipe,
            'keterangan' => $request->keterangan,
            'tanggal' => $request->tanggal,
        ]);

        return response()->json([
            'message' => 'Transaksi jimpitan berhasil ditambahkan',
            'data' => $data
        ]);
    }

    public function show($id)
    {
        $data = JimpitanTransaction::findOrFail($id);
        return response()->json($data);
    }

    public function update(Request $request, $id)
    {
        $data = JimpitanTransaction::findOrFail($id);

        $data->update([
            'jumlah' => $request->jumlah ?? $data->jumlah,
            'tipe' => $request->tipe ?? $data->tipe,
            'keterangan' => $request->keterangan ?? $data->keterangan,
            'tanggal' => $request->tanggal ?? $data->tanggal,
            'admin_id' => $request->user()->id, // admin terakhir yang update
        ]);

        return response()->json([
            'message' => 'Transaksi jimpitan berhasil diperbarui',
            'data' => $data
        ]);
    }

    public function destroy($id)
    {
        $data = JimpitanTransaction::findOrFail($id);
        $data->delete();

        return response()->json(['message' => 'Transaksi jimpitan berhasil dihapus']);
    }
}
