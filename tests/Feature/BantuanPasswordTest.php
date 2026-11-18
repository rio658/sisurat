<?php

namespace Tests\Feature;

use App\Models\Disposisi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BantuanPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function akun(string $role, string $username, ?string $jabatan = null): User
    {
        return User::create([
            'name' => 'Pemilik '.$username,
            'username' => $username,
            'role' => $role,
            'jabatan' => $jabatan,
            'password' => 'password123',
        ]);
    }

    private function permintaan(array $data = []): array
    {
        return $data + [
            'nama' => 'Pemilik Akun TU',
            'username' => 'tu',
            'jabatan' => 'tu',
            'kontak' => '081234567890',
            'pesan' => 'Saya lupa password akun saya.',
        ];
    }

    public function test_login_dan_form_bantuan_tersedia_tanpa_login_untuk_semua_jabatan(): void
    {
        $this->get('/login')->assertOk()->assertSee('Hubungi Super Admin')
            ->assertSee('Infolahta Ditkumad')
            ->assertSee(route('bantuan-password.create'))
            ->assertDontSee('Daftar di sini');

        $response = $this->get('/bantuan-password')->assertOk()
            ->assertSee('Kirim Permintaan Bantuan')
            ->assertSee('Hubungi Super Admin Infolahta Ditkumad')
            ->assertSee('Tata Usaha')
            ->assertSee('Direktur')
            ->assertSee('Super Admin')
            ->assertSee('name="_token"', false);

        foreach (Disposisi::semuaJabatan() as $kode => $label) {
            $response->assertSee('value="'.$kode.'"', false);
        }
    }

    public function test_permohonan_tamu_masuk_hanya_ke_super_admin_tanpa_mengubah_akun(): void
    {
        $admin = $this->akun('super_admin', 'admin_satu');
        $adminKedua = $this->akun('super_admin', 'admin_dua');
        $aktor = $this->akun('tu', 'tu');
        $kabagTu = $this->akun('staf', 'kabag_tu', 'kabag_tu');
        $akunSebelum = DB::table('users')->orderBy('id')->get()->all();

        $this->post('/bantuan-password', $this->permintaan([
            'password' => 'JanganSimpanPasswordIni',
            'current_password' => 'RahasiaSaatIni',
            'role' => 'super_admin',
            'user_id' => $kabagTu->id,
        ]))->assertRedirect(route('bantuan-password.create'))->assertSessionHas('success');

        $this->assertEquals($akunSebelum, DB::table('users')->orderBy('id')->get()->all());
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseCount('surats', 0);
        $this->assertDatabaseCount('disposisis', 0);
        $this->assertSame(0, $aktor->notifications()->count());
        $this->assertSame(0, $kabagTu->notifications()->count());

        foreach ([$admin, $adminKedua] as $penerima) {
            $notifikasi = $penerima->notifications()->sole();
            $this->assertSame('Permintaan Bantuan Password', $notifikasi->data['judul']);
            $this->assertSame(route('admin.akun.index', ['q' => 'tu']), $notifikasi->data['url']);
            $this->assertSame([
                'nama' => 'Pemilik Akun TU',
                'username' => 'tu',
                'jabatan' => 'Tata Usaha',
                'kontak' => '081234567890',
                'pesan' => 'Saya lupa password akun saya.',
                'terautentikasi' => false,
            ], $notifikasi->data['bantuan_password']);
            $this->assertStringNotContainsString('JanganSimpanPasswordIni', json_encode($notifikasi->data));
            $this->assertStringNotContainsString('RahasiaSaatIni', json_encode($notifikasi->data));
        }

        $id = $admin->notifications()->sole()->id;
        $this->get('/notifikasi')->assertRedirect(route('login'));
        $this->actingAs($aktor)->get('/notifikasi')->assertOk()->assertDontSee('081234567890');
        $this->actingAs($aktor)->get('/notifikasi/'.$id.'/baca')->assertNotFound();
        $this->actingAs($admin)->get('/notifikasi')->assertOk()
            ->assertSee('081234567890')
            ->assertSee('Identitas diisi pemohon dan belum terverifikasi.')
            ->assertSee('Verifikasi identitas pemohon sebelum mengganti password.');
        $this->actingAs($admin)->get('/notifikasi/'.$id.'/baca')
            ->assertRedirect(route('admin.akun.index', ['q' => 'tu']));
        $this->assertNotNull($admin->notifications()->sole()->read_at);
    }

    public function test_respons_tamu_tidak_mengungkap_apakah_username_terdaftar(): void
    {
        $admin = $this->akun('super_admin', 'admin');
        $this->akun('tu', 'tu');

        $this->post('/bantuan-password', $this->permintaan())
            ->assertRedirect(route('bantuan-password.create'))->assertSessionHas('success');
        $pesan = session('success');
        $this->post('/bantuan-password', $this->permintaan(['username' => 'akun_tidak_ada']))
            ->assertRedirect(route('bantuan-password.create'))->assertSessionHas('success', $pesan);
        $this->get('/bantuan-password')->assertOk()->assertDontSee('Username tidak ditemukan');
        $this->assertSame(2, $admin->notifications()->count());
    }

    public function test_semua_aktor_dan_jabatan_login_hanya_bisa_mengirim_identitas_sendiri(): void
    {
        $admin = $this->akun('super_admin', 'admin');
        $lain = $this->akun('staf', 'orang_lain', 'kabag_tu');
        $aktor = [];
        foreach (['tu', 'direktur', 'wadir', 'kasubditbinum', 'staf', 'super_admin'] as $role) {
            $aktor[] = $this->akun($role, 'role_'.$role);
        }
        foreach (array_keys(Disposisi::semuaJabatan()) as $jabatan) {
            $aktor[] = $this->akun('staf', 'jabatan_'.$jabatan, $jabatan);
        }

        $akunSebelum = DB::table('users')->orderBy('id')->get()->all();
        foreach ($aktor as $user) {
            $this->actingAs($user)->get('/password')->assertOk()->assertSee('Hubungi Super Admin');
            $this->actingAs($user)->get('/bantuan-password')->assertOk()->assertSee($user->username);
            $this->actingAs($user)->post('/bantuan-password', $this->permintaan([
                'nama' => 'Identitas Palsu',
                'username' => $lain->username,
                'jabatan' => 'kabag_tu',
                'user_id' => $lain->id,
            ]))->assertRedirect(route('bantuan-password.create'))->assertSessionHasNoErrors();

            $notifikasi = $admin->notifications()->get()->first(
                fn ($notifikasi) => $notifikasi->data['bantuan_password']['username'] === $user->username
            );
            $this->assertNotNull($notifikasi);
            $this->assertSame($user->name, $notifikasi->data['bantuan_password']['nama']);
            $this->assertSame($user->sebutanAktor(), $notifikasi->data['bantuan_password']['jabatan']);
            $this->assertTrue($notifikasi->data['bantuan_password']['terautentikasi']);
        }
        $this->assertEquals($akunSebelum, DB::table('users')->orderBy('id')->get()->all());
        $this->assertSame(0, $lain->notifications()->count());
    }

    public function test_validasi_bantuan_menolak_input_tidak_valid_dan_tidak_memflash_password(): void
    {
        $this->akun('super_admin', 'admin');
        $cases = [
            [['kontak' => ''], 'kontak'],
            [['kontak' => ['081234567890']], 'kontak'],
            [['kontak' => '<script>alert(1)</script>'], 'kontak'],
            [['kontak' => '--------'], 'kontak'],
            [['kontak' => '1234567-'], 'kontak'],
            [['kontak' => '1234567890123456'], 'kontak'],
            [['pesan' => ['pesan']], 'pesan'],
            [['pesan' => str_repeat('x', 1001)], 'pesan'],
            [['nama' => ''], 'nama'],
            [['nama' => ['nama']], 'nama'],
            [['nama' => str_repeat('n', 101)], 'nama'],
            [['username' => ['tu']], 'username'],
            [['username' => '<script>'], 'username'],
            [['jabatan' => 'jabatan_tidak_valid'], 'jabatan'],
            [['jabatan' => ['tu']], 'jabatan'],
        ];
        foreach ($cases as $index => [$data, $field]) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($index + 1)])
                ->post('/bantuan-password', $this->permintaan($data + [
                    'password' => 'RahasiaBaru',
                    'current_password' => 'RahasiaLama',
                    'password_confirmation' => 'RahasiaBaru',
                ]))->assertSessionHasErrors($field);
            $this->assertArrayNotHasKey('password', session('_old_input', []));
            $this->assertArrayNotHasKey('current_password', session('_old_input', []));
            $this->assertArrayNotHasKey('password_confirmation', session('_old_input', []));
            $this->get('/bantuan-password')->assertOk();
        }
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_permintaan_bantuan_dibatasi_untuk_mencegah_spam_notifikasi(): void
    {
        $admin = $this->akun('super_admin', 'admin');
        for ($i = 0; $i < 3; $i++) {
            $this->post('/bantuan-password', $this->permintaan())->assertRedirect();
        }
        $this->post('/bantuan-password', $this->permintaan())->assertStatus(429)->assertHeader('Retry-After');
        $this->assertSame(3, $admin->notifications()->count());
    }

    public function test_nomor_kontak_dengan_kode_negara_dan_pemisah_diterima(): void
    {
        $admin = $this->akun('super_admin', 'admin');
        $this->post('/bantuan-password', $this->permintaan(['kontak' => '+62 (812) 3456-7890']))
            ->assertRedirect(route('bantuan-password.create'))->assertSessionHasNoErrors();
        $this->assertSame('+62 (812) 3456-7890', $admin->notifications()->sole()->data['bantuan_password']['kontak']);
    }

    public function test_notifikasi_username_panjang_tetap_membuka_akun_yang_dimaksud(): void
    {
        $admin = $this->akun('super_admin', 'admin');
        $username = str_repeat('u', 255);
        $this->akun('staf', $username, 'kabag_tu');
        $this->post('/bantuan-password', $this->permintaan(['username' => $username]))
            ->assertRedirect(route('bantuan-password.create'))->assertSessionHasNoErrors();
        $notifikasi = $admin->notifications()->sole();
        $this->actingAs($admin)->get('/notifikasi/'.$notifikasi->id.'/baca')
            ->assertRedirect(route('admin.akun.index', ['q' => $username]));
        $this->actingAs($admin)->get($notifikasi->data['url'])->assertOk()->assertSee($username);
    }

    public function test_isi_permohonan_di_notifikasi_admin_ditampilkan_sebagai_teks_aman(): void
    {
        $admin = $this->akun('super_admin', 'admin');
        $this->post('/bantuan-password', $this->permintaan([
            'nama' => '<script>alert(1)</script>',
            'pesan' => '<img src=x onerror=alert(1)>',
        ]))->assertRedirect();

        $this->actingAs($admin)->get('/notifikasi')->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }
}
