<?php

namespace Tests\Feature;

use App\Models\Disposisi;
use App\Models\Surat;
use App\Models\User;
use App\Notifications\SuratMasuk;
use Database\Seeders\AkunSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SisuratTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(string $role, ?string $username = null): User
    {
        return User::create([
            'name' => ucfirst($role),
            'username' => $username ?? $role,
            'password' => 'password123',
            'role' => $role,
        ]);
    }

    private function buatSurat(User $tu, string $jenis = 'B'): Surat
    {
        $agenda = Surat::buatNomorAgenda($jenis, '2026-11-10');

        return Surat::create($agenda + [
            'jenis_surat' => $jenis,
            'nomor_surat' => '001/TEST/XI/2026',
            'tanggal_surat' => '2026-11-05',
            'tanggal_terima' => '2026-11-10',
            'asal_surat' => 'Instansi Contoh',
            'perihal' => 'Undangan Rapat',
            'status' => 'baru',
            'created_by' => $tu->id,
        ]);
    }

    public function test_akun_tu_dan_kabag_tu_terpisah_tanpa_mengubah_password_atau_role(): void
    {
        $tu = $this->buatUser('tu');
        $tu->update(['jabatan' => 'kabag_tu']);
        $kabagTu = $this->buatUser('staf', 'kabag_tu');
        $kabagTu->update(['jabatan' => 'kabag_tu']);
        $passwordTu = $tu->getRawOriginal('password');
        $passwordKabagTu = $kabagTu->getRawOriginal('password');

        $this->seed(AkunSeeder::class);
        $this->seed(AkunSeeder::class);

        $this->assertNull($tu->fresh()->jabatan);
        $this->assertSame('Tata Usaha', $tu->fresh()->sebutanAktor());
        $this->assertSame('tu', $tu->fresh()->role);
        $this->assertSame($passwordTu, $tu->fresh()->getRawOriginal('password'));
        $this->assertSame('KABAG TU', $kabagTu->fresh()->sebutanAktor());
        $this->assertSame('staf', $kabagTu->fresh()->role);
        $this->assertSame($passwordKabagTu, $kabagTu->fresh()->getRawOriginal('password'));
        $this->assertSame([$kabagTu->id], Disposisi::penerimaJabatan()['kabag_tu']->pluck('id')->all());
        $this->assertSame(0, Surat::count());
    }

    public function test_akun_tu_baru_tidak_dipetakan_ke_kabag_tu(): void
    {
        $this->seed(AkunSeeder::class);

        $tu = User::where('username', 'tu')->sole();
        $this->assertNull($tu->jabatan);
        $this->assertSame('tu', $tu->role);

        $penerima = Disposisi::penerimaJabatan()['kabag_tu'];
        $this->assertFalse($penerima->contains('id', $tu->id));
        $this->assertTrue($penerima->contains('username', 'kabag_tu'));
    }

    public function test_disposisi_kabag_tu_tidak_masuk_ke_akun_petugas_tu(): void
    {
        $tu = $this->buatUser('tu');
        $tu->update(['jabatan' => 'kabag_tu']);
        $kabagTu = $this->buatUser('staf', 'kabag_tu');
        $kabagTu->update(['jabatan' => 'kabag_tu']);
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $surat = $this->buatSurat($tu);

        $this->assertSame([$kabagTu->id], Disposisi::penerimaJabatan()['kabag_tu']->pluck('id')->all());
        $this->actingAs($tu)->get('/surat/input')->assertOk();
        $this->actingAs($kabagTu)->get('/surat/input')->assertForbidden();
        $this->actingAs($kabagTu)->get("/surat/{$surat->id}")->assertForbidden();

        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kabag_tu'],
            'perintah' => ['pelajari_teliti'],
            'lain_lain' => 'Arahan Direktur untuk KABAG TU',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(0, $kabagTu->notifications()->count());
        $this->assertFalse($surat->disposisis()->where('ke_user_id', $kabagTu->id)->exists());

        $this->actingAs($wadir)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kabag_tu'],
            'perintah' => ['tindak_lanjuti'],
            'lain_lain' => 'Arahan Wadir untuk KABAG TU',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame([$kabagTu->id], $surat->disposisis()->where('jabatan_tujuan', 'kabag_tu')->pluck('ke_user_id')->all());
        $this->assertSame(1, $kabagTu->notifications()->count());
        $this->assertSame(0, $tu->notifications()->count());
        $this->assertFalse($surat->disposisis()->where('ke_user_id', $tu->id)->exists());
        $this->actingAs($kabagTu)->get("/surat/{$surat->id}")->assertOk()
            ->assertSee('Arahan Direktur untuk KABAG TU')
            ->assertSee('Arahan Wadir untuk KABAG TU');
        $this->actingAs($kabagTu)->get('/dashboard')->assertOk()->assertSee('Dashboard KABAG TU');
        $this->actingAs($tu)->post("/disposisi/{$surat->disposisis()->where('ke_user_id', $kabagTu->id)->sole()->id}/checklist", [
            'aksi' => 'dibaca',
        ])->assertForbidden();
    }

    public function test_halaman_login_bisa_diakses(): void
    {
        $this->get('/login')->assertOk()->assertSee('SISURAT');
    }

    public function test_login_berhasil_dan_gagal(): void
    {
        $this->buatUser('tu');

        $this->post('/login', ['username' => 'tu', 'password' => 'password123'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $this->post('/logout');

        $this->post('/login', ['username' => 'tu', 'password' => 'salah'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_tu_input_surat_agenda_otomatis_dan_notifikasi_direktur(): void
    {
        Storage::fake('local');
        $this->travelTo('2026-11-10 09:00:00');
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');

        $data = [
            'tanggal_surat' => '2026-11-05',
            'asal_surat' => 'Kementerian Contoh',
            'perihal' => 'Undangan Rapat',
            'jenis_surat' => 'B',
            'file_pdf' => UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf'),
        ];

        $this->actingAs($tu)->post('/surat', $data)->assertRedirect();

        $surat = Surat::first();
        $this->assertSame('001/B/XI/2026', $surat->nomor_agenda);
        $this->assertSame('001/B/XI/2026', $surat->nomor_surat);
        $this->assertSame('2026-11-10', $surat->tanggal_terima->toDateString());
        $this->assertSame('baru', $surat->status);
        $this->assertDatabaseHas('disposisis', [
            'surat_id' => $surat->id,
            'dari_user_id' => $tu->id,
            'ke_user_id' => $direktur->id,
            'jabatan_tujuan' => null,
        ]);
        $this->assertSame(1, $direktur->fresh()->unreadNotifications()->count());
        $notifTu = $tu->fresh()->notifications()->get();
        $this->assertSame(1, $notifTu->count());
        $this->assertSame('Surat Terkirim ke Direktur', $notifTu->first()->data['judul']);
        Storage::disk('local')->assertExists($surat->file_pdf);

        $this->actingAs($tu)->get('/agenda?agenda='.$surat->id)->assertOk()
            ->assertViewHas('barisAgenda', fn ($baris) => $baris->firstWhere('label', 'Direktur')['penerima']->is($direktur)
                && $baris->firstWhere('label', 'Direktur')['tanggal_diterima'] !== null
                && $baris->firstWhere('label', 'Direktur')['dari']->first()->is($tu));

        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['wadirkumad'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertSessionHasNoErrors();

        $lanjutanTu = $tu->fresh()->notifications()->get()
            ->first(fn ($n) => $n->data['judul'] === 'Disposisi Diteruskan');
        $this->assertNotNull($lanjutanTu);
        $this->assertStringContainsString('Direktur', $lanjutanTu->data['pesan']);

        $data['jenis_surat'] = 'R';
        $data['file_pdf'] = UploadedFile::fake()->create('surat2.pdf', 100, 'application/pdf');
        $this->actingAs($tu)->post('/surat', $data)->assertRedirect();
        $this->assertSame('002/R/XI/2026', Surat::latest('id')->first()->nomor_agenda);
    }

    public function test_pratinjau_nomor_surat_mengikuti_agenda_dan_hanya_untuk_tu(): void
    {
        $this->travelTo('2026-11-10 09:00:00');
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $this->buatSurat($tu);

        $this->actingAs($tu)->getJson('/surat/pratinjau-nomor?jenis_surat=B')
            ->assertOk()
            ->assertJson(['no_urut' => 2, 'tahun' => 2026, 'nomor_agenda' => '002/B/XI/2026']);

        $this->actingAs($direktur)->getJson('/surat/pratinjau-nomor?jenis_surat=B')
            ->assertForbidden();
    }

    public function test_selain_tu_tidak_bisa_input_surat(): void
    {
        $this->buatUser('direktur');
        $this->actingAs(User::where('username', 'direktur')->first())
            ->post('/surat', [])->assertForbidden();
    }

    public function test_direktur_disposisi_otomatis_ke_akun_jabatan_dengan_notifikasi(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $surat = $this->buatSurat($tu);

        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['wadirkumad'],
            'perintah' => ['siapkan_bahan_materi'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('disposisis', [
            'surat_id' => $surat->id,
            'dari_user_id' => $direktur->id,
            'ke_user_id' => $wadir->id,
            'jabatan_tujuan' => 'wadirkumad',
            'instruksi' => 'SIAPKAN BAHAN MATERI',
        ]);
        $this->assertSame('diproses', $surat->fresh()->status);
        $this->assertSame(1, $wadir->fresh()->unreadNotifications()->count());
    }

    public function test_direktur_bisa_melihat_isi_pdf_surat_dari_tu(): void
    {
        Storage::fake('local');
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $surat = $this->buatSurat($tu);
        $surat->update(['file_pdf' => 'surat/contoh.pdf']);
        Storage::disk('local')->put('surat/contoh.pdf', '%PDF-1.4 isi surat contoh');

        $this->actingAs($direktur)->get("/surat/{$surat->id}")->assertOk()
            ->assertSee('Isi Surat (PDF) dari TU')
            ->assertSee('src="'.route('surat.file', $surat).'"', false);

        $this->get(route('surat.file', $surat))->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->buatUser('staf'))
            ->get(route('surat.file', $surat))->assertForbidden();
    }

    public function test_jabatan_tanpa_akun_tetap_bisa_diceklis_tanpa_notifikasi(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $staf = $this->buatUser('staf');
        $surat = $this->buatSurat($tu);

        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['wadirkumad'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($wadir)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['irditkumad', 'kabag_pers'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(3, $surat->disposisis()->count());
        $this->assertDatabaseHas('disposisis', [
            'surat_id' => $surat->id, 'ke_user_id' => null,
            'jabatan_tujuan' => 'irditkumad', 'instruksi' => 'TINDAK LANJUTI',
        ]);
        $this->assertDatabaseHas('disposisis', [
            'surat_id' => $surat->id, 'ke_user_id' => null,
            'jabatan_tujuan' => 'kabag_pers', 'instruksi' => 'TINDAK LANJUTI',
        ]);
        $this->assertSame(0, $staf->fresh()->notifications()->count());
        $this->assertSame('diproses', $surat->fresh()->status);

        $this->actingAs($direktur)->get("/surat/{$surat->id}")->assertOk()->assertSee('IRDITKUMAD');
        $this->actingAs($direktur)->get('/disposisi')->assertOk()->assertSee('Belum ada akun');
    }

    public function test_form_direktur_memuat_ceklis_jabatan_dan_perintah_foto(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $this->buatUser('kasubditbinum');
        $surat = $this->buatSurat($tu);

        $response = $this->actingAs($direktur)->get("/surat/{$surat->id}")->assertOk()
            ->assertSee('Disposisi Dirkumad')->assertSee('Lain-lain')
            ->assertSeeInOrder(array_values(Disposisi::jabatanDirekturList()))
            ->assertSeeInOrder(array_values(Disposisi::perintahList()));

        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(count(Disposisi::jabatanDirekturList()), $xpath->query('//form[@id="form-disposisi-direktur"]//input[@name="jabatan[]"]')->length);
        $this->assertSame(30, $xpath->query('//form[@id="form-disposisi-direktur"]//input[@name="perintah[]"]')->length);
        $this->assertSame(0, $xpath->query('//form[@id="form-disposisi-direktur"]//select')->length);
        $this->assertSame(count(Disposisi::jabatanDirekturList()), $xpath->query('//form[@id="form-disposisi-direktur"]//input[@name="jabatan[]" and not(@disabled)]')->length);
        $this->assertSame(0, $xpath->query('//form[@id="form-disposisi-direktur"]//input[@name="jabatan[]" and @disabled]')->length);
        $this->assertSame(1, $xpath->query('//form[@id="form-disposisi-direktur"]//input[@name="jabatan[]" and @checked]')->length);
        $this->assertStringNotContainsString($wadir->name, $response->getContent());

        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['wadirkumad'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($wadir)->get("/surat/{$surat->id}")->assertOk()
            ->assertDontSee('id="form-disposisi-direktur"', false)
            ->assertSee('id="form-disposisi-wadir"', false)
            ->assertDontSee('name="ke_user_id"', false);
        $this->actingAs($tu)->get("/surat/{$surat->id}")->assertOk()
            ->assertDontSee('id="form-disposisi-direktur"', false)
            ->assertDontSee('id="form-disposisi-wadir"', false);
    }

    public function test_direktur_hanya_bisa_mendisposisikan_kepada_wadir(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $kasubdit = $this->buatUser('kasubditbinum');
        $surat = $this->buatSurat($tu);

        // Direktur memilih kasubditbinum, tetapi disposisi hanya diterima oleh WADIRKUMAD
        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kasubditbinum'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, $surat->disposisis()->count());
        $disposisiDirektur = $surat->disposisis()->first();
        $this->assertSame($wadir->id, $disposisiDirektur->ke_user_id);
        $this->assertSame('wadirkumad', $disposisiDirektur->jabatan_tujuan);
        $this->assertSame(['kasubditbinum'], $disposisiDirektur->target_jabatan);

        $this->assertSame(1, $wadir->fresh()->unreadNotifications()->count());
        $this->assertSame(0, $kasubdit->fresh()->notifications()->count());
        $this->actingAs($kasubdit)->get("/surat/{$surat->id}")->assertForbidden();
        $this->get("/surat/{$surat->id}/file")->assertForbidden();
        $this->get("/surat/{$surat->id}/cetak")->assertForbidden();
        $this->get('/dashboard')->assertOk()->assertDontSee($surat->nomor_agenda);
        $this->get('/disposisi')->assertOk()->assertDontSee($surat->nomor_agenda);
        $this->get('/surat')->assertOk()->assertDontSee($surat->nomor_agenda)
            ->assertDontSee('href="'.route('surat.show', $surat).'"', false);
        $this->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kabag_pers'],
            'perintah' => ['catat'],
        ])->assertForbidden();
        $this->assertSame(1, $surat->disposisis()->count());

        // Wadir melihat arahan Direktur dan kasubditbinum otomatis tercentang
        $this->actingAs($wadir)->get("/surat/{$surat->id}")->assertOk()
            ->assertSee('Arahan Dirkumad')
            ->assertSee('KASUBDITBINUM');

        // Setelah wadir mendisposisikan, kasubdit menerima notifikasi dan bisa melihat disposisi direktur
        $this->actingAs($wadir)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kasubditbinum'],
            'perintah' => ['catat'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, $kasubdit->fresh()->unreadNotifications()->count());
        $this->actingAs($kasubdit)->get("/surat/{$surat->id}")->assertOk()
            ->assertSee('Riwayat Disposisi (2)')
            ->assertSee('Ceklis Pejabat Dituju:')
            ->assertSee('TINDAK LANJUTI')
            ->assertSee('CATAT');
    }

    public function test_penerima_lanjutan_wadir_dapat_melihat_disposisi_direktur(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $kabag = $this->buatUser('staf', 'kabagpers');
        $surat = $this->buatSurat($tu);

        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['wadirkumad'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertSessionHasNoErrors();

        $this->actingAs($wadir)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kabag_pers'],
            'perintah' => ['catat'],
        ])->assertSessionHasNoErrors();

        $this->actingAs($kabag)->get("/surat/{$surat->id}")->assertOk()
            ->assertSee('Riwayat Disposisi (2)')
            ->assertSeeInOrder([
                'data-urutan="1"',
                'Direktur &rarr; WADIRKUMAD',
                'TINDAK LANJUTI',
                'data-urutan="2"',
                'Wakil Direktur &rarr; KABAG PERS',
                'CATAT',
            ], false);
    }

    public function test_disposisi_dirkum_multi_ceklis_hanya_diterima_wadir_dan_setelah_disposisi_wadir_penerima_bisa_melihat_disposisi_direktur(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $kabag = $this->buatUser('staf', 'kabagpers');
        $kasubdit = $this->buatUser('kasubditbinum');
        $surat = $this->buatSurat($tu);

        // Dirkum menceklis kasubditbinum dan kabag_pers
        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kasubditbinum', 'kabag_pers'],
            'perintah' => ['tindak_lanjuti', 'pelajari_teliti'],
            'lain_lain' => 'Segera koordinasikan bersama.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        // 1. Disposisi Dirkum hanya diterima oleh WADIRKUMAD
        $this->assertSame(1, $surat->disposisis()->count());
        $disposisi = $surat->disposisis()->first();
        $this->assertSame($wadir->id, $disposisi->ke_user_id);
        $this->assertSame('wadirkumad', $disposisi->jabatan_tujuan);
        $this->assertSame(['kasubditbinum', 'kabag_pers'], $disposisi->target_jabatan);

        // 2. Notifikasi hanya ke Wadir; Kabag & Kasubdit belum menerima
        $this->assertSame(1, $wadir->fresh()->unreadNotifications()->count());
        $this->assertSame(0, $kabag->fresh()->notifications()->count());
        $this->assertSame(0, $kasubdit->fresh()->notifications()->count());

        // Staf belum bisa mengakses detail surat sebelum diteruskan Wadir
        $this->actingAs($kabag)->get("/surat/{$surat->id}")->assertForbidden();

        // 3. Wadir membuka surat dan melihat target arahan Dirkum otomatis tercentang
        $responseWadir = $this->actingAs($wadir)->get("/surat/{$surat->id}")->assertOk();
        $responseWadir->assertSee('Arahan Dirkumad')
            ->assertSee('KASUBDITBINUM, KABAG PERS');

        $dom = new \DOMDocument;
        @$dom->loadHTML($responseWadir->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//input[@id="wadir-jabatan-kasubditbinum" and @checked]')->length);
        $this->assertSame(1, $xpath->query('//input[@id="wadir-jabatan-kabag_pers" and @checked]')->length);

        // 4. Wadir melakukan disposisi kepada yang diceklis
        $this->actingAs($wadir)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kabag_pers'],
            'perintah' => ['atur_pelaksanaannya'],
            'lain_lain' => 'Laksanakan petunjuk Direktur.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        // 5. Sekarang Kabag Pers menerima disposisi dan bisa melihat disposisi Direktur
        $this->assertSame(1, $kabag->fresh()->unreadNotifications()->count());
        $this->actingAs($kabag)->get("/surat/{$surat->id}")->assertOk()
            ->assertSee('Riwayat Disposisi (2)')
            ->assertSee('Ceklis Pejabat Dituju:')
            ->assertSee('KASUBDITBINUM, KABAG PERS')
            ->assertSee('TINDAK LANJUTI')
            ->assertSee('PELAJARI &amp; TELITI', false)
            ->assertSee('Segera koordinasikan bersama.')
            ->assertSee('ATUR PELAKSANAANNYA')
            ->assertSee('Laksanakan petunjuk Direktur.');
    }

    public function test_multi_penerima_hanya_akun_jabatan_diceklis_mendapat_disposisi_dan_notifikasi(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $kasubdit = $this->buatUser('kasubditbinum');
        $kabag = $this->buatUser('staf', 'kabagpers');
        $tidakDipilih = $this->buatUser('staf', 'staf2');
        $surat = $this->buatSurat($tu);
        $catatan = '<script>alert("arahan")</script>';

        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['wadirkumad'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($wadir)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kasubditbinum', 'kabag_pers'],
            'perintah' => ['laporkan_hasilnya', 'siapkan_bahan_materi'],
            'lain_lain' => $catatan,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(3, $surat->disposisis()->count());
        $this->assertSame('diproses', $surat->fresh()->status);
        foreach (['kasubditbinum' => $kasubdit, 'kabag_pers' => $kabag] as $jabatan => $user) {
            $this->assertDatabaseHas('disposisis', [
                'surat_id' => $surat->id, 'dari_user_id' => $wadir->id,
                'ke_user_id' => $user->id, 'jabatan_tujuan' => $jabatan,
                'instruksi' => "SIAPKAN BAHAN MATERI; LAPORKAN HASILNYA\nLain-lain: ".$catatan,
                'dibaca_at' => null,
            ]);
            $this->assertSame(1, $user->fresh()->unreadNotifications()->count());
            $this->assertSame($surat->id, $user->fresh()->unreadNotifications()->first()->data['surat_id']);
        }
        foreach ([$tidakDipilih, $tu] as $user) {
            $this->assertSame(0, $user->fresh()->notifications()->count());
        }
        $notifDirektur = $direktur->fresh()->notifications()->get();
        $this->assertTrue($notifDirektur->contains(fn ($n) => $n->data['judul'] === 'Disposisi Terkirim'
            && str_contains($n->data['pesan'], 'WADIRKUMAD')));
        $this->assertTrue($notifDirektur->contains(fn ($n) => $n->data['judul'] === 'Disposisi Diteruskan'
            && str_contains($n->data['pesan'], 'KASUBDITBINUM')
            && str_contains($n->data['pesan'], 'KABAG PERS')));

        $this->actingAs($kasubdit)->get("/surat/{$surat->id}")->assertOk()
            ->assertSee('KASUBDITBINUM')->assertSee('SIAPKAN BAHAN MATERI')
            ->assertSee('&lt;script&gt;', false)->assertDontSee($catatan, false);
        $this->actingAs($tidakDipilih)->get('/surat')->assertOk()->assertDontSee($surat->nomor_agenda);
        $this->get('/dashboard')->assertOk()->assertDontSee($surat->nomor_agenda);
        $this->get("/surat/{$surat->id}")->assertForbidden();
        $this->actingAs($tu)->get('/agenda?agenda='.$surat->id)->assertOk()
            ->assertViewHas('barisAgenda', fn ($baris) => $baris->firstWhere('label', 'Wadirkumad')['penerima']->is($wadir)
                && $baris->firstWhere('label', 'Wadirkumad')['tanggal_diterima'] !== null
                && $baris->firstWhere('label', 'Kasubditbinum Ditkumad')['penerima']->is($kasubdit)
                && $baris->firstWhere('label', 'Kabag')['penerima']->is($kabag)
                && $baris->firstWhere('label', 'Irditkumad')['tanggal_diterima'] === null);
    }

    public function test_pilihan_disposisi_tidak_valid_tidak_mengirim_sebagian(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $surat = $this->buatSurat($tu);
        $valid = ['jabatan' => ['wadirkumad'], 'perintah' => ['tindak_lanjuti']];
        $kasus = [
            [['jabatan' => []], 'jabatan'],
            [['jabatan' => 'wadirkumad'], 'jabatan'],
            [['jabatan' => ['tidak_valid']], 'jabatan.0'],
            [['jabatan' => ['wadirkumad', 'wadirkumad']], 'jabatan.0'],
            [['perintah' => []], 'perintah'],
            [['perintah' => ['palsu']], 'perintah.0'],
            [['lain_lain' => str_repeat('a', 2001)], 'lain_lain'],
        ];

        foreach ($kasus as [$perubahan, $error]) {
            $this->actingAs($direktur)->from("/surat/{$surat->id}")
                ->post("/surat/{$surat->id}/disposisi", array_replace($valid, $perubahan))
                ->assertRedirect("/surat/{$surat->id}")->assertSessionHasErrors($error);
            $this->assertSame(0, $surat->disposisis()->count());
            $this->assertSame(0, $wadir->fresh()->notifications()->count());
            $this->assertSame('baru', $surat->fresh()->status);
        }

        $this->actingAs($direktur)->from("/surat/{$surat->id}")->post("/surat/{$surat->id}/disposisi", $valid + ['lain_lain' => 'Catatan tetap tersimpan.'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $surat->disposisis()->count());
    }

    public function test_status_surat_selesai_hanya_setelah_semua_penerima_selesai(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $kasubdit = $this->buatUser('kasubditbinum');
        $surat = $this->buatSurat($tu);
        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['wadirkumad'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertSessionHasNoErrors();
        $this->actingAs($wadir)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kasubditbinum'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertSessionHasNoErrors();
        $pertama = $surat->disposisis()->where('ke_user_id', $wadir->id)->first();
        $kedua = $surat->disposisis()->where('ke_user_id', $kasubdit->id)->first();
        $this->actingAs($direktur)->post("/disposisi/{$pertama->id}/checklist", ['aksi' => 'selesai'])->assertForbidden();
        $this->actingAs($wadir)->post("/disposisi/{$kedua->id}/checklist", ['aksi' => 'selesai'])->assertForbidden();
        $this->post("/disposisi/{$pertama->id}/checklist", ['aksi' => 'selesai'])->assertRedirect();
        $this->assertSame('diproses', $surat->fresh()->status);
        $this->actingAs($kasubdit)->post("/disposisi/{$kedua->id}/checklist", ['aksi' => 'selesai'])->assertRedirect();
        $this->assertSame('selesai', $surat->fresh()->status);
    }

    public function test_riwayat_disposisi_tampil_berurutan_dan_lengkap_meski_sudah_selesai(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $kabag = $this->buatUser('staf', 'kabagpers');
        $surat = $this->buatSurat($tu);

        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['wadirkumad'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertSessionHasNoErrors();

        $this->actingAs($wadir)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kabag_pers'],
            'perintah' => ['catat'],
        ])->assertSessionHasNoErrors();

        $this->actingAs($tu)->get("/surat/{$surat->id}")->assertOk()
            ->assertSee('Riwayat Disposisi (2)')
            ->assertSeeInOrder(['data-urutan="1"', 'Direktur &rarr; WADIRKUMAD', 'data-urutan="2"', 'Wakil Direktur &rarr; KABAG PERS'], false);

        $disposisiWadir = $surat->disposisis()->where('ke_user_id', $wadir->id)->first();
        $this->actingAs($wadir)->post(route('disposisi.checklist', $disposisiWadir), ['aksi' => 'selesai'])->assertRedirect();

        $this->actingAs($tu)->get("/surat/{$surat->id}")->assertOk()
            ->assertSee('Riwayat Disposisi (2)')
            ->assertSeeInOrder(['data-urutan="1"', 'data-urutan="2"'], false)
            ->assertSee('WADIRKUMAD')
            ->assertSee('KABAG PERS');
    }

    public function test_ceklis_agenda_mengikuti_jabatan_yang_dipilih(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $this->buatUser('wadir');
        $this->buatUser('kasubditbinum');
        $surat = $this->buatSurat($tu);
        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['wadirkumad'], 'perintah' => ['tindak_lanjuti'],
        ])->assertSessionHasNoErrors();
        $this->actingAs($tu)->get('/agenda?agenda='.$surat->id)->assertOk()
            ->assertViewHas('barisAgenda', fn ($baris) => $baris->firstWhere('label', 'Wadirkumad')['tanggal_diterima'] !== null
                && $baris->firstWhere('label', 'Kasubditbinum Ditkumad')['tanggal_diterima'] === null);
    }

    public function test_kasubdit_pakai_form_ceklis_jabatan_dan_penerima_dapat_notifikasi(): void
    {
        $tu = $this->buatUser('tu');
        $kasubdit = $this->buatUser('kasubditbinum');
        $wadir = $this->buatUser('wadir');
        $pemegangJabatan = $this->buatUser('staf', 'kabag_pers');
        $surat = $this->buatSurat($tu);
        $this->actingAs($wadir)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kasubditbinum'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertSessionHasNoErrors();

        $response = $this->actingAs($kasubdit)->get("/surat/{$surat->id}")->assertOk()
            ->assertSee('Disposisi Kasubditbinum')
            ->assertSeeInOrder(array_values(Disposisi::jabatanKasubditList()))
            ->assertSeeInOrder(array_values(Disposisi::perintahKasubditList()));

        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(11, $xpath->query('//form[@id="form-disposisi-kasubdit"]//input[@name="jabatan[]"]')->length);
        $this->assertSame(28, $xpath->query('//form[@id="form-disposisi-kasubdit"]//input[@name="perintah[]"]')->length);
        $this->assertSame(0, $xpath->query('//form[@id="form-disposisi-kasubdit"]//select')->length);

        $this->actingAs($kasubdit)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kabag_pers', 'kaprim'],
            'perintah' => ['tindak_lanjuti', 'arsipkan_filekan'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(3, $surat->disposisis()->count());
        $this->assertDatabaseHas('disposisis', [
            'surat_id' => $surat->id, 'dari_user_id' => $kasubdit->id,
            'ke_user_id' => $pemegangJabatan->id, 'jabatan_tujuan' => 'kabag_pers',
            'instruksi' => 'TINDAKLANJUTI; ARSIP / FILEKAN',
        ]);
        $this->assertDatabaseHas('disposisis', [
            'surat_id' => $surat->id, 'dari_user_id' => $kasubdit->id,
            'ke_user_id' => null, 'jabatan_tujuan' => 'kaprim',
            'instruksi' => 'TINDAKLANJUTI; ARSIP / FILEKAN',
        ]);
        $this->assertSame(1, $pemegangJabatan->fresh()->unreadNotifications()->count());
    }

    public function test_jabatan_dan_perintah_kasubdit_sesuai_foto(): void
    {
        $this->assertSame([
            'KABAG TU', 'KABAG PERS', 'KABAG PAM', 'KABAG LOG', 'KABAG PROGGAR',
            'KABAG URDAL', 'KABAG TER', 'KA INFOLAHTA', 'PAKU DITKUMAD',
            'KAPRIM DITKUMAD', 'SPRI',
        ], array_values(Disposisi::jabatanKasubditList()));

        $perintah = array_values(Disposisi::perintahKasubditList());
        $this->assertCount(28, $perintah);
        foreach (['ACC', 'ACARAKAN', 'ATUR PELAKSANAANNYA', 'TINDAKLANJUTI', 'ARSIP / FILEKAN', 'SIAPKAN BAHAN/MATERI'] as $label) {
            $this->assertContains($label, $perintah);
        }
        $this->assertNotContains('MENGHADAP SAYA', $perintah);
        $this->assertNotContains('DUK SESUAI REALITA', $perintah);
    }

    public function test_form_wadir_sesuai_foto_dan_penerima_dapat_notifikasi(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $pemegangJabatan = $this->buatUser('staf', 'kabag_pers');
        $surat = $this->buatSurat($tu);

        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['wadirkumad'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $response = $this->actingAs($wadir)->get("/surat/{$surat->id}")->assertOk()
            ->assertSee('Disposisi Wadirkumad')
            ->assertSeeInOrder(array_values(Disposisi::jabatanWadirList()))
            ->assertSeeInOrder(array_values(Disposisi::perintahList()));

        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(0, $xpath->query('//form[@id="form-disposisi-wadir"]//input[@id="wadir-jabatan-wadirkumad"]')->length);
        $this->assertSame(23, $xpath->query('//form[@id="form-disposisi-wadir"]//input[@name="jabatan[]"]')->length);
        $this->assertSame(30, $xpath->query('//form[@id="form-disposisi-wadir"]//input[@name="perintah[]"]')->length);
        $this->assertSame(0, $xpath->query('//form[@id="form-disposisi-wadir"]//select')->length);

        $this->actingAs($wadir)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kabag_pers', 'irditkumad'],
            'perintah' => ['siapkan_bahan_materi', 'laporkan_hasilnya'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(3, $surat->disposisis()->count());
        $this->assertDatabaseHas('disposisis', [
            'surat_id' => $surat->id, 'dari_user_id' => $wadir->id,
            'ke_user_id' => $pemegangJabatan->id, 'jabatan_tujuan' => 'kabag_pers',
            'instruksi' => 'SIAPKAN BAHAN MATERI; LAPORKAN HASILNYA',
        ]);
        $this->assertDatabaseHas('disposisis', [
            'surat_id' => $surat->id, 'dari_user_id' => $wadir->id,
            'ke_user_id' => null, 'jabatan_tujuan' => 'irditkumad',
        ]);
        $this->assertSame(1, $pemegangJabatan->fresh()->unreadNotifications()->count());
        $this->assertSame('diproses', $surat->fresh()->status);
    }

    public function test_pantau_surat_mengikuti_akses_disposisi_dan_tetap_bisa_difilter(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $staf = $this->buatUser('staf');
        $surat = $this->buatSurat($tu);
        $suratLain = $this->buatSurat($tu, 'R');
        $this->get('/surat')->assertRedirect(route('login'));

        foreach ([$tu, $direktur] as $user) {
            $this->actingAs($user)->get('/surat')->assertOk()
                ->assertSee($surat->nomor_agenda)->assertSee($suratLain->nomor_agenda);
            $this->get('/surat?jenis=R')->assertOk()
                ->assertSee($suratLain->nomor_agenda)->assertDontSee($surat->nomor_agenda);
            $this->get('/surat?q=001/B')->assertOk()
                ->assertSee($surat->nomor_agenda)->assertDontSee($suratLain->nomor_agenda);
        }

        foreach ([$wadir, $staf] as $user) {
            $this->actingAs($user)->get('/surat')->assertOk()
                ->assertDontSee($surat->nomor_agenda)->assertDontSee($suratLain->nomor_agenda);
        }

        $surat->disposisis()->create([
            'dari_user_id' => $direktur->id,
            'ke_user_id' => $wadir->id,
            'jabatan_tujuan' => 'wadirkumad',
            'instruksi' => 'TINDAK LANJUTI',
        ]);

        $this->actingAs($wadir)->get('/surat')->assertOk()
            ->assertSee($surat->nomor_agenda)->assertDontSee($suratLain->nomor_agenda);
        $this->get('/surat?jenis=R')->assertOk()->assertDontSee($suratLain->nomor_agenda);
        $this->get('/surat?q=001/B')->assertOk()->assertSee($surat->nomor_agenda);
        $this->assertSame(0, $wadir->fresh()->notifications()->count());
        $this->assertSame(0, $staf->fresh()->notifications()->count());
    }

    public function test_staf_dan_tu_tidak_bisa_disposisi(): void
    {
        $tu = $this->buatUser('tu');
        $staf = $this->buatUser('staf', 'staf1');
        $surat = $this->buatSurat($tu);

        $this->actingAs($staf)->post("/surat/{$surat->id}/disposisi", [])->assertForbidden();
        $this->actingAs($tu)->post("/surat/{$surat->id}/disposisi", [])->assertForbidden();
    }

    public function test_checklist_hanya_penerima_dan_status_selesai(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $staf = $this->buatUser('staf', 'staf1');
        $stafLain = $this->buatUser('staf', 'staf2');
        $surat = $this->buatSurat($tu);
        $disposisi = Disposisi::create([
            'surat_id' => $surat->id,
            'dari_user_id' => $direktur->id,
            'ke_user_id' => $staf->id,
            'instruksi' => 'Kerjakan.',
        ]);

        $this->actingAs($stafLain)->post("/disposisi/{$disposisi->id}/checklist", ['aksi' => 'dibaca'])
            ->assertForbidden();

        $this->actingAs($staf)->post("/disposisi/{$disposisi->id}/checklist", ['aksi' => 'dibaca'])->assertRedirect();
        $this->actingAs($staf)->post("/disposisi/{$disposisi->id}/checklist", ['aksi' => 'tindak_lanjut'])->assertRedirect();
        $this->actingAs($staf)->post("/disposisi/{$disposisi->id}/checklist", ['aksi' => 'selesai', 'catatan' => 'Sudah dikerjakan.'])->assertRedirect();

        $d = $disposisi->fresh();
        $this->assertNotNull($d->dibaca_at);
        $this->assertNotNull($d->tindak_lanjut_at);
        $this->assertNotNull($d->selesai_at);
        $this->assertSame('Sudah dikerjakan.', $d->catatan);
        $this->assertSame('selesai', $surat->fresh()->status);
    }

    public function test_pantau_surat_hanya_menampilkan_surat_yang_diterima_atau_diteruskan_staf(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $staf = $this->buatUser('staf', 'staf1');
        $surat = $this->buatSurat($tu);
        $suratLain = $this->buatSurat($tu, 'R');
        Disposisi::create([
            'surat_id' => $surat->id,
            'dari_user_id' => $direktur->id,
            'ke_user_id' => $staf->id,
            'instruksi' => 'Kerjakan.',
        ]);

        $this->actingAs($staf)->get("/surat/{$surat->id}")->assertOk();
        $this->get("/surat/{$suratLain->id}")->assertForbidden();
        $this->get("/surat/{$suratLain->id}/file")->assertForbidden();
        $this->get("/surat/{$suratLain->id}/cetak")->assertForbidden();
        $this->get('/surat')->assertOk()
            ->assertSee('Sesuai Akses')->assertSee('Diterima')
            ->assertSee($surat->nomor_agenda)->assertDontSee($suratLain->nomor_agenda)
            ->assertSee('href="'.route('surat.show', $surat).'"', false)
            ->assertDontSee('href="'.route('surat.show', $suratLain).'"', false);
        $this->get('/dashboard')->assertOk()->assertSee($surat->nomor_agenda)->assertDontSee($suratLain->nomor_agenda);
        $this->assertSame(0, $staf->fresh()->notifications()->count());
    }

    public function test_pencarian_no_agenda(): void
    {
        $tu = $this->buatUser('tu');
        $surat = $this->buatSurat($tu);
        $suratLain = $this->buatSurat($tu, 'Kep');

        $this->actingAs($tu)->get('/surat?q=001/B')->assertOk()
            ->assertSee($surat->nomor_agenda)->assertDontSee($suratLain->nomor_agenda);
        $this->actingAs($tu)->get('/surat?q=tidakada')->assertOk()
            ->assertSee('Tidak ada surat ditemukan');
    }

    public function test_agenda_tu_hanya_tabel_kontrol_dengan_urutan_jabatan_foto(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $this->buatUser('wadir');
        $this->buatUser('staf');
        $surat = $this->buatSurat($tu);
        $surat->update(['catatan_tu' => 'Mohon arahan untuk surat ini.']);

        $response = $this->actingAs($tu)->get('/agenda')->assertOk()
            ->assertSee('Kontrol Agenda Diteruskan')
            ->assertSee($surat->nomor_agenda)
            ->assertDontSee('Pilih Nomor Agenda')
            ->assertDontSee('Daftar Agenda')
            ->assertDontSee('DIREKTORAT HUKUM TNI ANGKATAN DARAT')
            ->assertDontSee('LEMBAR DISPOSISI')
            ->assertDontSee('Catatan TU')
            ->assertSee('Pejabat')
            ->assertSee('Tanggal Diterima')
            ->assertSee('Ceklis')
            ->assertDontSee('PARAF')
            ->assertSeeInOrder([
                'Wadirkumad', 'Irditkumad', 'Kasubditbinum Ditkumad',
                'Kasubditbincab Ditkumad', 'Kasubditbindiklat Ditkumad',
                'Kasubditdukkum Ditkumad', 'Kasubditbankumpid Ditkumad',
                'Kasubditbankumperdatun Ditkumad', 'Kasubditbinundang Ditkumad',
                'Kainfolahta Ditkumad', 'Kabag', 'Ketua',
            ])
            ->assertViewHas('agendaTerpilih', fn ($agenda) => $agenda->is($surat));

        $tanggalDiterima = $response->viewData('tanggalDiterimaAgenda');
        $this->assertFalse($tanggalDiterima->has($direktur->id));
        $this->assertFalse($tanggalDiterima->has($tu->id));

        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(14, $xpath->query('//table[@id="kontrol-agenda"]/tbody/tr')->length);
        $this->assertSame(13, $xpath->query('//table[@id="kontrol-agenda"]//input[@type="checkbox" and @disabled]')->length);
        $this->assertSame(0, $xpath->query('//table[@id="kontrol-agenda"]//input[@checked]')->length);
    }

    public function test_agenda_tu_otomatis_tersambung_dengan_jabatan_yang_dituju_wadir_dan_kasubdit(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $kasubdit = $this->buatUser('kasubditbinum');
        foreach (['pamen_ahli_kum_umum', 'pejabat_lain', 'kaprim', 'paku'] as $jabatan) {
            $this->buatUser('staf', $jabatan);
        }
        $surat = $this->buatSurat($tu);

        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['wadirkumad'], 'perintah' => ['tindak_lanjuti'],
        ])->assertSessionHasNoErrors();
        $this->actingAs($wadir)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kasubditbinum', 'pamen_ahli_kum_umum', 'pejabat_lain'], 'perintah' => ['catat'],
        ])->assertSessionHasNoErrors();
        $this->actingAs($kasubdit)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kaprim', 'paku'], 'perintah' => ['tindak_lanjuti'],
        ])->assertSessionHasNoErrors();

        $response = $this->actingAs($tu)->get('/agenda?agenda='.$surat->id)->assertOk();
        $baris = $response->viewData('barisAgenda');

        foreach (['Wadirkumad', 'PAMEN AHLI BID KUM UMUM', 'PEJABAT LAIN', 'KAPRIM DITKUMAD', 'PAKU DITKUMAD'] as $label) {
            $this->assertNotNull($baris->firstWhere('label', $label)['tanggal_diterima'] ?? null, "Baris {$label} tidak tersambung.");
        }
        $this->assertSame('Direktur', $baris->firstWhere('label', 'Wadirkumad')['dari']->first()->roleLabel());
        $this->assertNull($baris->firstWhere('label', 'Kabag')['tanggal_diterima']);

        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(6, $xpath->query('//table[@id="kontrol-agenda"]//input[@checked]')->length);
        $this->assertSame(18, $xpath->query('//table[@id="kontrol-agenda"]/tbody/tr')->length);
    }

    public function test_ceklis_tu_mengikuti_penerima_aktual_bukan_target_direktur_atau_jabatan_tanpa_akun(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $kasubdit = $this->buatUser('kasubditbinum');
        $kabag = $this->buatUser('staf', 'kabag_pers');
        $surat = $this->buatSurat($tu);
        $suratLain = $this->buatSurat($tu, 'R');
        $surat->disposisis()->create([
            'dari_user_id' => $tu->id,
            'ke_user_id' => $direktur->id,
            'instruksi' => 'Surat masuk dari TU.',
        ]);

        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kasubditbinum', 'kabag_pers', 'irditkumad', 'pamen_ahli_kum_umum'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertSessionHasNoErrors();

        $response = $this->actingAs($tu)->get('/agenda?agenda='.$surat->id)->assertOk();
        $baris = $response->viewData('barisAgenda');
        $this->assertNotNull($baris->firstWhere('label', 'Direktur')['tanggal_diterima']);
        $this->assertNotNull($baris->firstWhere('label', 'Wadirkumad')['tanggal_diterima']);
        $this->assertNull($baris->firstWhere('label', 'Kasubditbinum Ditkumad')['tanggal_diterima']);
        $this->assertNull($baris->firstWhere('label', 'Kabag')['tanggal_diterima']);
        $this->assertSame(0, $kasubdit->fresh()->notifications()->count());
        $this->assertSame(0, $kabag->fresh()->notifications()->count());

        $this->actingAs($wadir)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kasubditbinum', 'irditkumad', 'pamen_ahli_kum_umum'],
            'perintah' => ['catat'],
        ])->assertSessionHasNoErrors();

        $response = $this->actingAs($tu)->get('/agenda?agenda='.$surat->id)->assertOk();
        $baris = $response->viewData('barisAgenda');
        $this->assertNotNull($baris->firstWhere('label', 'Kasubditbinum Ditkumad')['tanggal_diterima']);
        $this->assertNull($baris->firstWhere('label', 'Kabag')['tanggal_diterima']);
        $this->assertNull($baris->firstWhere('label', 'Irditkumad')['tanggal_diterima']);
        $this->assertNull($baris->firstWhere('label', 'PAMEN AHLI BID KUM UMUM')['tanggal_diterima']);
        $this->assertFalse($response->viewData('tanggalDiterimaAgenda')->has(''));
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $this->assertSame(3, (new \DOMXPath($dom))->query('//table[@id="kontrol-agenda"]//input[@checked]')->length);

        $pamen = $this->buatUser('staf', 'pamen_ahli_kum_umum');
        $this->travelTo(now()->addDay());
        $this->actingAs($wadir)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['pamen_ahli_kum_umum'],
            'perintah' => ['catat'],
        ])->assertSessionHasNoErrors();
        $disposisiDiterima = $surat->disposisis()->where('ke_user_id', $pamen->id)->sole();
        $response = $this->actingAs($tu)->get('/agenda?agenda='.$surat->id)->assertOk();
        $barisPamen = $response->viewData('barisAgenda')->firstWhere('label', 'PAMEN AHLI BID KUM UMUM');
        $this->assertTrue($barisPamen['penerima']->is($pamen));
        $this->assertTrue($barisPamen['tanggal_diterima']->equalTo($disposisiDiterima->created_at));
        $this->get('/agenda?agenda='.$suratLain->id)->assertOk()
            ->assertViewHas('barisAgenda', fn ($baris) => $baris->every(fn ($b) => $b['tanggal_diterima'] === null));
    }

    public function test_ceklis_hanya_aktif_setelah_surat_diteruskan_ke_akun_tujuan(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $staf = $this->buatUser('staf');
        $surat = $this->buatSurat($tu);

        $this->actingAs($wadir)->get('/surat')->assertOk()->assertDontSee($surat->nomor_agenda);
        $response = $this->actingAs($tu)->get('/agenda?agenda='.$surat->id)->assertOk();
        $this->assertFalse($response->viewData('tanggalDiterimaAgenda')->has($wadir->id));

        $this->travelTo(now()->addDays(2));
        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['wadirkumad'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertRedirect();

        $disposisi = $surat->disposisis()->first();
        $this->assertNull($disposisi->dibaca_at);
        $response = $this->actingAs($tu)->get('/agenda?agenda='.$surat->id)->assertOk();
        $tanggalDiterima = $response->viewData('tanggalDiterimaAgenda');
        $this->assertTrue($tanggalDiterima->get($wadir->id)->equalTo($disposisi->created_at));
        $this->assertFalse($tanggalDiterima->has($staf->id));

        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//tr[@data-penerima-id="'.$wadir->id.'"]//input[@checked and @disabled]')->length);
        $this->assertSame(0, $xpath->query('//tr[@data-penerima-id="'.$staf->id.'"]//input[@checked]')->length);
    }

    public function test_ceklis_penerima_sesuai_inbox_dan_tidak_tercampur_antar_surat(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $pemegangJabatan = $this->buatUser('staf', 'kabag_pers');
        $stafLain = $this->buatUser('staf', 'staf2');
        $surat = $this->buatSurat($tu);
        $suratLain = $this->buatSurat($tu, 'R');

        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['wadirkumad'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertRedirect();
        $this->actingAs($wadir)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kabag_pers'],
            'perintah' => ['siapkan_bahan_materi'],
        ])->assertRedirect();

        $this->actingAs($pemegangJabatan)->get('/dashboard')->assertOk()->assertSee($surat->nomor_agenda);
        $this->get("/surat/{$surat->id}")->assertOk();
        $this->get("/surat/{$suratLain->id}")->assertForbidden();

        $response = $this->actingAs($tu)->get('/agenda?agenda='.$surat->id)->assertOk();
        $this->assertTrue($response->viewData('tanggalDiterimaAgenda')->has($pemegangJabatan->id));
        $this->assertFalse($response->viewData('tanggalDiterimaAgenda')->has($stafLain->id));

        $response = $this->get('/agenda?agenda='.$suratLain->id)->assertOk();
        $this->assertFalse($response->viewData('tanggalDiterimaAgenda')->has($pemegangJabatan->id));
    }

    public function test_tanggal_penerimaan_pertama_tetap_meski_dikirim_ulang_atau_dibaca(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $penerima = $this->buatUser('kasubditbinum');
        $surat = $this->buatSurat($tu);
        $data = [
            'jabatan' => ['kasubditbinum'],
            'perintah' => ['tindak_lanjuti'],
        ];

        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['wadirkumad'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertRedirect();
        $this->actingAs($wadir)->post("/surat/{$surat->id}/disposisi", $data)->assertRedirect();
        $pertama = $surat->disposisis()->where('ke_user_id', $penerima->id)->first();
        $this->travelTo(now()->addDay());
        $this->actingAs($wadir)->post("/surat/{$surat->id}/disposisi", $data)->assertRedirect();
        $this->actingAs($penerima)->post("/disposisi/{$pertama->id}/checklist", ['aksi' => 'dibaca'])->assertRedirect();

        $response = $this->actingAs($tu)->get('/agenda?agenda='.$surat->id)->assertOk();
        $this->assertTrue($response->viewData('tanggalDiterimaAgenda')->get($penerima->id)->equalTo($pertama->created_at));
    }

    public function test_tu_bisa_memilih_agenda_dan_catatan_tetap_aman(): void
    {
        $tu = $this->buatUser('tu');
        $surat = $this->buatSurat($tu);
        $suratLain = $this->buatSurat($tu, 'R');
        $surat->update(['catatan_tu' => '<script>alert("catatan")</script>']);
        $suratLain->update(['catatan_tu' => 'Catatan surat rahasia.']);

        $this->actingAs($tu)->get('/agenda?agenda='.$surat->id)->assertOk()
            ->assertViewHas('agendaTerpilih', fn ($agenda) => $agenda->is($surat))
            ->assertSee($surat->nomor_agenda)
            ->assertDontSee($suratLain->nomor_agenda)
            ->assertDontSee('<script>alert("catatan")</script>', false)
            ->assertDontSee('Catatan surat rahasia.');

        $this->get('/agenda?agenda='.$suratLain->id)->assertOk()
            ->assertViewHas('agendaTerpilih', fn ($agenda) => $agenda->is($suratLain))
            ->assertSee($suratLain->nomor_agenda)
            ->assertDontSee($surat->nomor_agenda);
    }

    public function test_agenda_terpilih_tetap_mengikuti_filter(): void
    {
        $tu = $this->buatUser('tu');
        $surat = $this->buatSurat($tu);
        $suratLain = $this->buatSurat($tu, 'R');

        $this->actingAs($tu)->get('/agenda?jenis=B&agenda='.$surat->id)->assertOk()
            ->assertViewHas('agendaTerpilih', fn ($agenda) => $agenda->is($surat))
            ->assertDontSee($suratLain->nomor_agenda);

        $this->get('/agenda?jenis=B&agenda='.$suratLain->id)->assertOk()
            ->assertViewHas('agendaTerpilih', null)
            ->assertSee('Pilih agenda surat')
            ->assertDontSee($suratLain->nomor_agenda);

        $this->get('/agenda?agenda=999999')->assertOk()
            ->assertViewHas('agendaTerpilih', null)
            ->assertSee('Pilih agenda surat');
    }

    public function test_agenda_tu_tanpa_surat_tetap_menampilkan_tabel_kontrol(): void
    {
        $tu = $this->buatUser('tu');

        $response = $this->actingAs($tu)->get('/agenda')->assertOk()
            ->assertSee('Kontrol Agenda Diteruskan')
            ->assertSee('Belum ada surat — tabel urutan jabatan sebagai acuan.')
            ->assertSeeInOrder(['Direktur', 'Wadirkumad', 'Irditkumad', 'Kasubditbinum Ditkumad', 'Kabag', 'Ketua']);

        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(14, $xpath->query('//table[@id="kontrol-agenda"]/tbody/tr')->length);
        $this->assertSame(13, $xpath->query('//table[@id="kontrol-agenda"]//input[@type="checkbox" and @disabled]')->length);
        $this->assertSame(0, $xpath->query('//table[@id="kontrol-agenda"]//input[@checked]')->length);
    }

    public function test_agenda_dan_pantau_surat_tu_memiliki_menu_terpisah(): void
    {
        $tu = $this->buatUser('tu');
        $surat = $this->buatSurat($tu);

        $this->actingAs($tu)->get('/agenda')->assertOk()
            ->assertSee('href="'.route('agenda.index').'"', false)
            ->assertSee('href="'.route('surat.index').'"', false)
            ->assertSee('Kontrol Agenda Diteruskan')
            ->assertDontSee('Detail Surat')
            ->assertDontSee('name="q"', false)
            ->assertDontSee('name="jenis"', false)
            ->assertDontSee('name="agenda"', false)
            ->assertDontSee('Agenda / Pantau Surat');

        $this->get('/surat')->assertOk()
            ->assertSee('Pantau Surat')
            ->assertSee('Daftar Surat')
            ->assertSee('name="q"', false)
            ->assertSee('name="jenis"', false)
            ->assertSee('href="'.route('surat.show', $surat).'"', false)
            ->assertSee('href="'.route('agenda.index', ['agenda' => $surat->id]).'"', false)
            ->assertDontSee('Kontrol Agenda Diteruskan');

        $this->get('/agenda?q=001/B')->assertOk()
            ->assertSee('Kontrol Agenda Diteruskan')
            ->assertDontSee('action="'.route('agenda.index').'"', false);

        $this->get('/agenda?q=tidakada')->assertOk()
            ->assertSee('Tidak ada surat ditemukan.')
            ->assertDontSee('Kontrol Agenda Diteruskan');

        $this->get('/agenda?jenis=R')->assertOk()
            ->assertSee('Tidak ada surat ditemukan.');
    }

    public function test_pantau_peran_lain_tetap_menggunakan_daftar_surat(): void
    {
        $tu = $this->buatUser('tu');
        $this->buatSurat($tu);

        foreach (['direktur', 'wadir', 'kasubditbinum', 'staf'] as $role) {
            $user = $this->buatUser($role);
            $this->actingAs($user)->get('/surat')->assertOk()
                ->assertSee('Daftar Surat')
                ->assertSee('No Surat')
                ->assertDontSee('DIREKTORAT HUKUM TNI ANGKATAN DARAT')
                ->assertDontSee('Agenda Tata Usaha')
                ->assertDontSee('href="'.route('agenda.index').'"', false);

            $this->get('/agenda')->assertForbidden();
        }
    }

    public function test_agenda_tu_bisa_memilih_surat_di_luar_halaman_aktif(): void
    {
        $tu = $this->buatUser('tu');
        $suratLama = $this->buatSurat($tu);
        $suratLama->forceFill(['created_at' => now()->subDay()])->save();

        for ($i = 0; $i < 10; $i++) {
            $this->buatSurat($tu);
        }

        $response = $this->actingAs($tu)->get('/agenda?jenis=B&agenda='.$suratLama->id)->assertOk()
            ->assertViewHas('agendaTerpilih', fn ($agenda) => $agenda->is($suratLama));

        $surats = $response->viewData('surats');
        $this->assertCount(10, $surats);
        $this->assertSame(11, $surats->total());
        $this->assertStringContainsString('jenis=B', $surats->nextPageUrl());

        $this->get('/agenda?jenis=B&page=2')->assertOk()
            ->assertViewHas('agendaTerpilih', fn ($agenda) => $agenda->is($suratLama));
    }

    public function test_cetak_pdf_lembar_agenda(): void
    {
        $tu = $this->buatUser('tu');
        $surat = $this->buatSurat($tu);

        $response = $this->actingAs($tu)->get("/surat/{$surat->id}/cetak");
        $response->assertOk();
        $this->assertStringContainsString('pdf', $response->headers->get('content-type'));
    }

    public function test_notifikasi_dibaca_dan_redirect_ke_surat(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $surat = $this->buatSurat($tu);
        $direktur->notify(new SuratMasuk($surat));
        $notifikasi = $direktur->fresh()->unreadNotifications()->first();

        $this->actingAs($direktur)->get("/notifikasi/{$notifikasi->id}/baca")
            ->assertRedirect(route('surat.show', $surat->id));
        $this->assertNotNull($notifikasi->fresh()->read_at);
    }

    public function test_registrasi_akun_baru_otomatis_staf(): void
    {
        $this->get('/register')->assertOk()->assertSee('Daftar Akun')->assertSee('Jabatan');

        $this->post('/register', [
            'name' => 'Budi Santoso',
            'username' => 'budi',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'role' => 'direktur',
        ])->assertSessionHasErrors('jabatan');

        $this->post('/register', [
            'name' => 'Budi Santoso',
            'username' => 'budi',
            'jabatan' => 'kabag_tu',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'role' => 'direktur',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $user = User::where('username', 'budi')->first();
        $this->assertSame('staf', $user->role);
        $this->assertSame('kabag_tu', $user->jabatan);
        $this->assertSame('KABAG TU', $user->sebutanAktor());
    }

    public function test_akun_yang_baru_didaftarkan_menerima_notifikasi_disposisi_jabatannya(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $surat = $this->buatSurat($tu);

        $this->post('/register', [
            'name' => 'Kepala Bagian Tata Usaha',
            'username' => 'kabagtu',
            'jabatan' => 'kabag_tu',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertRedirect(route('dashboard'));

        $akunBaru = User::where('username', 'kabagtu')->first();
        $this->assertSame(0, $akunBaru->notifications()->count());

        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['wadirkumad'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($wadir)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kabag_tu'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $disposisi = $surat->disposisis()->where('ke_user_id', $akunBaru->id)->first();
        $this->assertNotNull($disposisi);
        $this->assertSame('kabag_tu', $disposisi->jabatan_tujuan);

        $notifikasi = $akunBaru->fresh()->unreadNotifications()->get();
        $this->assertSame(1, $notifikasi->count());
        $this->assertSame('Disposisi Baru', $notifikasi->first()->data['judul']);

        $this->actingAs($akunBaru)->get('/dashboard')->assertOk()
            ->assertSee('Dashboard KABAG TU')
            ->assertSee($surat->nomor_agenda)
            ->assertSee('TINDAK LANJUTI');
    }

    public function test_semua_akun_dengan_role_wadir_menerima_disposisi_direktur(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $surat = $this->buatSurat($tu);

        $wadirKedua = User::create([
            'name' => 'Wakil Direktur Kedua',
            'username' => 'wadir2',
            'jabatan' => 'wadirkumad',
            'password' => 'password123',
            'role' => 'wadir',
        ]);

        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['wadirkumad'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, $surat->disposisis()->where('jabatan_tujuan', 'wadirkumad')->count());
        $this->assertSame(1, $wadir->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $wadirKedua->fresh()->unreadNotifications()->count());
    }

    public function test_akun_staf_dengan_jabatan_wadir_tidak_menerima_disposisi_direktur(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $staf = $this->buatUser('staf', 'wadirkumad');
        $staf->update(['jabatan' => 'wadirkumad']);
        $surat = $this->buatSurat($tu);

        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kasubditbinum', 'kabag_pers'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, $surat->disposisis()->count());
        $this->assertSame($wadir->id, $surat->disposisis()->first()->ke_user_id);
        $this->assertSame(0, $staf->fresh()->notifications()->count());
        $this->actingAs($staf)->get("/surat/{$surat->id}")->assertForbidden();
    }

    public function test_tanpa_akun_wadir_disposisi_direktur_tidak_dialihkan_ke_staf(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $staf = $this->buatUser('staf', 'wadirkumad');
        $staf->update(['jabatan' => 'wadirkumad']);
        $surat = $this->buatSurat($tu);

        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kabag_pers'],
            'perintah' => ['catat'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $disposisi = $surat->disposisis()->sole();
        $this->assertNull($disposisi->ke_user_id);
        $this->assertSame('wadirkumad', $disposisi->jabatan_tujuan);
        $this->assertSame(['kabag_pers'], $disposisi->target_jabatan);
        $this->assertSame(0, $staf->fresh()->notifications()->count());
    }

    public function test_registrasi_username_kembar_ditolak(): void
    {
        $this->buatUser('tu');

        $this->post('/register', [
            'name' => 'Duplikat',
            'username' => 'tu',
            'jabatan' => 'kabag_tu',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors('username');
    }

    public function test_pdf_lebih_dari_5mb_ditolak(): void
    {
        Storage::fake('local');
        $tu = $this->buatUser('tu');

        $this->actingAs($tu)->post('/surat', [
            'nomor_surat' => 'X/1/2026',
            'tanggal_surat' => '2026-11-05',
            'tanggal_terima' => '2026-11-10',
            'asal_surat' => 'Instansi',
            'perihal' => 'Perihal',
            'jenis_surat' => 'B',
            'file_pdf' => UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf'),
        ])->assertSessionHasErrors('file_pdf');

        $this->assertSame(0, Surat::count());
    }

    public function test_unduh_pdf_surat_hanya_untuk_tu(): void
    {
        Storage::fake('local');
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $surat = $this->buatSurat($tu);
        $surat->update(['file_pdf' => 'surat/contoh.pdf']);
        Storage::disk('local')->put('surat/contoh.pdf', '%PDF-1.4 contoh');

        $this->actingAs($tu)->get(route('surat.unduh', $surat))
            ->assertOk()
            ->assertDownload('Surat-001-B-XI-2026.pdf');

        $this->actingAs($direktur)->get(route('surat.unduh', $surat))->assertForbidden();
        $this->actingAs($wadir)->get(route('surat.unduh', $surat))->assertForbidden();
    }

    public function test_dashboard_sesuai_peran_aktor(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $staf = $this->buatUser('staf', 'staf1');

        $suratBaru = $this->buatSurat($tu);
        $suratStaf = $this->buatSurat($tu, 'R');
        Disposisi::create([
            'surat_id' => $suratStaf->id,
            'dari_user_id' => $direktur->id,
            'ke_user_id' => $staf->id,
            'instruksi' => 'Kerjakan.',
        ]);

        $this->actingAs($staf)->get('/dashboard')->assertOk()
            ->assertSee('Surat &amp; Tugas Saya', false)
            ->assertSee($suratStaf->nomor_agenda);

        $this->actingAs($direktur)->get('/dashboard')->assertOk()
            ->assertSee('Menunggu Disposisi Anda')
            ->assertSee($suratBaru->nomor_agenda);

        $this->actingAs($tu)->get('/dashboard')->assertOk()->assertSee('Surat Terbaru');
    }

    public function test_dashboard_staf_tanpa_jabatan_mengikuti_akun_yang_login(): void
    {
        $user = User::create([
            'name' => 'Petugas Arsip',
            'username' => 'arsip1',
            'password' => 'password123',
            'role' => 'staf',
        ]);

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('Dashboard Petugas Arsip')
            ->assertSee('arsip1');
    }

    public function test_dashboard_kabag_mengikuti_username_dan_bisa_ceklist(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $kabag = $this->buatUser('staf', 'kabagpers');
        $surat = $this->buatSurat($tu);

        $disposisi = Disposisi::create([
            'surat_id' => $surat->id,
            'dari_user_id' => $direktur->id,
            'ke_user_id' => $kabag->id,
            'jabatan_tujuan' => 'kabag_pers',
            'instruksi' => 'TINDAKLANJUTI',
        ]);

        $this->actingAs($kabag)->get('/dashboard')->assertOk()
            ->assertSee('Dashboard KABAG PERS')
            ->assertSee('kabagpers')
            ->assertSee('Surat &amp; Tugas Saya', false)
            ->assertSee($surat->nomor_agenda)
            ->assertSee('TINDAKLANJUTI')
            ->assertSee(route('disposisi.checklist', $disposisi))
            ->assertViewHas('stats', fn ($stats) => $stats['diterima'] === 1 && $stats['belum_dibaca'] === 1);

        $this->actingAs($kabag)->post(route('disposisi.checklist', $disposisi), ['aksi' => 'dibaca'])
            ->assertRedirect();
        $this->assertNotNull($disposisi->fresh()->dibaca_at);
    }

    public function test_dashboard_tu_dan_pimpinan_punya_tampilan_sendiri(): void
    {
        $tu = $this->buatUser('tu');
        $this->buatUser('direktur');

        $this->actingAs($tu)->get('/dashboard')->assertOk()
            ->assertSee('Dashboard Tata Usaha')
            ->assertSee('Input Surat Baru')
            ->assertSee(route('agenda.index'));

        $this->actingAs(User::where('role', 'direktur')->first())->get('/dashboard')->assertOk()
            ->assertSee('Dashboard Direktur')
            ->assertSee('Halaman Disposisi');
    }

    public function test_halaman_disposisi_untuk_pimpinan_serta_ada_di_menu(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $kasubdit = $this->buatUser('kasubditbinum');
        $staf = $this->buatUser('staf', 'staf1');
        $surat = $this->buatSurat($tu);

        $this->actingAs($tu)->get('/disposisi')->assertForbidden();
        $this->actingAs($staf)->get('/disposisi')->assertForbidden();

        $this->actingAs($direktur)->get('/disposisi')->assertOk()
            ->assertSee('Disposisi Direktur')
            ->assertSee('Menunggu Disposisi Anda')
            ->assertSee($surat->nomor_agenda)
            ->assertSee('Beri Disposisi');

        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['wadirkumad'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($wadir)->get('/disposisi')->assertOk()
            ->assertSee('Disposisi Wakil Direktur')
            ->assertSee('Menunggu Disposisi Anda')
            ->assertSee($surat->nomor_agenda);

        $this->actingAs($kasubdit)->get('/disposisi')->assertOk()
            ->assertSee('Disposisi Kasubdit Binum')
            ->assertSee('Menunggu Disposisi Anda')
            ->assertDontSee($surat->nomor_agenda);

        $this->actingAs($wadir)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kasubditbinum'],
            'perintah' => ['catat'],
        ])->assertSessionHasNoErrors();
        $this->actingAs($kasubdit)->get('/disposisi')->assertOk()
            ->assertSee($surat->nomor_agenda);

        foreach ([$direktur, $wadir, $kasubdit] as $pimpinan) {
            $this->actingAs($pimpinan)->get('/dashboard')->assertOk()
                ->assertSee('href="'.route('disposisi.index').'"', false);
        }
        $this->actingAs($tu)->get('/dashboard')->assertOk()
            ->assertDontSee('href="'.route('disposisi.index').'"', false);
    }

    public function test_surat_diteruskan_direktur_muncul_di_disposisi_wadir_dan_progresnya_kembali_ke_direktur(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $surat = $this->buatSurat($tu);

        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['wadirkumad'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $disposisi = $surat->disposisis()->where('ke_user_id', $wadir->id)->first();
        $this->assertNotNull($disposisi);
        $this->assertSame(1, $wadir->fresh()->unreadNotifications()->count());

        $this->actingAs($wadir)->get('/disposisi')->assertOk()
            ->assertSee('Surat Diteruskan ke Saya')
            ->assertSee($surat->nomor_agenda)
            ->assertSee('TINDAK LANJUTI')
            ->assertSee('Disposisi Lanjutan')
            ->assertViewHas('stats', fn ($stats) => $stats['diteruskan'] === 1);

        $this->actingAs($wadir)->get('/dashboard')->assertOk()
            ->assertSee('Menunggu Disposisi Anda')
            ->assertSee($surat->nomor_agenda);

        $notifDirektur = $direktur->fresh()->notifications()->latest()->get();
        $this->assertSame(1, $notifDirektur->count());
        $this->assertSame('Disposisi Terkirim', $notifDirektur->first()->data['judul']);
        $this->assertStringContainsString('WADIRKUMAD', $notifDirektur->first()->data['pesan']);

        $this->actingAs($wadir)->post(route('disposisi.checklist', $disposisi), ['aksi' => 'tindak_lanjut'])->assertRedirect();
        $this->actingAs($wadir)->post(route('disposisi.checklist', $disposisi), ['aksi' => 'selesai'])->assertRedirect();

        $progres = $direktur->fresh()->notifications()->get();
        $this->assertSame(3, $progres->count());
        $progresSelesai = $progres->first(fn ($n) => $n->data['judul'] === 'Progres Disposisi'
            && str_contains($n->data['pesan'], 'selesai'));
        $this->assertNotNull($progresSelesai);
        $this->assertSame($surat->id, $progresSelesai->data['surat_id']);
        $this->assertSame('selesai', $surat->fresh()->status);
    }

    public function test_halaman_disposisi_menampilkan_progres_global_penerima(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $kasubdit = $this->buatUser('kasubditbinum');
        $surat = $this->buatSurat($tu);
        $suratWadir = $this->buatSurat($tu, 'R');

        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['wadirkumad'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertSessionHasNoErrors();
        $this->actingAs($wadir)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kasubditbinum'], 'perintah' => ['tindak_lanjuti'],
        ])->assertSessionHasNoErrors();
        $this->actingAs($wadir)->post("/surat/{$suratWadir->id}/disposisi", [
            'jabatan' => ['kabag_pers'], 'perintah' => ['tindak_lanjuti'],
        ])->assertSessionHasNoErrors();

        $pertama = $surat->disposisis()->where('ke_user_id', $wadir->id)->first();
        $kedua = $surat->disposisis()->where('ke_user_id', $kasubdit->id)->first();
        $this->actingAs($wadir)->post(route('disposisi.checklist', $pertama), ['aksi' => 'dibaca'])->assertRedirect();

        $response = $this->actingAs($direktur)->get('/disposisi')->assertOk()
            ->assertSee('Monitor Progres Disposisi (Global)')
            ->assertSee('WADIRKUMAD')->assertSee('KASUBDITBINUM')
            ->assertSee('TINDAK LANJUTI')
            ->assertSee($surat->nomor_agenda)
            ->assertDontSee($suratWadir->nomor_agenda);

        $stats = $response->viewData('stats');
        $this->assertSame(0, $stats['menunggu']);
        $this->assertSame(1, $stats['surat']);
        $this->assertSame(1, $stats['belum_dibaca']);
        $this->assertSame(0, $stats['selesai']);

        $this->actingAs($wadir)->post(route('disposisi.checklist', $pertama), ['aksi' => 'tindak_lanjut'])->assertRedirect();
        $this->actingAs($wadir)->post(route('disposisi.checklist', $pertama), ['aksi' => 'selesai'])->assertRedirect();
        $this->actingAs($kasubdit)->post(route('disposisi.checklist', $kedua), ['aksi' => 'dibaca'])->assertRedirect();
        $this->actingAs($kasubdit)->post(route('disposisi.checklist', $kedua), ['aksi' => 'selesai'])->assertRedirect();

        $response = $this->actingAs($direktur)->get('/disposisi')->assertOk();
        $stats = $response->viewData('stats');
        $this->assertSame(0, $stats['belum_dibaca']);
        $this->assertSame(2, $stats['selesai']);
        $this->assertSame('selesai', $surat->fresh()->status);
    }

    public function test_wadir_meneruskan_surat_memberi_notifikasi_ke_penerima_dan_pengirim_sebelumnya(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $kabag = $this->buatUser('staf', 'kabagpers');
        $surat = $this->buatSurat($tu);

        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['wadirkumad'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertSessionHasNoErrors();

        $this->actingAs($wadir)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kabag_pers'],
            'perintah' => ['catat'],
        ])->assertSessionHasNoErrors();

        $notifKabag = $kabag->fresh()->unreadNotifications()->get();
        $this->assertSame(1, $notifKabag->count());
        $this->assertSame('Disposisi Baru', $notifKabag->first()->data['judul']);

        $notifDiteruskan = $direktur->fresh()->notifications()->get()
            ->first(fn ($n) => $n->data['judul'] === 'Disposisi Diteruskan');
        $this->assertNotNull($notifDiteruskan);
        $this->assertStringContainsString('Wakil Direktur', $notifDiteruskan->data['pesan']);
        $this->assertStringContainsString('KABAG PERS', $notifDiteruskan->data['pesan']);
    }

    public function test_notifikasi_dapat_dihapus_per_item_dan_semua(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $surat = $this->buatSurat($tu);
        $direktur->notify(new SuratMasuk($surat));
        $direktur->notify(new SuratMasuk($surat));
        $wadir->notify(new SuratMasuk($surat));

        $milikDirektur = $direktur->notifications()->first();
        $milikWadir = $wadir->notifications()->first();

        $this->actingAs($direktur)->delete(route('notifikasi.destroy', $milikWadir->id))->assertNotFound();

        $this->actingAs($direktur)->delete(route('notifikasi.destroy', $milikDirektur->id))->assertRedirect();
        $this->assertSame(1, $direktur->fresh()->notifications()->count());
        $this->assertSame(1, $wadir->fresh()->notifications()->count());

        $this->actingAs($direktur)->delete(route('notifikasi.destroySemua'))->assertRedirect();
        $this->assertSame(0, $direktur->fresh()->notifications()->count());
        $this->assertSame(1, $wadir->fresh()->notifications()->count());
    }

    public function test_hapus_riwayat_disposisi_hanya_oleh_aktor_terkait_dan_status_dihitung_ulang(): void
    {
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $kasubdit = $this->buatUser('kasubditbinum');
        $surat = $this->buatSurat($tu);

        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['wadirkumad'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertSessionHasNoErrors();
        $this->actingAs($wadir)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['kasubditbinum'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertSessionHasNoErrors();

        $keWadir = $surat->disposisis()->where('ke_user_id', $wadir->id)->first();
        $keKasubdit = $surat->disposisis()->where('ke_user_id', $kasubdit->id)->first();

        $this->actingAs($tu)->delete(route('disposisi.destroy', $keWadir))->assertForbidden();

        $this->actingAs($wadir)->delete(route('disposisi.destroy', $keWadir))->assertRedirect();
        $this->assertDatabaseMissing('disposisis', ['id' => $keWadir->id]);
        $this->assertSame('diproses', $surat->fresh()->status);

        $this->actingAs($kasubdit)->delete(route('disposisi.destroy', $keKasubdit))->assertRedirect();
        $this->assertSame(0, $surat->disposisis()->count());
        $this->assertSame('baru', $surat->fresh()->status);
    }

    public function test_hapus_surat_menghapus_disposisi_notifikasi_dan_file(): void
    {
        Storage::fake('local');
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $wadir = $this->buatUser('wadir');
        $staf = $this->buatUser('staf');
        $surat = $this->buatSurat($tu);
        $surat->update(['file_pdf' => 'surat/contoh.pdf']);
        Storage::disk('local')->put('surat/contoh.pdf', 'pdf');

        $this->actingAs($direktur)->post("/surat/{$surat->id}/disposisi", [
            'jabatan' => ['wadirkumad'],
            'perintah' => ['tindak_lanjuti'],
        ])->assertSessionHasNoErrors();

        $this->assertGreaterThan(0, $wadir->fresh()->notifications()->count());

        $this->actingAs($staf)->delete("/surat/{$surat->id}")->assertForbidden();

        $this->actingAs($wadir)->delete("/surat/{$surat->id}")->assertRedirect('/surat');

        $this->assertDatabaseMissing('surats', ['id' => $surat->id]);
        $this->assertDatabaseMissing('disposisis', ['surat_id' => $surat->id]);
        $this->assertDatabaseCount('notifications', 0);
        Storage::disk('local')->assertMissing('surat/contoh.pdf');
    }

    public function test_kosongkan_semua_surat_hanya_tu_dan_akun_tetap(): void
    {
        Storage::fake('local');
        $tu = $this->buatUser('tu');
        $direktur = $this->buatUser('direktur');
        $this->buatSurat($tu);
        Storage::disk('local')->put('surat/a.pdf', 'pdf');

        $this->actingAs($direktur)->post('/surat/kosongkan')->assertForbidden();
        $this->actingAs($tu)->post('/surat/kosongkan')->assertRedirect();

        $this->assertDatabaseCount('surats', 0);
        $this->assertDatabaseCount('disposisis', 0);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('users', 2);
        Storage::disk('local')->assertMissing('surat/a.pdf');
    }
}
