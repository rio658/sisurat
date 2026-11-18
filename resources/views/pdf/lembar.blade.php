<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Lembar Agenda - {{ $surat->nomor_agenda }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #111; }
        .kop { text-align: center; border-bottom: 3px double #000; padding-bottom: 8px; margin-bottom: 16px; }
        .kop h2 { margin: 0; }
        .kop h3 { margin: 8px 0 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 6px 8px; border: 1px solid #444; vertical-align: top; }
        th { background: #f0f0f0; }
        .label { width: 30%; background: #f0f0f0; font-weight: bold; }
        .mt { margin-top: 16px; }
        .ttd { margin-top: 36px; width: 45%; float: right; text-align: center; }
    </style>
</head>
<body>
    <div class="kop">
        <h2>SISURAT</h2>
        <div>Sistem Agenda &amp; Disposisi Surat</div>
        <h3>LEMBAR AGENDA &amp; DISPOSISI SURAT</h3>
    </div>

    <table>
        <tr><td class="label">Nomor Agenda</td><td>{{ $surat->nomor_agenda }}</td></tr>
        <tr><td class="label">Jenis Surat</td><td>{{ $surat->jenisLabel() }}</td></tr>
        <tr><td class="label">Nomor Surat</td><td>{{ $surat->nomor_surat }}</td></tr>
        <tr><td class="label">Tanggal Surat</td><td>{{ $surat->tanggal_surat->format('d F Y') }}</td></tr>
        <tr><td class="label">Tanggal Terima</td><td>{{ $surat->tanggal_terima->format('d F Y') }}</td></tr>
        <tr><td class="label">Asal Surat</td><td>{{ $surat->asal_surat }}</td></tr>
        <tr><td class="label">Perihal</td><td>{{ $surat->perihal }}</td></tr>
        <tr><td class="label">Status</td><td>{{ $surat->statusLabel() }}</td></tr>
        <tr><td class="label">Diinput Oleh</td><td>{{ $surat->pembuat->name }} ({{ $surat->created_at->format('d/m/Y H:i') }})</td></tr>
    </table>

    <h3 class="mt">Riwayat Disposisi</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 15%;">Tanggal</th>
                <th style="width: 18%;">Dari</th>
                <th style="width: 18%;">Kepada</th>
                <th>Instruksi</th>
                <th style="width: 20%;">Progres</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($surat->disposisis->sortBy('created_at') as $i => $d)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $d->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $d->dari->name }} ({{ $d->dari->roleLabel() }})</td>
                    <td>{{ $d->ke ? $d->ke->name.' ('.$d->ke->roleLabel().')' : \App\Models\Disposisi::label($d->jabatan_tujuan, $d->dari?->role) }}</td>
                    <td>
                        {{ $d->instruksi }}
                        @if (! empty($d->target_jabatan))
                            <br><small><strong>Ceklis Pejabat:</strong> {{ $d->targetJabatanTeks() }}</small>
                        @endif
                        @if ($d->catatan)<br><em>Catatan: {{ $d->catatan }}</em>@endif
                    </td>
                    <td>
                        Dibaca: {{ $d->dibaca_at ? $d->dibaca_at->format('d/m/Y') : '-' }}<br>
                        Tindak lanjut: {{ $d->tindak_lanjut_at ? $d->tindak_lanjut_at->format('d/m/Y') : '-' }}<br>
                        Selesai: {{ $d->selesai_at ? $d->selesai_at->format('d/m/Y') : '-' }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align: center;">Belum ada disposisi.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="ttd">
        Dicetak: {{ now()->format('d/m/Y H:i') }}<br>
        Oleh: {{ auth()->user()->name }}<br><br><br><br>
        ________________________
    </div>
</body>
</html>