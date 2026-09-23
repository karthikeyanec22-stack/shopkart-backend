<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // =====================================================
    // REGISTER
    // =====================================================

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'customer',
            'status' => 'active',
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'token' => $token,
        ], 201);
    }


    // =====================================================
    // LOGIN
    // =====================================================

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'Account does not exist. You do not have an account yet, please register or sign up first before logging in.',
                'code' => 'ACCOUNT_NOT_FOUND',
            ], 404);
        }

        if (!Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Incorrect password. Please check your credentials.',
                'code' => 'INVALID_PASSWORD',
            ], 401);
        }

        if ($user->status === 'blocked') {
            return response()->json([
                'message' => 'Your account has been suspended. Please contact support.',
                'code' => 'ACCOUNT_BLOCKED',
            ], 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ], 200);
    }


    // =====================================================
    // CURRENT USER
    // =====================================================

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ], 200);
    }


    // =====================================================
    // LOGOUT
    // =====================================================

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully',
        ], 200);
    }
}