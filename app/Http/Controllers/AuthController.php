<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/register
     * Mendaftarkan admin baru (opsional setup awal)
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

        $token = $admin->createToken('admin_token')->plainTextToken;

        return response()->json([
            'message' => 'Admin berhasil didaftarkan',
            'data' => $admin,
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * POST /api/login
     * Login dan kembalikan Bearer Token
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

        // Hapus token lama dan buat token baru
        $admin->tokens()->delete();
        $token = $admin->createToken('admin_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil',
            'admin' => $admin,
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * POST /api/logout
     * Menghapus token aktif (logout)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil'
        ]);
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
     * Update name, email, dan foto profil admin login
     */
    public function updateProfile(Request $request)
    {
        $admin = $request->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admin,email,' . $admin->id,
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048', // max 2MB
        ]);

        // Update nama dan email
        $admin->name = $request->name;
        $admin->email = $request->email;

        // Jika ada upload foto baru
        if ($request->hasFile('photo')) {
            // Hapus foto lama jika ada
            if ($admin->photo && Storage::exists('public/profile/' . $admin->photo)) {
                Storage::delete('public/profile/' . $admin->photo);
            }

            // Simpan foto baru
            $path = $request->file('photo')->store('public/profile');
            $filename = basename($path);
            $admin->photo = $filename;
        }

        $admin->save();

        return response()->json([
            'message' => 'Profil berhasil diperbarui',
            'data' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'photo_url' => $admin->photo ? asset('storage/profile/' . $admin->photo) : null,
            ]
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

        // Hapus semua token agar harus login ulang
        $admin->tokens()->delete();

        return response()->json([
            'message' => 'Password berhasil diubah. Silakan login kembali.'
        ]);
    }
}
