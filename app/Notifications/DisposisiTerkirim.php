<?php

namespace App\Notifications;

use App\Models\Surat;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DisposisiTerkirim extends Notification
{
    use Queueable;

    public function __construct(public Surat $surat, public array $tujuan)
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
            'judul' => 'Disposisi Terkirim',
            'pesan' => "Surat {$this->surat->nomor_agenda} telah diteruskan ke ".implode(', ', $this->tujuan).'.',
            'url' => route('surat.show', $this->surat->id),
        ];
    }
}
