<?php

namespace App\Notifications;

use App\Models\Surat;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SuratMasuk extends Notification
{
    use Queueable;

    public function __construct(public Surat $surat)
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
            'judul' => 'Surat Masuk Baru',
            'pesan' => "Surat {$this->surat->nomor_agenda} dari {$this->surat->asal_surat} telah diterima TU dan diteruskan ke Direktur.",
            'url' => route('surat.show', $this->surat->id),
        ];
    }
}