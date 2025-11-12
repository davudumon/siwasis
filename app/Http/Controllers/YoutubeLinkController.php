<?php

namespace App\Http\Controllers;

use App\Models\YoutubeLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class YoutubeLinkController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $yt_links = YoutubeLink::with('admin')->latest()->get();

        return response()->json($yt_links, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:225',
            'url'   => [
                'required',
                'url',
                'regex:/^(https?\:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+$/'
            ],
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Upload image jika ada
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('youtube', 'public');
        }

        $yt_link = YoutubeLink::create([
            'admin_id' => $request->user()->id,
            'title'    => $request->input('title'),
            'url'      => $request->input('url'),
            'image'    => $imagePath,
        ]);

        return response()->json([
            'message' => 'Link Youtube berhasil ditambahkan',
            'data'    => $yt_link,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $yt_link = YoutubeLink::findOrFail($id);
        return response()->json([
            'data' => $yt_link
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $yt_link = YoutubeLink::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:225',
            'url'   => [
                'required',
                'url',
                'regex:/^(https?\:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+$/'
            ],
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Jika ada file baru, hapus lama dan simpan baru
        if ($request->hasFile('image')) {
            if ($yt_link->image && Storage::disk('public')->exists($yt_link->image)) {
                Storage::disk('public')->delete($yt_link->image);
            }
            $yt_link->image = $request->file('image')->store('youtube', 'public');
        }

        $yt_link->update([
            'title' => $request->input('title'),
            'url'   => $request->input('url'),
            'image' => $yt_link->image,
        ]);

        return response()->json([
            'message' => 'Link Youtube berhasil diupdate',
            'data' => $yt_link
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $yt_link = YoutubeLink::findOrFail($id);

        // Hapus gambar dari storage jika ada
        if ($yt_link->image && Storage::disk('public')->exists($yt_link->image)) {
            Storage::disk('public')->delete($yt_link->image);
        }

        $yt_link->delete();

        return response()->json([
            'message' => 'Link Youtube berhasil dihapus'
        ], 200);
    }
}
