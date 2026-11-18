<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Disposisi extends Model
{
    protected $fillable = [
        'surat_id', 'dari_user_id', 'ke_user_id', 'jabatan_tujuan', 'target_jabatan', 'instruksi',
        'catatan', 'dibaca_at', 'tindak_lanjut_at', 'selesai_at',
    ];

    public static function jabatanList(): array
    {
        return [
            'wadirkumad' => 'WADIRKUMAD',
            'ketua_sthm' => 'KETUA STHM',
            'danpusdikkum' => 'DANPUSDIKKUM',
            'irditkumad' => 'IRDITKUMAD',
            'pamen_ahli_kum_umum' => 'PAMEN AHLI BID KUM UMUM',
            'pamen_ahli_kum_milhum' => 'PAMEN AHLI BID KUM MILHUM',
            'kasubditbinum' => 'KASUBDITBINUM',
            'kasubditbincab' => 'KASUBDITBINCAB',
            'kasubditbindiklat' => 'KASUBDITBINDIKLAT',
            'kasubditdukkum' => 'KASUBDITDUKKUM',
            'kasubditbankumpid' => 'KASUBDITBANKUMPID',
            'kasubditbankumperdatun' => 'KASUBDITBANKUMPERDATUN',
            'kasubditbinundang' => 'KASUBDITBINUNDANG',
            'kabag_tu' => 'KABAG TU',
            'kabag_pers' => 'KABAG PERS',
            'kabag_pam' => 'KABAG PAM',
            'kabag_log' => 'KABAG LOG',
            'kabag_proggar' => 'KABAG PROGGAR',
            'kabag_urdal' => 'KABAG URDAL',
            'kabag_ter' => 'KABAG TER',
            'kainfolahta' => 'KAINFOLAHTA',
            'paku' => 'PAKU',
            'spri_adc' => 'SPRI/ADC',
            'pejabat_lain' => 'PEJABAT LAIN',
        ];
    }

    public static function jabatanDirekturList(): array
    {
        return self::jabatanList();
    }

    public static function jabatanWadirList(): array
    {
        return array_diff_key(self::jabatanList(), ['wadirkumad' => true]);
    }

    public static function jabatanKasubditList(): array
    {
        return [
            'kabag_tu' => 'KABAG TU',
            'kabag_pers' => 'KABAG PERS',
            'kabag_pam' => 'KABAG PAM',
            'kabag_log' => 'KABAG LOG',
            'kabag_proggar' => 'KABAG PROGGAR',
            'kabag_urdal' => 'KABAG URDAL',
            'kabag_ter' => 'KABAG TER',
            'kainfolahta' => 'KA INFOLAHTA',
            'paku' => 'PAKU DITKUMAD',
            'kaprim' => 'KAPRIM DITKUMAD',
            'spri_adc' => 'SPRI',
        ];
    }

    public static function perintahKasubditList(): array
    {
        return [
            'acc' => 'ACC',
            'acarakan' => 'ACARAKAN',
            'bantu' => 'BANTU',
            'balas' => 'BALAS',
            'catat' => 'CATAT',
            'hadir' => 'HADIR',
            'tidak_hadir' => 'TIDAK HADIR',
            'wakili' => 'WAKILI',
            'atur_pelaksanaannya' => 'ATUR PELAKSANAANNYA',
            'pelajari_teliti' => 'PELAJARI & TELITI',
            'tanggapan_saran' => 'TANGGAPAN & SARAN',
            'tindak_lanjuti' => 'TINDAKLANJUTI',
            'sebagai_bahan' => 'SEBAGAI BAHAN',
            'arsipkan_filekan' => 'ARSIP / FILEKAN',
            'ump' => 'UMP',
            'udk' => 'UDK',
            'udl' => 'UDL',
            'siapkan_bahan_materi' => 'SIAPKAN BAHAN/MATERI',
            'ikuti_perkembangannya' => 'IKUTI PERKEMBANGANNYA',
            'laporkan_hasilnya' => 'LAPORKAN HASILNYA',
            'selesaikan' => 'SELESAIKAN',
            'koordinasikan' => 'KOORDINASIKAN',
            'evaluasi' => 'EVALUASI',
            'tunjuk' => 'TUNJUK',
            'pedomani' => 'PEDOMANI',
            'proses' => 'PROSES',
            'umumkan' => 'UMUMKAN',
            'ingatkan' => 'INGATKAN',
        ];
    }

    public static function label(?string $kode, ?string $pengirim = null): string
    {
        if ($kode === null) {
            return '-';
        }

        if ($pengirim === 'kasubditbinum') {
            return self::jabatanKasubditList()[$kode] ?? self::jabatanList()[$kode] ?? $kode;
        }

        return self::jabatanList()[$kode] ?? self::jabatanKasubditList()[$kode] ?? $kode;
    }

    public static function labelDariUsername(string $username): ?string
    {
        $rapi = self::rapikan($username);

        foreach ([self::jabatanList(), self::jabatanKasubditList()] as $daftar) {
            foreach ($daftar as $kode => $label) {
                if (self::rapikan($kode) === $rapi || self::rapikan($label) === $rapi) {
                    return $label;
                }
            }
        }

        return null;
    }

    public static function semuaJabatan(): array
    {
        return self::jabatanList() + self::jabatanKasubditList();
    }

    public static function penerimaJabatan(): array
    {
        $users = User::whereNotIn('role', ['tu', 'direktur', 'super_admin'])->orderBy('id')->get();
        $roleJabatan = [
            'wadirkumad' => 'wadir',
            'kasubditbinum' => 'kasubditbinum',
        ];

        $penerima = [];
        foreach (array_keys(self::semuaJabatan()) as $kode) {
            $kodeRapi = self::rapikan($kode);
            $penerima[$kode] = $users->filter(function (User $user) use ($kode, $kodeRapi, $roleJabatan) {
                return $user->jabatan === $kode
                    || $user->username === $kode
                    || self::rapikan($user->username) === $kodeRapi
                    || (isset($roleJabatan[$kode]) && $user->role === $roleJabatan[$kode]);
            })->unique('id')->values();
        }

        return $penerima;
    }

    private static function rapikan(string $teks): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($teks));
    }

    public static function perintahList(): array
    {
        return [
            'acc' => 'ACC',
            'acarakan' => 'ACARAKAN',
            'bantu' => 'BANTU',
            'balas' => 'BALAS',
            'catat' => 'CATAT',
            'hadir' => 'HADIR',
            'tidak_hadir' => 'TIDAK HADIR',
            'wakili' => 'WAKILI',
            'menghadap_saya' => 'MENGHADAP SAYA',
            'pelajari_teliti' => 'PELAJARI & TELITI',
            'tanggapan_saran' => 'TANGGAPAN & SARAN',
            'tindak_lanjuti' => 'TINDAK LANJUTI',
            'sebagai_bahan' => 'SEBAGAI BAHAN',
            'duk_sesuai_realita' => 'DUK SESUAI REALITA',
            'ump' => 'UMP',
            'udk' => 'UDK',
            'udl' => 'UDL',
            'siapkan_bahan_materi' => 'SIAPKAN BAHAN MATERI',
            'ikuti_perkembangannya' => 'IKUTI PERKEMBANGANNYA',
            'laporkan_hasilnya' => 'LAPORKAN HASILNYA',
            'selesaikan' => 'SELESAIKAN',
            'koordinasikan' => 'KOORDINASIKAN',
            'evaluasi' => 'EVALUASI',
            'tunjuk' => 'TUNJUK',
            'pedomani' => 'PEDOMANI',
            'proses' => 'PROSES',
            'umumkan' => 'UMUMKAN',
            'ingatkan' => 'INGATKAN',
            'arsipkan_filekan' => 'ARSIPKAN FILEKAN',
            'atur_pelaksanaannya' => 'ATUR PELAKSANAANNYA',
        ];
    }

    public function targetJabatanLabels(): array
    {
        if (empty($this->target_jabatan) || ! is_array($this->target_jabatan)) {
            return [];
        }

        return array_values(array_intersect_key(self::jabatanList(), array_flip($this->target_jabatan)));
    }

    public function targetJabatanTeks(): string
    {
        return implode(', ', $this->targetJabatanLabels());
    }

    protected function casts(): array
    {
        return [
            'target_jabatan' => 'array',
            'dibaca_at' => 'datetime',
            'tindak_lanjut_at' => 'datetime',
            'selesai_at' => 'datetime',
        ];
    }

    public function surat()
    {
        return $this->belongsTo(Surat::class);
    }

    public function dari()
    {
        return $this->belongsTo(User::class, 'dari_user_id');
    }

    public function ke()
    {
        return $this->belongsTo(User::class, 'ke_user_id');
    }
}
