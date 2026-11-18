<?php

namespace App\Notifications;

use App\Models\Disposisi;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProgresDisposisi extends Notification
{
    use Queueable;

    public function __construct(public Disposisi $disposisi, public string $aksi)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $status = $this->aksi === 'selesai' ? 'selesai' : 'ditindaklanjuti';
        $penerima = $this->disposisi->ke?->name ?? 'Penerima';

        return [
            'surat_id' => $this->disposisi->surat_id,
            'judul' => 'Progres Disposisi',
            'pesan' => "{$penerima} menandai disposisi {$status} untuk surat {$this->disposisi->surat->nomor_agenda}.",
            'url' => route('surat.show', $this->disposisi->surat_id),
        ];
    }
}
