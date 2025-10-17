<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Warga;
use Illuminate\Http\Request;

class WargaController extends Controller
{
    public function index()
    {
        $warga = Warga::with('admin')->latest()->get();

        return response()->json($warga, 201);
    }

    // tambah data warga
    public function store(Request $request)
    {
        $request->validate([
            'nama'          => 'required|string|max:255',
            'alamat'        => 'required|string',
            'role'          => 'nullable|in:ketua,wakil_ketua,sekretaris,bendahara,warga',
            'tanggal_lahir' => 'required|date',
        ]);

        $warga = Warga::create([
            'admin_id'      => $request->user()->id,
            'nama'          => $request->nama,
            'alamat'        => $request->alamat,
            'role'          => $request->role ?? 'warga',
            'tanggal_lahir' => $request->tanggal_lahir,
        ]);

        return response()->json($warga, 201);
    }


    // tampilkan detail warga
    public function show($id)
    {
        $warga = Warga::findOrFail($id);
        return response()->json($warga);
    }

    // update data warga
    public function update(Request $request, $id)
    {
        $warga = Warga::findOrFail($id);

        $request->validate([
            'nama'          => 'sometimes|required|string|max:255',
            'alamat'        => 'sometimes|required|string',
            'role'          => 'sometimes|in:ketua,wakil_ketua,sekretaris,bendahara,warga',
            'tanggal_lahir' => 'sometimes|date',
        ]);

        $warga->update([
            'nama'          => $request->nama ?? $warga->nama,
            'alamat'        => $request->alamat ?? $warga->alamat,
            'role'          => $request->role ?? $warga->role,
            'tanggal_lahir' => $request->tanggal_lahir ?? $warga->tanggal_lahir,
        ]);

        return response()->json($warga);
    }

    public function destroy($id)
    {
        $warga = Warga::findOrFail($id);
        $warga->delete();

        return response()->json(['message' => 'Warga deleted']);
    }
}
