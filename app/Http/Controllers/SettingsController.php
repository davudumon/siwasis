<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    /**
     * GET /api/admins
     * Mengambil daftar semua admin
     */
    public function index()
    {
        $admins = Admin::all();
        return response()->json([
            'status' => 'success',
            'data' => $admins
        ], 200);
    }

    /**
     * POST /api/admins
     * Membuat admin baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:admins,email',
            'password' => 'required|string|min:6|confirmed'
        ]);

        $admin = Admin::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Admin berhasil dibuat',
            'data' => $admin
        ], 201);
    }

    /**
     * DELETE /api/admins/me
     * Menghapus admin yang sedang login
     */
    public function destroyMe()
    {
        $admin = Admin::user();

        if (!$admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'Admin tidak ditemukan'
            ], 404);
        }

        $admin->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Admin berhasil dihapus'
        ], 200);
    }
}
