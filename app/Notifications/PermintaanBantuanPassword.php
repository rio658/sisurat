<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PermintaanBantuanPassword extends Notification
{
    use Queueable;

    public function __construct(public array $permintaan) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'judul' => 'Permintaan Bantuan Password',
            'pesan' => $this->permintaan['username'].' meminta bantuan password. Verifikasi identitas pemohon sebelum mengganti password.',
            'url' => route('admin.akun.index', ['q' => $this->permintaan['username']]),
            'bantuan_password' => $this->permintaan,
        ];
    }
}
