<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        return view('auth.password', compact('user'));
    }

    public function update(Request $request)
    {
        return $this->gantiPassword($request, $request->user(), false);
    }

    public function updateAkun(Request $request, User $user)
    {
        return $this->gantiPassword($request, $user, true);
    }

    private function gantiPassword(Request $request, User $user, bool $olehAdmin)
    {
        $data = $request->validate([
            'current_password' => ['bail', 'required', 'string', 'current_password:web'],
            'password' => [
                'bail', 'required', 'string', 'min:8', 'confirmed',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (strlen($value) > 72) {
                        $fail('Password maksimal 72 byte. Gunakan password yang lebih pendek.');
                    }
                    if (str_contains($value, "\0")) {
                        $fail('Password tidak boleh mengandung karakter null.');
                    }
                },
            ],
        ], [
            'current_password.required' => $olehAdmin
                ? 'Masukkan password Super Admin untuk mengonfirmasi perubahan.'
                : 'Masukkan password saat ini untuk mengonfirmasi perubahan.',
            'current_password.current_password' => $olehAdmin ? 'Password Super Admin tidak cocok.' : 'Password saat ini tidak cocok.',
            'password.min' => 'Password baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
        ]);

        $akunSendiri = $user->is($request->user());

        DB::transaction(function () use ($user, $data, $request, $akunSendiri) {
            $user->forceFill([
                'password' => Hash::make($data['password']),
                'remember_token' => Str::random(60),
            ])->save();

            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))
                    ->table(config('session.table', 'sessions'))
                    ->where('user_id', $user->id)
                    ->when($akunSendiri, fn ($query) => $query->where('id', '!=', $request->session()->getId()))
                    ->delete();
            }
        });

        if ($akunSendiri) {
            $request->session()->regenerate(true);
        }

        return redirect()->route($olehAdmin ? 'admin.akun.index' : 'password.edit')->with(
            'success',
            $olehAdmin
                ? 'Password akun '.$user->username.' berhasil diganti. Sampaikan password baru secara pribadi kepada pemilik akun.'
                : 'Password Anda berhasil diganti. Gunakan password baru saat login.'
        );
    }
}
