<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HeroImageController extends Controller
{

    public function index()
    {
        return response()->json([
            'image_url' => asset('storage/' . config('hero.image'))
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120'
        ]);

        // Ambil config hero saat ini
        $hero = include config_path('hero.php');
        $old_path = $hero['image'] ?? null;

        // Hapus file lama jika ada
        if ($old_path && Storage::disk('public')->exists($old_path)) {
            Storage::disk('public')->delete($old_path);
        }

        // Simpan file baru
        $path = $request->file('image')->store('hero', 'public');
        // Hasil contoh: "hero/abc123.jpg"

        // Update config/hero.php
        $content = "<?php\nreturn [\n    'image' => '{$path}',\n];";
        file_put_contents(config_path('hero.php'), $content);

        // Bangun full URL pakai kondisi B
        $fullUrl = config('app.url') . '/storage/' . $path;

        return response()->json([
            'message' => 'Hero Image berhasil diupdate',
            'image_url' => $fullUrl
        ]);
    }
}
