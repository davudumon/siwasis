<?php

namespace App\Http\Controllers;

use App\Models\YoutubeLink;
use Illuminate\Http\Request;

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
     * Show the form for creating a new resource.
     */
    public function create() {}

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
        ]);

        $yt_links = YoutubeLink::create([
            'admin_id' => $request->user()->id, // admin login
            'title'    => $request->input('title'),
            'url'      => $request->input('url'),
        ]);

        return response()->json([
            'message' => 'Link Youtube berhasil ditambahkan',
            'data'    => $yt_links,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $yt_links = YoutubeLink::findOrFail($id);
        return response()->json([
            'data' => $yt_links
        ], 201);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $yt_links = YoutubeLink::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:225',
            'url'   => [
                'required',
                'url',
                'regex:/^(https?\:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+$/'
            ],
        ]);

        $yt_links->update($request->all());

        return response()->json([
            'message' => 'Link Youtube berhasil diupdate',
            'data' => $yt_links
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $yt_links = YoutubeLink::findOrFail($id);

        $yt_links->delete();

        return response()->json([
            'message' => 'Link Youtube berhasil dihapus'
        ]);
    }
}
