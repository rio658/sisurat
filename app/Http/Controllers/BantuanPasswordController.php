<?php

namespace App\Http\Controllers;

use App\Models\Disposisi;
use App\Models\User;
use App\Notifications\PermintaanBantuanPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class BantuanPasswordController extends Controller
{
    public function create(Request $request)
    {
        $user = $request->user();
        $jabatanList = $this->jabatanList();

        return view('auth.bantuan-password', compact('user', 'jabatanList'));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $jabatanList = $this->jabatanList();
        $rules = [
            'kontak' => [
                'bail', 'required', 'string', 'max:25', 'regex:/^\+?[0-9 ()-]{8,25}$/',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $jumlahDigit = preg_match_all('/[0-9]/', $value);
                    if ($jumlahDigit < 8 || $jumlahDigit > 15) {
                        $fail('Nomor kontak harus mengandung 8 sampai 15 digit angka.');
                    }
                },
            ],
            'pesan' => ['nullable', 'string', 'max:1000'],
        ];

        if (! $user) {
            $rules += [
                'nama' => ['required', 'string', 'max:100'],
                'username' => ['bail', 'required', 'string', 'max:255', 'alpha_dash'],
                'jabatan' => ['required', 'string', Rule::in(array_keys($jabatanList))],
            ];
        }

        $data = $request->validate($rules, [
            'kontak.required' => 'Isi nomor WhatsApp / telepon yang dapat dihubungi.',
            'kontak.regex' => 'Nomor kontak hanya boleh berisi angka, tanda + di awal, spasi, tanda kurung, atau tanda hubung.',
            'nama.required' => 'Masukkan nama Anda.',
            'username.required' => 'Masukkan username akun Anda.',
            'username.alpha_dash' => 'Username hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
            'jabatan.required' => 'Pilih jabatan Anda.',
            'jabatan.in' => 'Jabatan tidak valid.',
            'pesan.max' => 'Pesan maksimal 1000 karakter.',
        ]);

        $identitas = $user ? [
            'nama' => $user->name,
            'username' => $user->username,
            'jabatan' => $user->sebutanAktor(),
        ] : [
            'nama' => $data['nama'],
            'username' => $data['username'],
            'jabatan' => $jabatanList[$data['jabatan']],
        ];

        Notification::send(
            User::where('role', 'super_admin')->get(),
            new PermintaanBantuanPassword($identitas + [
                'kontak' => $data['kontak'],
                'pesan' => $data['pesan'] ?? '',
                'terautentikasi' => $user !== null,
            ])
        );

        return redirect()->route('bantuan-password.create')->with(
            'success',
            'Permintaan bantuan sudah dikirim kepada Super Admin Infolahta Ditkumad. Admin akan menghubungi nomor yang Anda tulis setelah memverifikasi identitas. Password belum diubah.'
        );
    }

    private function jabatanList(): array
    {
        return [
            'tu' => 'Tata Usaha',
            'direktur' => 'Direktur',
            'super_admin' => 'Super Admin',
            'staf' => 'Staf',
        ] + Disposisi::semuaJabatan();
    }
}
