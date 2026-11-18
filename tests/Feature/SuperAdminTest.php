<?php

namespace Tests\Feature;

use App\Models\Disposisi;
use App\Models\User;
use Database\Seeders\AkunSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function akun(string $role, ?string $jabatan = null): User
    {
        return User::create([
            'name' => $jabatan ? Disposisi::label($jabatan) : ucfirst($role),
            'username' => $jabatan ? 'akun_'.$jabatan : $role,
            'role' => $role,
            'jabatan' => $jabatan,
            'password' => 'PasswordLama123!',
        ]);
    }

    private function perubahan(array $data = []): array
    {
        return $data + [
            'current_password' => 'PasswordLama123!',
            'password' => 'PasswordBaru456!',
            'password_confirmation' => 'PasswordBaru456!',
        ];
    }

    public function test_halaman_login_mengarahkan_pengguna_lupa_password_ke_admin(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('Lupa password?')
            ->assertSee('Hubungi Super Admin')
            ->assertSee('username dan jabatan')
            ->assertDontSee('Belum punya akun?')
            ->assertDontSee('Daftar di sini')
            ->assertDontSee('href="'.route('register').'"', false);
    }

    public function test_tamu_dan_semua_aktor_non_admin_tidak_bisa_mengubah_password(): void
    {
        $target = $this->akun('staf', 'spri_adc');
        $password = $target->getRawOriginal('password');
        $this->get('/admin/akun')->assertRedirect('/login');
        $this->get('/admin/akun/'.$target->id.'/password')->assertRedirect('/login');
        $this->patch('/admin/akun/'.$target->id.'/password', $this->perubahan())->assertRedirect('/login');

        foreach (['tu', 'direktur', 'wadir', 'kasubditbinum', 'staf'] as $role) {
            $actor = $this->akun($role);
            $this->actingAs($actor)->get('/admin/akun')->assertForbidden();
            $this->get('/admin/akun/'.$target->id.'/password')->assertForbidden();
            $this->patch('/admin/akun/'.$target->id.'/password', $this->perubahan())->assertForbidden();
        }

        $this->assertSame($password, $target->fresh()->getRawOriginal('password'));
    }

    public function test_dashboard_admin_dan_pencarian_akun_terpisah_dari_surat(): void
    {
        $admin = $this->akun('super_admin');
        $direktur = $this->akun('direktur');
        $spri = $this->akun('staf', 'spri_adc');

        $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('admin.akun.index'));
        $this->get('/admin/akun')->assertOk()
            ->assertSee('Kelola Akun')
            ->assertSee('Super Admin')
            ->assertSee($direktur->username)
            ->assertSee($spri->username)
            ->assertSee('SPRI/ADC')
            ->assertDontSee('href="'.route('surat.index').'"', false)
            ->assertDontSee('name="password"', false);

        $this->get('/admin/akun?q=spri')->assertOk()
            ->assertViewHas('users', fn ($users) => $users->total() === 1 && $users->first()->is($spri))
            ->assertViewHas('jumlahAkun', 3);
        $this->get('/admin/akun?q=tidakada')->assertOk()
            ->assertViewHas('users', fn ($users) => $users->isEmpty());
        $this->get('/admin/akun?q='.str_repeat('x', 256))->assertSessionHasErrors('q');
        $this->get('/surat')->assertForbidden();
        $this->get('/agenda')->assertForbidden();
        $this->get('/disposisi')->assertForbidden();
    }

    public function test_admin_bisa_mengganti_password_semua_peran_dan_jabatan_tanpa_mengubah_identitas(): void
    {
        $admin = $this->akun('super_admin');
        $adminPassword = $admin->getRawOriginal('password');
        $targets = collect(['tu', 'direktur', 'wadir', 'kasubditbinum'])
            ->map(fn ($role) => $this->akun($role));
        foreach (Disposisi::semuaJabatan() as $jabatan => $label) {
            $targets->push($this->akun('staf', $jabatan));
        }

        foreach ($targets as $target) {
            $identitas = $target->only(['name', 'username', 'role', 'jabatan']);
            $this->actingAs($admin)->get('/admin/akun/'.$target->id.'/password')->assertOk()
                ->assertSee($target->username)
                ->assertSee('name="current_password"', false);
            $this->patch('/admin/akun/'.$target->id.'/password', $this->perubahan([
                'role' => 'super_admin', 'jabatan' => 'pejabat_lain', 'username' => 'diubah',
            ]))->assertRedirect(route('admin.akun.index'))
                ->assertSessionHas('success')
                ->assertSessionMissing('password');
            $target->refresh();
            $this->assertTrue(Hash::check('PasswordBaru456!', $target->password));
            $this->assertFalse(Hash::check('PasswordLama123!', $target->password));
            $this->assertSame($identitas, $target->only(['name', 'username', 'role', 'jabatan']));
            $this->assertSame($adminPassword, $admin->fresh()->getRawOriginal('password'));
        }

        $this->assertSame(0, DB::table('surats')->count());
        $this->assertSame(0, DB::table('disposisis')->count());
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_perubahan_memerlukan_password_admin_dan_konfirmasi_valid_tanpa_memflash_password(): void
    {
        $admin = $this->akun('super_admin');
        $target = $this->akun('staf');
        $password = $target->getRawOriginal('password');
        $invalid = [
            ['current_password' => '', 'error' => 'current_password'],
            ['current_password' => 'salah', 'error' => 'current_password'],
            ['current_password' => ['tidak valid'], 'error' => 'current_password'],
            ['password' => ['tidak valid'], 'password_confirmation' => ['tidak valid'], 'error' => 'password'],
            ['password' => "Password\0Baru123!", 'password_confirmation' => "Password\0Baru123!", 'error' => 'password'],
            ['password' => 'pendek', 'password_confirmation' => 'pendek', 'error' => 'password'],
            ['password_confirmation' => 'berbeda', 'error' => 'password'],
            ['password' => str_repeat('a', 73), 'password_confirmation' => str_repeat('a', 73), 'error' => 'password'],
            ['password' => str_repeat('é', 40), 'password_confirmation' => str_repeat('é', 40), 'error' => 'password'],
        ];

        foreach ($invalid as $data) {
            $error = $data['error'];
            unset($data['error']);
            $this->actingAs($admin)->from('/admin/akun/'.$target->id.'/password')
                ->patch('/admin/akun/'.$target->id.'/password', $this->perubahan($data))
                ->assertSessionHasErrors($error)
                ->assertSessionMissing('_old_input.current_password')
                ->assertSessionMissing('_old_input.password')
                ->assertSessionMissing('_old_input.password_confirmation');
            $this->assertSame($password, $target->fresh()->getRawOriginal('password'));
        }

        $this->get('/admin/akun/999999/password')->assertNotFound();
        $this->patch('/admin/akun/999999/password', $this->perubahan())->assertNotFound();
    }

    public function test_password_lama_tidak_bisa_login_setelah_admin_menggantinya(): void
    {
        $admin = $this->akun('super_admin');
        $target = $this->akun('staf', 'spri_adc');
        $this->actingAs($admin)->patch('/admin/akun/'.$target->id.'/password', $this->perubahan())->assertRedirect();
        $this->post('/logout');
        $this->post('/login', ['username' => $target->username, 'password' => 'PasswordLama123!'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->post('/login', ['username' => $target->username, 'password' => 'PasswordBaru456!'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($target);
    }

    public function test_password_berbentuk_hash_tetap_diperlakukan_sebagai_teks_password(): void
    {
        $admin = $this->akun('super_admin');
        $target = $this->akun('staf');
        $teksPassword = Hash::make('x');

        $this->actingAs($admin)->patch('/admin/akun/'.$target->id.'/password', $this->perubahan([
            'password' => $teksPassword, 'password_confirmation' => $teksPassword,
        ]))->assertRedirect();

        $this->assertTrue(Hash::check($teksPassword, $target->fresh()->password));
        $this->assertFalse(Hash::check('x', $target->fresh()->password));
    }

    public function test_reset_password_mencabut_session_dan_remember_token_hanya_milik_target(): void
    {
        $admin = $this->akun('super_admin');
        $target = $this->akun('staf');
        $target->forceFill(['remember_token' => str_repeat('a', 60)])->save();
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert([
            ['id' => 'session-target', 'user_id' => $target->id, 'payload' => base64_encode(serialize([])), 'last_activity' => time()],
            ['id' => 'session-admin-lain', 'user_id' => $admin->id, 'payload' => base64_encode(serialize([])), 'last_activity' => time()],
        ]);

        $this->actingAs($admin)->patch('/admin/akun/'.$target->id.'/password', $this->perubahan())->assertRedirect();
        $this->assertDatabaseMissing('sessions', ['id' => 'session-target']);
        $this->assertDatabaseHas('sessions', ['id' => 'session-admin-lain']);
        $this->assertNotSame(str_repeat('a', 60), $target->fresh()->remember_token);
    }

    public function test_admin_dapat_mengganti_password_sendiri_tanpa_kehilangan_session_aktif(): void
    {
        $admin = $this->akun('super_admin');
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert([
            'id' => 'session-admin-lain', 'user_id' => $admin->id,
            'payload' => base64_encode(serialize([])), 'last_activity' => time(),
        ]);

        $this->actingAs($admin)->withSession(['verifikasi' => true]);
        $sessionLama = session()->getId();
        $this->patch('/admin/akun/'.$admin->id.'/password', $this->perubahan())->assertRedirect();
        $this->assertDatabaseMissing('sessions', ['id' => 'session-admin-lain']);
        $this->assertDatabaseMissing('sessions', ['id' => $sessionLama]);
        $this->assertNotSame($sessionLama, session()->getId());
        $this->get('/admin/akun')->assertOk();
        $this->assertTrue(Hash::check('PasswordBaru456!', $admin->fresh()->password));
    }

    public function test_super_admin_bukan_penerima_disposisi_meski_jabatan_cocok(): void
    {
        $admin = $this->akun('super_admin', 'spri_adc');
        $spri = $this->akun('staf');
        $spri->update(['jabatan' => 'spri_adc']);
        $penerima = Disposisi::penerimaJabatan()['spri_adc'];
        $this->assertFalse($penerima->contains('id', $admin->id));
        $this->assertTrue($penerima->contains('id', $spri->id));
        $this->assertSame('Super Admin', $admin->roleLabel());
    }

    public function test_register_tidak_bisa_membuat_username_admin_atau_role_admin(): void
    {
        foreach (['superadmin', 'SuperAdmin', 'SUPERADMIN'] as $username) {
            $this->post('/register', [
                'name' => 'Admin palsu', 'username' => $username, 'role' => 'super_admin', 'jabatan' => 'spri_adc',
                'password' => 'PasswordLama123!', 'password_confirmation' => 'PasswordLama123!',
            ])->assertSessionHasErrors('username');
        }
        $this->assertSame(0, User::count());
        $this->post('/register', [
            'name' => 'Staf', 'username' => 'staf_baru', 'role' => 'super_admin', 'jabatan' => 'spri_adc',
            'password' => 'PasswordLama123!', 'password_confirmation' => 'PasswordLama123!',
        ])->assertRedirect('/dashboard');
        $this->assertDatabaseHas('users', ['username' => 'staf_baru', 'role' => 'staf']);
    }

    public function test_tamu_tidak_bisa_membuka_atau_mengganti_password_pribadi(): void
    {
        $this->get('/password')->assertRedirect('/login');
        $this->patch('/password', $this->perubahan())->assertRedirect('/login');
    }

    public function test_semua_peran_dan_jabatan_dapat_mengganti_password_sendiri_tanpa_mengubah_akun_lain(): void
    {
        $lain = User::create([
            'name' => 'Akun Lain', 'username' => 'akun_lain', 'role' => 'staf',
            'jabatan' => 'spri_adc', 'password' => 'PasswordLama123!',
        ]);
        $passwordLain = $lain->getRawOriginal('password');
        $actors = collect(['tu', 'direktur', 'wadir', 'kasubditbinum', 'staf', 'super_admin'])
            ->map(fn ($role) => $this->akun($role));
        foreach (Disposisi::semuaJabatan() as $jabatan => $label) {
            $actors->push($this->akun('staf', $jabatan));
        }

        foreach ($actors as $actor) {
            $identitas = $actor->only(['name', 'username', 'role', 'jabatan']);
            $this->actingAs($actor)->get('/password')->assertOk()
                ->assertSee('Ganti Password Saya')
                ->assertSee($actor->username)
                ->assertSee('Password saat ini')
                ->assertSee('Hubungi Super Admin');
            $this->patch('/password', $this->perubahan([
                'user_id' => $lain->id, 'username' => $lain->username,
                'role' => 'super_admin', 'jabatan' => 'pejabat_lain',
            ]))->assertRedirect(route('password.edit'))
                ->assertSessionHas('success', 'Password Anda berhasil diganti. Gunakan password baru saat login.');
            $actor->refresh();
            $this->assertTrue(Hash::check('PasswordBaru456!', $actor->password));
            $this->assertFalse(Hash::check('PasswordLama123!', $actor->password));
            $this->assertSame($identitas, $actor->only(['name', 'username', 'role', 'jabatan']));
            $this->assertSame($passwordLain, $lain->fresh()->getRawOriginal('password'));
        }
    }

    public function test_password_pribadi_menolak_password_lama_salah_dan_konfirmasi_tidak_cocok(): void
    {
        $actor = $this->akun('staf', 'spri_adc');
        $password = $actor->getRawOriginal('password');
        $this->actingAs($actor)->from('/password')->patch('/password', $this->perubahan([
            'current_password' => 'salah',
        ]))->assertSessionHasErrors(['current_password' => 'Password saat ini tidak cocok.'])
            ->assertSessionMissing('_old_input.current_password')
            ->assertSessionMissing('_old_input.password');
        $this->assertSame($password, $actor->fresh()->getRawOriginal('password'));
        $this->patch('/password', $this->perubahan(['password_confirmation' => 'berbeda']))
            ->assertSessionHasErrors('password');
        $this->assertSame($password, $actor->fresh()->getRawOriginal('password'));
        $this->get('/admin/akun')->assertForbidden();
        $this->get('/password/'.$actor->id)->assertNotFound();
    }

    public function test_login_password_lama_gagal_setelah_aktor_mengganti_password_sendiri(): void
    {
        $actor = $this->akun('wadir');
        $this->actingAs($actor)->patch('/password', $this->perubahan())->assertRedirect('/password');
        $this->post('/logout');
        $this->post('/login', ['username' => $actor->username, 'password' => 'PasswordLama123!'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->post('/login', ['username' => $actor->username, 'password' => 'PasswordBaru456!'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($actor);
    }

    public function test_ganti_password_sendiri_mencabut_session_lama_tanpa_mencabut_akun_lain(): void
    {
        $actor = $this->akun('staf');
        $lain = $this->akun('tu');
        $actor->forceFill(['remember_token' => str_repeat('b', 60)])->save();
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert([
            ['id' => 'session-actor-lain', 'user_id' => $actor->id, 'payload' => base64_encode(serialize([])), 'last_activity' => time()],
            ['id' => 'session-tu', 'user_id' => $lain->id, 'payload' => base64_encode(serialize([])), 'last_activity' => time()],
        ]);
        $this->actingAs($actor)->withSession(['verifikasi' => true]);
        $sessionLama = session()->getId();
        $this->patch('/password', $this->perubahan())->assertRedirect('/password');
        $this->assertDatabaseMissing('sessions', ['id' => $sessionLama]);
        $this->assertDatabaseMissing('sessions', ['id' => 'session-actor-lain']);
        $this->assertDatabaseHas('sessions', ['id' => 'session-tu']);
        $this->assertNotSame(str_repeat('b', 60), $actor->fresh()->remember_token);
        $this->get('/password')->assertOk();
    }

    public function test_seeder_membuat_admin_dari_hash_tanpa_mengatur_ulang_password_atau_menaikkan_role_akun_lama(): void
    {
        $tu = $this->akun('tu');
        $passwordTu = $tu->getRawOriginal('password');
        config(['sisurat.super_admin_password_hash' => Hash::make('AdminAwal123!')]);
        $this->seed(AkunSeeder::class);
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $this->assertSame('super_admin', $admin->role);
        $this->assertTrue(Hash::check('AdminAwal123!', $admin->password));
        $admin->update(['password' => 'AdminDiganti456!']);
        $passwordAdmin = $admin->getRawOriginal('password');
        $this->seed(AkunSeeder::class);
        $this->assertSame($passwordAdmin, $admin->fresh()->getRawOriginal('password'));
        $this->assertSame($passwordTu, $tu->fresh()->getRawOriginal('password'));
        $admin->update(['role' => 'staf']);
        $this->seed(AkunSeeder::class);
        $this->assertSame('staf', $admin->fresh()->role);
        $this->assertSame($passwordAdmin, $admin->fresh()->getRawOriginal('password'));
    }
}
