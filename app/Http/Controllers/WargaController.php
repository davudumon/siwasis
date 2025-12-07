<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Periode;
use App\Models\PeriodeWarga;
use App\Models\Warga;
use Illuminate\Http\Request;

class WargaController extends Controller
{
    public function index(Request $request)
    {
        // 🔹 Ambil periode dari parameter atau fallback ke periode terbaru
        $periodeId = $request->get('periode_id');
        $periode = $periodeId ? Periode::find($periodeId) : Periode::latest('id')->first();

        // 🔥 Jika user ngasih periode tapi tidak ditemukan
        if ($periodeId && !$periode) {
            return response()->json([
                'message' => 'Periode tidak ditemukan'
            ], 404);
        }

        // 🔹 Query dasar warga
        $query = Warga::with(['admin'])
            ->withSum(['kasTransaction as setoran_kas' => function ($q) {
                $q->where('status', 'sudah_bayar');
            }], 'jumlah')
            ->with(['arisanTransaction' => function ($q) use ($periode) {
                if ($periode) {
                    $q->where('periode_id', $periode->id);
                }
                $q->where('status', 'sudah_bayar')->with('periode');
            }]);

        // 🔹 Join ke periode_warga untuk ambil status_arisan
        if ($periode) {
            $query->leftJoin('periode_warga', function ($join) use ($periode) {
                $join->on('periode_warga.warga_id', '=', 'warga.id')
                    ->where('periode_warga.periode_id', '=', $periode->id);
            })->addSelect('warga.*', 'periode_warga.status_arisan');
        }

        // 🔹 Filter RT
        if ($request->filled('rt') && $request->rt !== 'semua') {
            $query->where('warga.rt', $request->rt);
        }

        // 🔹 Filter role
        if ($request->filled('role') && $request->role !== 'semua') {
            $query->where('warga.role', $request->role);
        }

        // 🔹 Filter nama (pencarian)
        if ($request->filled('q')) {
            $query->where('warga.nama', 'like', '%' . $request->q . '%');
        }

        // 🔹 Filter kas
        if ($request->filled('kas_min')) {
            $query->having('setoran_kas', '>=', $request->kas_min);
        }
        if ($request->filled('kas_max')) {
            $query->having('setoran_kas', '<=', $request->kas_max);
        }

        // 🔹 Filter status arisan
        if ($request->filled('arisan_status') && $request->arisan_status !== 'semua') {
            $query->where('periode_warga.status_arisan', $request->arisan_status);
        }

        // 🔹 Pagination jumlah per halaman (default 10)
        $perPage = $request->get('per_page', 10);

        // 🔹 Ambil hasil dengan pagination
        // Laravel otomatis mempertahankan semua query parameter (periode, role, rt, dll.)
        $warga = $query->orderBy('warga.id', 'desc')->paginate($perPage);

        // 🔹 Transformasi data
        $warga->getCollection()->transform(function ($item) {
            $item->setoran_arisan = $item->arisanTransaction->sum('jumlah') ?? 0;
            $item->setoran_kas = $item->setoran_kas ?? 0;
            return $item;
        });

        $listRT = Warga::select('rt')->distinct()->pluck('rt');

        // 🔹 Response JSON
        return response()->json([
            'message' => 'Data warga berhasil diambil',
            'periode_aktif' => $periode ? $periode->nama : null,
            'periode_id' => $periode ? $periode->id : null,
            'filter' => [
                'rt' => $request->rt ?? 'semua',
                'role' => $request->role ?? 'semua',
                'q' => $request->q ?? null,
                'kas_min' => $request->kas_min ?? null,
                'kas_max' => $request->kas_max ?? null,
                'arisan_status' => $request->arisan_status ?? 'semua',
            ],
            'list_rt' => $listRT,
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
            'nama'           => 'required|string|max:255',
            'alamat'         => 'required|string',
            'tanggal_lahir'  => 'required|date',
            'rt'             => 'required|string',
            'role'           => 'nullable|in:ketua,wakil_ketua,sekretaris,bendahara,warga',
        ]);

        $warga = Warga::create([
            'admin_id'       => $request->user()->id,
            'nama'           => $validated['nama'],
            'alamat'         => $validated['alamat'],
            'tanggal_lahir'  => $validated['tanggal_lahir'],
            'rt'             => $validated['rt'],
            'role'           => $validated['role'] ?? 'warga',
            'tipe_warga'     => 'kas',
        ]);

        // ✅ Daftarkan ke semua periode yang ada dengan status_arisan = 'tidak_ikut'
        $periodes = Periode::all();
        foreach ($periodes as $periode) {
            \App\Models\PeriodeWarga::firstOrCreate([
                'periode_id' => $periode->id,
                'warga_id' => $warga->id,
            ], [
                'status_arisan' => 'tidak_ikut',
            ]);
        }

        return response()->json([
            'message' => 'Data warga kas berhasil ditambahkan',
            'data' => $warga
        ], 201);
    }
    public function storeArisan(Request $request)
    {
        $validated = $request->validate([
            'nama'           => 'required|string|max:255',
            'alamat'         => 'required|string',
            'tanggal_lahir'  => 'required|date',
            'rt'             => 'required|string',
            'role'           => 'nullable|in:ketua,wakil_ketua,sekretaris,bendahara,warga',
        ]);

        $warga = Warga::create([
            'admin_id'       => $request->user()->id,
            'nama'           => $validated['nama'],
            'alamat'         => $validated['alamat'],
            'tanggal_lahir'  => $validated['tanggal_lahir'],
            'rt'             => $validated['rt'],
            'role'           => $validated['role'] ?? 'warga',
            'tipe_warga'     => 'arisan',
        ]);

        $periodes = Periode::all();

        foreach ($periodes as $periode) {
            PeriodeWarga::firstOrCreate([
                'periode_id' => $periode->id,
                'warga_id' => $warga->id,
            ], [
                'status_arisan' => 'belum_dapat',
            ]);
        }

        return response()->json([
            'message' => 'Data warga arisan berhasil ditambahkan',
            'data' => $warga
        ], 201);
    }


