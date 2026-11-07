<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $provider = Auth::createUserProvider(config('auth.guards.web.provider'));
        $credentials = ['email' => $data['email'], 'password' => $data['password']];
        $user = $provider->retrieveByCredentials($credentials);

        if (! $user || ! $provider->validateCredentials($user, $credentials)) {
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi belum sesuai.']);
        }

        $provider->rehashPasswordIfRequired($user, $credentials);
        $expiresAt = now()->addDays(30);
        $token = $user->createToken($data['device_name'], ['*'], $expiresAt);

        return response()->json([
            'message' => 'Berhasil masuk.',
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'data' => $user,
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil keluar.']);
    }
}
