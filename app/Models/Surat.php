<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Surat extends Model
{
    protected $fillable = [
        'no_urut', 'tahun', 'nomor_agenda', 'jenis_surat', 'nomor_surat',
        'tanggal_surat', 'tanggal_terima', 'asal_surat', 'perihal', 'catatan_tu',
        'file_pdf', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_surat' => 'date',
            'tanggal_terima' => 'date',
        ];
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function disposisis()
    {
        return $this->hasMany(Disposisi::class)->latest();
    }

    public function scopeUntukUser($query, User $user)
    {
        if (! in_array($user->role, ['tu', 'direktur'], true)) {
            $query->whereHas('disposisis', fn ($q) => $q
                ->where('ke_user_id', $user->id)
                ->orWhere('dari_user_id', $user->id));
        }

        return $query;
    }

    public function segarkanStatus(): void
    {
        $daftar = $this->disposisis()->get();

        if ($daftar->isEmpty()) {
            $this->update(['status' => 'baru']);
        } elseif ($daftar->every(fn (Disposisi $d) => $d->selesai_at !== null)) {
            $this->update(['status' => 'selesai']);
        } else {
            $this->update(['status' => 'diproses']);
        }
    }

    public static function buatNomorAgenda(string $jenis, $tanggalTerima): array
    {
        $tanggal = Carbon::parse($tanggalTerima);
        $tahun = (int) $tanggal->year;
        $urut = ((int) static::where('tahun', $tahun)->lockForUpdate()->max('no_urut')) + 1;
        $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$tanggal->month - 1];

        return [
            'no_urut' => $urut,
            'tahun' => $tahun,
            'nomor_agenda' => sprintf('%03d', $urut).'/'.$jenis.'/'.$romawi.'/'.$tahun,
        ];
    }

    public static function jenisList(): array
    {
        return [
            'B' => 'B (Biasa)',
            'R' => 'R (Rahasia)',
            'Kep' => 'Kep (Keputusan)',
            'Sprin' => 'Sprin (Surat Perintah)',
            'St' => 'St (Surat Telegram)',
        ];
    }

    public function jenisLabel(): string
    {
        return static::jenisList()[$this->jenis_surat] ?? $this->jenis_surat;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'baru' => 'Terkirim ke Direktur',
            'diproses' => 'Diproses',
            'selesai' => 'Selesai',
            default => $this->status,
        };
    }
}