    public function update(Request $request, $id)
    {
        $warga = Warga::findOrFail($id);

        $request->validate([
            'nama'          => 'sometimes|required|string|max:255',
            'alamat'        => 'sometimes|required|string',
            'role'          => 'sometimes|in:ketua,wakil_ketua,sekretaris,bendahara,warga',
            'tanggal_lahir' => 'sometimes|date',
            'rt'            => 'sometimes|required|string',
        ]);

        $warga->update([
            'nama'          => $request->nama ?? $warga->nama,
            'alamat'        => $request->alamat ?? $warga->alamat,
            'role'          => $request->role ?? $warga->role,
            'tanggal_lahir' => $request->tanggal_lahir ?? $warga->tanggal_lahir,
            'rt'            => $request->rt ?? $warga->rt,
        ]);

        return response()->json([
            'message' => 'Data warga berhasil diperbarui',
            'data' => $warga
        ]);
    }

    public function tambahWarga(Request $request)
    {
        $validated = $request->validate([
            'periode_id' => 'required|exists:periode,id',
            'warga_id' => 'required|exists:warga,id',
        ]);

        $periode = Periode::findOrFail($validated['periode_id']);
        $warga = Warga::findOrFail($validated['warga_id']);

        // Tambahkan ke tabel periode_warga
        $periodeWarga = PeriodeWarga::firstOrCreate(
            [
                'periode_id' => $periode->id,
                'warga_id' => $warga->id,
            ],
            [
                // Default status tergantung tipe_warga
                'status_arisan' => $warga->tipe_warga === 'arisan' ? 'belum_dapat' : 'tidak_ikut',
            ]
        );


        return response()->json([
            'message' => 'Warga berhasil ditambahkan ke periode',
            'data' => [
                'periode' => $periode->nama ?? $periode->id,
                'warga' => $warga->nama,
                'periode_warga' => $periodeWarga,
            ]
        ], 201);
    }

    public function getPengurus()
    {
        $pengurus = Warga::whereIn('role', [
            'ketua',
            'wakil_ketua',
            'sekretaris',
            'bendahara',
        ])->get();

        if ($pengurus->isEmpty()) {
            return response()->json([
                'message' => 'Data pengurus tidak ditemukan',
                'data' => [],
            ], 404);
        }

        return response()->json([
            'message' => 'Data pengurus berhasil diambil',
            'data' => $pengurus,
        ]);
    }



    public function destroy($id)
    {
        $warga = Warga::findOrFail($id);
        $warga->delete();

        return response()->json(['message' => 'Warga berhasil dihapus']);
    }
}
