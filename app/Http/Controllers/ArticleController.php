<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ArticleController extends Controller
{
    /**
     * Tampilkan semua artikel
     */
    public function index()
    {
        $articles = Article::with('admin')->latest()->get();
        return response()->json($articles);
    }

    /**
     * Simpan artikel baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'title'   => 'required|string|max:255',
            'content' => 'required',
            'image_path'   => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'published' => 'nullable|date',
        ]);

        $path = null;
        if ($request->hasFile('image_path')) {
            $path = $request->file('image_path')->store('articles', 'public');
        }

        $article = Article::create([
            'admin_id'   => $request->user()->id,
            'title'      => $request->input('title'),
            'content'    => $request->input('content'),
            'image_path' => $path,
            'published'  => $request->input('published', now()),
        ]);

        return response()->json([
            'message' => 'Artikel berhasil dibuat',
            'data' => $article,
        ]);
    }

    /**
     * Tampilkan detail artikel
     */
    public function show($id)
    {
        $article = Article::findOrFail($id);
        return response()->json($article);
    }

    /**
     * Update artikel
     */
    public function update(Request $request, $id)
    {
        $article = Article::findOrFail($id);

        $request->validate([
            'title'   => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required',
            'image'   => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'published' => 'nullable|date',
        ]);

        if ($request->hasFile('image')) {
            
            if ($article->image_path && Storage::disk('public')->exists($article->image_path)) {
                Storage::disk('public')->delete($article->image_path);
            }
            $article->image_path = $request->file('image')->store('articles', 'public');
        }

        $article->update($request->only(['title', 'content', 'published']));

        return response()->json([
            'message' => 'Artikel berhasil diupdate',
            'data' => $article,
        ]);
    }

    /**
     * Hapus artikel
     */
    public function destroy($id)
    {
        $article = Article::findOrFail($id);

        if ($article->image_path && Storage::disk('public')->exists($article->image_path)) {
            Storage::disk('public')->delete($article->image_path);
        }

        $article->delete();

        return response()->json([
            'message' => 'Artikel berhasil dihapus',
        ]);
    }
}
