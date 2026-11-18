<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AkunSeeder extends Seeder
{
    public function run(): void
    {
        $akun = [
            ['name' => 'Tata Usaha', 'username' => 'tu', 'role' => 'tu'],
            ['name' => 'Direktur', 'username' => 'direktur', 'role' => 'direktur'],
            ['name' => 'Wakil Direktur', 'username' => 'wadir', 'role' => 'wadir', 'jabatan' => 'wadirkumad'],
            ['name' => 'Kasubdit Binum', 'username' => 'kasubditbinum', 'role' => 'kasubditbinum', 'jabatan' => 'kasubditbinum'],
            ['name' => 'Kabag Pers', 'username' => 'kabagpers', 'role' => 'staf', 'jabatan' => 'kabag_pers'],
            ['name' => 'KETUA STHM', 'username' => 'ketua_sthm', 'role' => 'staf', 'jabatan' => 'ketua_sthm'],
            ['name' => 'DANPUSDIKKUM', 'username' => 'danpusdikkum', 'role' => 'staf', 'jabatan' => 'danpusdikkum'],
            ['name' => 'IRDITKUMAD', 'username' => 'irditkumad', 'role' => 'staf', 'jabatan' => 'irditkumad'],
            ['name' => 'PAMEN AHLI BID KUM UMUM', 'username' => 'pamen_ahli_kum_umum', 'role' => 'staf', 'jabatan' => 'pamen_ahli_kum_umum'],
            ['name' => 'PAMEN AHLI BID KUM MILHUM', 'username' => 'pamen_ahli_kum_milhum', 'role' => 'staf', 'jabatan' => 'pamen_ahli_kum_milhum'],
            ['name' => 'KASUBDITBINCAB', 'username' => 'kasubditbincab', 'role' => 'staf', 'jabatan' => 'kasubditbincab'],
            ['name' => 'KASUBDITBINDIKLAT', 'username' => 'kasubditbindiklat', 'role' => 'staf', 'jabatan' => 'kasubditbindiklat'],
            ['name' => 'KASUBDITDUKKUM', 'username' => 'kasubditdukkum', 'role' => 'staf', 'jabatan' => 'kasubditdukkum'],
            ['name' => 'KASUBDITBANKUMPID', 'username' => 'kasubditbankumpid', 'role' => 'staf', 'jabatan' => 'kasubditbankumpid'],
            ['name' => 'KASUBDITBANKUMPERDATUN', 'username' => 'kasubditbankumperdatun', 'role' => 'staf', 'jabatan' => 'kasubditbankumperdatun'],
            ['name' => 'KASUBDITBINUNDANG', 'username' => 'kasubditbinundang', 'role' => 'staf', 'jabatan' => 'kasubditbinundang'],
            ['name' => 'KABAG PAM', 'username' => 'kabag_pam', 'role' => 'staf', 'jabatan' => 'kabag_pam'],
            ['name' => 'KABAG LOG', 'username' => 'kabag_log', 'role' => 'staf', 'jabatan' => 'kabag_log'],
            ['name' => 'KABAG PROGGAR', 'username' => 'kabag_proggar', 'role' => 'staf', 'jabatan' => 'kabag_proggar'],
            ['name' => 'KABAG URDAL', 'username' => 'kabag_urdal', 'role' => 'staf', 'jabatan' => 'kabag_urdal'],
            ['name' => 'KABAG TER', 'username' => 'kabag_ter', 'role' => 'staf', 'jabatan' => 'kabag_ter'],
            ['name' => 'KAINFOLAHTA', 'username' => 'kainfolahta', 'role' => 'staf', 'jabatan' => 'kainfolahta'],
            ['name' => 'PAKU', 'username' => 'paku', 'role' => 'staf', 'jabatan' => 'paku'],
            ['name' => 'SPRI/ADC', 'username' => 'spri_adc', 'role' => 'staf', 'jabatan' => 'spri_adc'],
            ['name' => 'KABAG TU', 'username' => 'kabag_tu', 'role' => 'staf', 'jabatan' => 'kabag_tu'],
        ];

        foreach ($akun as $data) {
            $user = User::firstOrCreate(
                ['username' => $data['username']],
                $data + ['password' => env('SEED_PASSWORD', 'password123')]
            );

            if ($user->name !== $data['name']) {
                $user->update(['name' => $data['name']]);
            }

            if ($user->username === 'tu' && $user->role === 'tu' && $user->jabatan === 'kabag_tu') {
                $user->update(['jabatan' => null]);
            } elseif (! $user->jabatan && isset($data['jabatan'])) {
                $user->update(['jabatan' => $data['jabatan']]);
            }
        }

        if ($passwordHash = config('sisurat.super_admin_password_hash')) {
            User::firstOrCreate(['username' => 'superadmin'], [
                'name' => 'Super Admin',
                'role' => 'super_admin',
                'password' => $passwordHash,
            ]);
        }
    }
}
