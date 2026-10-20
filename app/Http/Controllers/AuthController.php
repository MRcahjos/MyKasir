<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
class AuthController extends Controller
{
    public function show() { return view('auth.login', ['setup' => ! User::exists()]); }
    public function login(Request $request) {
        $data = $request->validate(['email' => ['required','email'], 'password' => ['required','string']]);
        if (! Auth::attempt($data, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi belum sesuai.']);
        }
        $request->session()->regenerate();
        return response()->json(['message'=>'Selamat datang kembali.', 'redirect'=>route('dashboard')]);
    }
    public function setup(Request $request) {
        abort_if(User::exists(), 403, 'Akun pemilik sudah dibuat. Silakan masuk.');
        $data = $request->validate(['name'=>['required','string','max:100'], 'email'=>['required','email','max:255','unique:users'], 'password'=>['required','confirmed',Password::min(8)]]);
        $user = Cache::lock('mykasir-initial-owner', 10)->block(5, function () use ($data) {
            return DB::transaction(function () use ($data) {
                abort_if(User::exists(), 403, 'Akun pemilik sudah dibuat. Silakan masuk.');
                return User::create($data);
            });
        });
        Auth::login($user);
        $request->session()->regenerate();
        return response()->json(['message'=>'Akun pemilik berhasil dibuat.', 'redirect'=>route('dashboard')], 201);
    }
    public function logout(Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['redirect'=>route('login')]);
    }
}
