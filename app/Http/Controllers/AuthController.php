<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Termwind\Components\Hr;

class AuthController extends Controller
{
    public function register(Request $request){
        $fields = $request->validate([
            'name' => 'required|max:255',
            'email' => 'required|email|unique:admin',
            'password' => 'required'
        ]);

        $fields['password'] = Hash::make($fields['password']);

        $admin = Admin::create($fields);

        $tokens = $admin->createToken($request->name)->plainTextToken;

        return response()->json([
            'admin' => $admin,
            'token' => $tokens
        ]);
    }

    public function login(Request $request){
        $request->validate([
            'email' => 'required|email|exists:admin',
            'password' => 'required'
        ]);

        $admin = Admin::where('email', $request->input('email'))->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ]);
        }

        $tokens = $admin->createToken($request->email)->plainTextToken;

        return response()->json([
            'admin' => $admin,
            'token' => $tokens
        ]);
    }

    public function logout(Request $request){
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'You are logged out'
        ]);
    }
}
