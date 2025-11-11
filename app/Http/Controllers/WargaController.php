<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\GiliranArisan;
use App\Models\Periode;
use App\Models\Warga;
use Illuminate\Http\Request;

class WargaController extends Controller
{
    public function index(Request $request)
    {
        // Ambil periode terbaru
        $periodeTerbaru = Periode::latest('id')->first();

        // Query dasar
        $query = Warga::with(['admin', 'giliranArisan'])
            ->withSum(['kasTransaction as setoran_kas' => function ($q) {
                $q->where('status', 'sudah_bayar');
            }], 'jumlah')
            ->with(['arisanTransaction' => function ($q) use ($periodeTerbaru) {
                if ($periodeTerbaru) {
                    $q->where('periode_id', $periodeTerbaru->id);
                }
                $q->where('status', 'sudah_bayar')->with('periode');
            }]);

        // Filter RT
        if ($request->filled('rt') && $request->rt !== 'semua') {
            $query->where('rt', $request->rt);
        }

        // Filter role
        if ($request->filled('role') && $request->role !== 'semua') {
            $query->where('role', $request->role);
        }

        // Filter nama (pencarian)
        if ($request->filled('q')) {
            $query->where('nama', 'like', '%' . $request->q . '%');
        }

        // Filter total setoran kas
        if ($request->filled('kas_min')) {
            $query->having('setoran_kas', '>=', $request->kas_min);
        }
        if ($request->filled('kas_max')) {
            $query->having('setoran_kas', '<=', $request->kas_max);
        }

        // Filter arisan
        if (
            $request->filled('arisan_min') ||
            $request->filled('arisan_max') ||
            $request->filled('arisan_status')
        ) {
            $query->whereHas('arisanTransaction', function ($q) use ($request, $periodeTerbaru) {
                if ($periodeTerbaru) {
                    $q->where('periode_id', $periodeTerbaru->id);
                }

                if ($request->filled('arisan_status') && $request->arisan_status !== 'semua') {
                    $q->where('status', $request->arisan_status);
                }

                if ($request->filled('arisan_min')) {
                    $q->where('jumlah', '>=', $request->arisan_min);
                }

                if ($request->filled('arisan_max')) {
                    $q->where('jumlah', '<=', $request->arisan_max);
                }
            });
        }

        // Tentukan jumlah item per halaman (default 10)
        $perPage = $request->get('per_page', 10);

        // Ambil hasil dengan pagination
        $warga = $query->latest()->paginate($perPage);

        // Transformasi hasil tiap item
        $warga->getCollection()->transform(function ($item) {
            $item->setoran_arisan = $item->arisanTransaction->sum('jumlah') ?? 0;
            $item->setoran_kas = $item->setoran_kas ?? 0;
            return $item;
        });

        // Response JSON lengkap dengan data pagination
        return response()->json([
            'message' => 'Data warga berhasil diambil',
            'filter' => [
                'rt' => $request->rt ?? 'semua',
                'role' => $request->role ?? 'semua',
                'q' => $request->q ?? null,
                'kas_min' => $request->kas_min ?? null,
                'kas_max' => $request->kas_max ?? null,
                'arisan_min' => $request->arisan_min ?? null,
                'arisan_max' => $request->arisan_max ?? null,
                'arisan_status' => $request->arisan_status ?? 'semua',
            ],
            'pagination' => [
                'current_page' => $warga->currentPage(),
                'per_page' => $warga->perPage(),
                'total' => $warga->total(),
                'last_page' => $warga->lastPage(),
            ],
            'data' => $warga->items(),
        ], 200);
    }



    public function storeKas(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'alamat' => 'required|string',
            'tanggal_lahir' => 'required|date',
            'rt' => 'required|string',
        ]);

        $warga = Warga::create([
            'admin_id' => $request->user()->id,
            'nama' => $validated['nama'],
            'alamat' => $validated['alamat'],
            'tanggal_lahir' => $validated['tanggal_lahir'],
            'rt' => $validated['rt'],
            'tipe_warga' => 'kas',
        ]);

        return response()->json($warga, 201);
    }

    public function storeArisan(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'alamat' => 'required|string',
            'tanggal_lahir' => 'required|date',
            'rt' => 'required|string',
        ]);

        $warga = Warga::create([
            'admin_id' => $request->user()->id,
            'nama' => $validated['nama'],
            'alamat' => $validated['alamat'],
            'tanggal_lahir' => $validated['tanggal_lahir'],
            'rt' => $validated['rt'],
            'tipe_warga' => 'arisan',
        ]);

        $periodes = Periode::all();

        foreach ($periodes as $periode) {
            GiliranArisan::firstOrCreate([
                'warga_id' => $warga->id,
                'periode_id' => $periode->id,
            ], [
                'admin_id' => $request->user()->id,
                'status' => 'belum_dapat',
                'tanggal_dapat' => null,
            ]);
        }

        return response()->json($warga, 201);
    }

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

        return response()->json(['message' => 'Warga berhasil dihapus']);
    }
}
