<?php

namespace Database\Seeders;

use App\Models\Surat;
use App\Models\User;
use App\Notifications\DisposisiBaru;
use App\Notifications\SuratMasuk;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Tata Usaha', 'username' => 'tu', 'role' => 'tu'],
            ['name' => 'Direktur', 'username' => 'direktur', 'role' => 'direktur'],
            ['name' => 'Wakil Direktur', 'username' => 'wadir', 'role' => 'wadir'],
            ['name' => 'Kasubdit Binum', 'username' => 'kasubditbinum', 'role' => 'kasubditbinum'],
            ['name' => 'Staf Administrasi', 'username' => 'staf1', 'role' => 'staf'],
            ['name' => 'Staf Keuangan', 'username' => 'staf2', 'role' => 'staf'],
            ['name' => 'Staf Umum', 'username' => 'staf3', 'role' => 'staf'],
        ];

        foreach ($users as $u) {
            User::firstOrCreate(['username' => $u['username']], $u + ['password' => 'password123']);
        }

        if (Surat::count() === 0) {
            $tu = User::where('username', 'tu')->first();
            $direktur = User::where('username', 'direktur')->first();
            $wadir = User::where('role', 'wadir')->first();

            $contoh = [
                [
                    'nomor_surat' => '005/UND/XI/2026',
                    'tanggal_surat' => '2026-11-05',
                    'tanggal_terima' => '2026-11-10',
                    'asal_surat' => 'Kementerian Pendidikan Tinggi',
                    'perihal' => 'Undangan Rapat Koordinasi Akademik Tahun 2026',
                    'jenis_surat' => 'B',
                ],
                [
                    'nomor_surat' => '123/KEP/X/2026',
                    'tanggal_surat' => '2026-11-08',
                    'tanggal_terima' => '2026-11-10',
                    'asal_surat' => 'Sekretariat Jenderal',
                    'perihal' => 'Keputusan tentang Penetapan Jadwal Kegiatan',
                    'jenis_surat' => 'Kep',
                ],
            ];

            foreach ($contoh as $c) {
                $agenda = Surat::buatNomorAgenda($c['jenis_surat'], $c['tanggal_terima']);
                $surat = Surat::create($c + $agenda + ['status' => 'baru', 'created_by' => $tu->id]);
                Notification::send(User::where('role', 'direktur')->get(), new SuratMasuk($surat));
            }

            $suratPertama = Surat::orderBy('id')->first();
            $disposisi = $suratPertama->disposisis()->create([
                'dari_user_id' => $direktur->id,
                'ke_user_id' => $wadir->id,
                'jabatan_tujuan' => 'wadirkumad',
                'target_jabatan' => ['kabag_tu'],
                'instruksi' => 'Mohon siapkan bahan rapat dan konfirmasi kehadiran sebelum 13 November 2026.',
            ]);
            $suratPertama->update(['status' => 'diproses']);
            $wadir->notify(new DisposisiBaru($disposisi));
        }
    }
}
