<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'username', 'email', 'password', 'role', 'jabatan'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isPimpinan(): bool
    {
        return in_array($this->role, ['direktur', 'wadir', 'kasubditbinum']);
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'tu' => 'Tata Usaha',
            'direktur' => 'Direktur',
            'wadir' => 'Wakil Direktur',
            'kasubditbinum' => 'Kasubdit Binum',
            'super_admin' => 'Super Admin',
            default => 'Staf',
        };
    }

    public function jabatanNama(): ?string
    {
        if ($this->jabatan) {
            $label = Disposisi::label($this->jabatan);
            if ($label !== '-') {
                return $label;
            }
        }

        return Disposisi::labelDariUsername($this->username);
    }

    public function sebutanAktor(): string
    {
        return $this->jabatanNama() ?? $this->roleLabel();
    }
}
