<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/register
     * (Opsional untuk setup awal)
     */
    public function register(Request $request)
    {
        $fields = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admin,email',
            'password' => 'required|min:6|confirmed',
        ]);

        $admin = Admin::create([
            'name' => $fields['name'],
            'email' => $fields['email'],
            'password' => Hash::make($fields['password']),
        ]);

        // Opsional: Generate token setelah register
        $token = $admin->createToken('admin_token')->plainTextToken;

        return response()->json([
            'message' => 'Admin berhasil didaftarkan',
            'data' => $admin,
            // Kembalikan token agar admin bisa langsung login
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * POST /api/login
     * Autentikasi admin dan kembalikan Bearer Token di body response.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email|exists:admin,email',
            'password' => 'required',
        ]);

        $admin = Admin::where('email', $credentials['email'])->first();

        if (!$admin || !Hash::check($credentials['password'], $admin->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        // Hapus token lama dan buat token baru untuk sesi saat ini (opsional tapi disarankan)
        $admin->tokens()->delete();

        // Buat token Sanctum
        $token = $admin->createToken('admin_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil',
            'admin' => $admin,
            // Kembalikan token di body response
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
        // Hilangkan: ->withCookie($cookie);
    }

    /**
     * POST /api/logout
     * Hapus semua token login.
     */
    public function logout(Request $request)
    {
        // Hapus token yang sedang digunakan (current token)
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil'
        ]);
        // Hilangkan: $cookie = cookie()->forget('laravel_token');
        // Hilangkan: ->withCookie($cookie);
    }

    /**
     * GET /api/profile
     * Ambil data admin yang sedang login
     */
    public function profile(Request $request)
    {
        return response()->json([
            'message' => 'Data profil berhasil diambil',
            'data' => $request->user()
        ], 200);
    }

    /**
     * PUT /api/profile
     * Update name & email admin login
     */
    public function updateProfile(Request $request)
    {
        $admin = $request->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admin,email,' . $admin->id,
        ]);

        $admin->update($request->only(['name', 'email']));

        return response()->json([
            'message' => 'Profil berhasil diperbarui',
            'data' => $admin
        ]);
    }

    /**
     * POST /api/password/change
     * Ganti password admin login
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required',
            'password' => 'required|confirmed|min:6',
        ]);

        $admin = $request->user();

        if (!Hash::check($request->old_password, $admin->password)) {
            return response()->json(['message' => 'Password lama tidak sesuai'], 422);
        }

        $admin->update([
            'password' => Hash::make($request->password),
        ]);

        // Hapus semua token setelah password diubah untuk memaksa login ulang
        $admin->tokens()->delete();

        return response()->json([
            'message' => 'Password berhasil diubah. Anda harus login kembali.'
        ]);
    }
}
