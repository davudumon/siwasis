<?php

namespace App\Http\Controllers;

use App\Models\Warga;
use Illuminate\Http\Request;

class WargaController extends Controller
{
    public function index()
    {
        return response()->json(Warga::all());
    }

    // tambah data warga
    public function store(Request $request)
    {
        $request->validate([
            'nama'          => 'required|string|max:255',
            'alamat'        => 'required|string',
            'telepon'       => 'required|string|max:20',
            'tanggal_lahir' => 'required|date',
        ]);

        $warga = Warga::create($request->all());

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
            'telepon'       => 'sometimes|required|string|max:20',
            'tanggal_lahir' => 'sometimes|date',
        ]);

        $warga->update($request->all());

        return response()->json($warga);
    }

    public function destroy($id)
    {
        $warga = Warga::findOrFail($id);
        $warga->delete();

        return response()->json(['message' => 'Warga deleted']);
    }
}
