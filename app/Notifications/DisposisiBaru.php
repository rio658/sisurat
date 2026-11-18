<?php

namespace App\Notifications;

use App\Models\Disposisi;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class DisposisiBaru extends Notification
{
    use Queueable;

    public function __construct(public Disposisi $disposisi)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'surat_id' => $this->disposisi->surat_id,
            'judul' => 'Disposisi Baru',
            'pesan' => "Disposisi dari {$this->disposisi->dari->name} untuk surat {$this->disposisi->surat->nomor_agenda}: ".Str::limit($this->disposisi->instruksi, 80),
            'url' => route('surat.show', $this->disposisi->surat_id),
        ];
    }
}