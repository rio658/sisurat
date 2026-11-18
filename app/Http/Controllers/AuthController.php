<?php

namespace App\Http\Controllers;

use App\Models\Disposisi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function login()
    {
        return view('auth.login');
    }

    public function authenticate(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors(['username' => 'Username atau password salah.'])->onlyInput('username');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'bail', 'required', 'string', 'max:255', 'alpha_dash', 'unique:users,username',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (strtolower($value) === 'superadmin') {
                        $fail('Username ini khusus Super Admin.');
                    }
                },
            ],
            'jabatan' => ['required', 'string', Rule::in(array_keys(Disposisi::semuaJabatan()))],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'username.unique' => 'Username sudah dipakai, pilih yang lain.',
            'username.alpha_dash' => 'Username hanya boleh huruf, angka, tanda hubung, dan garis bawah.',
            'jabatan.required' => 'Pilih jabatan Anda agar disposisi untuk jabatan itu masuk ke akun ini.',
            'jabatan.in' => 'Jabatan tidak valid.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.min' => 'Password minimal 8 karakter.',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'jabatan' => $data['jabatan'],
            'password' => $data['password'],
            'role' => 'staf',
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with(
            'success',
            'Pendaftaran berhasil. Anda terdaftar sebagai '.Disposisi::label($data['jabatan']).' dan akan menerima notifikasi disposisi untuk jabatan tersebut.'
        );
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
