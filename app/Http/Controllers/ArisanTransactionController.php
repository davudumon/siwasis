<?php

namespace App\Http\Controllers;

use App\Models\ArisanTransaction;
use App\Models\Warga;
use Illuminate\Console\View\Components\Warn;
use Illuminate\Http\Request;

class ArisanTransactionController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'periode' => 'required|string',
            'tanggal' => 'required|date',
        ]);

        $warga = Warga::all();
        $rekap = [];

        foreach ($warga as $item) {
            $sudahBayar = ArisanTransaction::where('warga_id', $item->id)
                ->where('periode', $request->periode)
                ->where('tanggal', $request->tanggal)
                ->exists();

            $rekap[] = [
                'id' => $item->id,
                'nama' => $item->nama,
                'alamat' => $item->alamat,
                'status_bayar' => $sudahBayar,
            ];
        }

        return response()->json($rekap);
    }


    public function toggle(Request $request)
    {
        $request->validate([
            'warga_id' => 'required|exists:warga,id',
            'periode' => 'required|string',
            'tanggal' => 'required|date',
            'jumlah' => 'required|numeric',
        ]);

        $existing = ArisanTransaction::where([
            'warga_id' => $request->warga_id,
            'periode' => $request->periode,
            'tanggal' => $request->tanggal,
        ])->first();

        if ($existing) {
            $existing->delete();
            $status = 'dibatalkan';
        } else {
            ArisanTransaction::create([
                'admin_id' => $request->user()->id,
                'warga_id' => $request->warga_id,
                'periode' => $request->periode,
                'tanggal' => $request->tanggal,
                'jumlah' => $request->jumlah,
            ]);
            
            $status = 'ditandai sudah bayar';
        }

        return response()->json(['message' => "Pembayaran berhasil $status"]);
    }
}
