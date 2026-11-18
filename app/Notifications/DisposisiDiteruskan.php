<?php

namespace App\Notifications;

use App\Models\Surat;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DisposisiDiteruskan extends Notification
{
    use Queueable;

    public function __construct(public Surat $surat, public User $pengirim, public array $tujuan)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'surat_id' => $this->surat->id,
            'judul' => 'Disposisi Diteruskan',
            'pesan' => "{$this->pengirim->sebutanAktor()} meneruskan surat {$this->surat->nomor_agenda} ke ".implode(', ', $this->tujuan).'.',
            'url' => route('surat.show', $this->surat->id),
        ];
    }
}
