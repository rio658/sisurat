@extends('layouts.app')

@section('title', 'Detail Surat')

@section('content')
    @if (auth()->user()->role === 'direktur')
        <style>
            .disposisi-direktur { background: #0d1117; color: #e6edf3; border: 1px solid #30363d; border-radius: 12px; overflow: hidden; }
            .disposisi-direktur .card-header { background: #161b22; border-bottom: 1px solid #30363d; padding: 20px; }
            .disposisi-direktur .disposisi-muted { color: #aab8c5; }
            .disposisi-direktur .disposisi-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
            .disposisi-direktur .disposisi-option { background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 12px; min-width: 0; }
            .disposisi-direktur .disposisi-option:hover { border-color: #6e7681; }
            .disposisi-direktur .disposisi-option:has(input:checked) { background: #12261c; border-color: #22c55e; }
            .disposisi-direktur .form-check-label { font-size: .8rem; cursor: pointer; overflow-wrap: anywhere; }
            .disposisi-direktur .form-check-input { background-color: #0d1117; border-color: #6e7681; }
            .disposisi-direktur .form-check-input:checked { background-color: #16a34a; border-color: #22c55e; }
            .disposisi-direktur .form-select, .disposisi-direktur .form-control { background-color: #0d1117; color: #e6edf3; border-color: #484f58; }
            .disposisi-direktur .form-select { color-scheme: dark; }
            .disposisi-direktur .form-control::placeholder { color: #aab8c5; }
            .disposisi-direktur .disposisi-footer { border-top: 1px solid #30363d; }
            @media (max-width: 1100px) { .disposisi-direktur .disposisi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
            @media (max-width: 650px) { .disposisi-direktur .disposisi-grid { grid-template-columns: 1fr; } }
        </style>
        <div id="disposisi-direktur" class="card disposisi-direktur shadow-sm mb-4">
            <div class="card-header">
                <h5 class="fw-bold mb-2"><i class="bi bi-send me-2"></i>Disposisi Dirkumad</h5>
                <div class="d-flex flex-wrap gap-3 small disposisi-muted">
                    <span>Agenda: <strong class="text-white">{{ $surat->nomor_agenda }}</strong></span>
                    <span>Diterima: {{ $surat->tanggal_terima->format('d/m/Y') }}</span>
                    <span>Dari: {{ $surat->asal_surat }}</span>
                </div>
                <div class="mt-2 small">Perihal: {{ $surat->perihal }}</div>
            </div>
            @if ($surat->file_pdf)
                <div class="card-body p-3 p-md-4 pb-0">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <span class="fw-semibold small"><i class="bi bi-file-earmark-pdf me-1 text-danger"></i>Isi Surat (PDF) dari TU</span>
                        <a href="{{ route('surat.file', $surat) }}" target="_blank" class="btn btn-sm btn-outline-light">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Buka Tab Baru
                        </a>
                    </div>
                    <iframe src="{{ route('surat.file', $surat) }}" title="Isi surat {{ $surat->nomor_agenda }}"
                            style="width: 100%; height: 520px; border: 1px solid #30363d; border-radius: 8px; background: #fff;"></iframe>
                </div>
            @endif
            <form id="form-disposisi-direktur" method="POST" action="{{ route('disposisi.store', $surat) }}">
                @csrf
                <div class="card-body p-3 p-md-4">
                    <fieldset class="mb-4">
                        <legend class="fs-6 fw-bold mb-1">Kepada Yth. <span class="text-danger">*</span></legend>
                        <p class="small disposisi-muted mb-3">Ceklis pejabat yang dituju dan perintah disposisi. Disposisi Dirkumad hanya diterima oleh WADIRKUMAD untuk dikoordinasikan dan diteruskan, sehingga pejabat yang terkena ceklis nantinya setelah disposisi dari WADIRKUMAD dapat melihat disposisi Direktur ini.</p>
                        <div class="disposisi-grid">
                            @foreach (\App\Models\Disposisi::jabatanDirekturList() as $kode => $label)
                                <div class="disposisi-option">
                                    <div class="form-check mb-0">
                                        <input id="tujuan-{{ $kode }}" type="checkbox" name="jabatan[]" value="{{ $kode }}" class="form-check-input" @checked(in_array($kode, (array) old('jabatan', ['wadirkumad']), true))>
                                        <label for="tujuan-{{ $kode }}" class="form-check-label fw-semibold">{{ $label }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                    <fieldset class="mb-4">
                        <legend class="fs-6 fw-bold mb-1">Perintah Disposisi <span class="text-danger">*</span></legend>
                        <p class="small disposisi-muted mb-3">Pilih satu atau beberapa perintah. Semua penerima terpilih menerima perintah yang sama.</p>
                        <div class="disposisi-grid">
                            @foreach (\App\Models\Disposisi::perintahList() as $kode => $label)
                                <div class="disposisi-option">
                                    <div class="form-check mb-0">
                                        <input id="perintah-{{ $kode }}" type="checkbox" name="perintah[]" value="{{ $kode }}" class="form-check-input" @checked(in_array($kode, (array) old('perintah', []), true))>
                                        <label for="perintah-{{ $kode }}" class="form-check-label">{{ $label }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                    <label for="lain-lain" class="form-label fw-semibold">Lain-lain <span class="disposisi-muted fw-normal small">(opsional)</span></label>
                    <textarea id="lain-lain" name="lain_lain" class="form-control" rows="3" maxlength="2000" placeholder="Tambahkan arahan khusus jika diperlukan...">{{ old('lain_lain') }}</textarea>
                </div>
                <div class="disposisi-footer p-3 p-md-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <span id="ringkasan-disposisi" class="small disposisi-muted" aria-live="polite"></span>
                    <button type="submit" class="btn btn-success"><i class="bi bi-send me-1"></i>Kirim Disposisi</button>
                </div>
            </form>
        </div>
        <script>
            const formDisposisi = document.getElementById('form-disposisi-direktur');
            function perbaruiDisposisi() {
                const penerima = formDisposisi.querySelectorAll('input[name="jabatan[]"]:checked').length;
                const perintah = formDisposisi.querySelectorAll('input[name="perintah[]"]:checked').length;
                document.getElementById('ringkasan-disposisi').textContent = `${penerima} tujuan dipilih · ${perintah} perintah dipilih`;
            }
            formDisposisi.addEventListener('change', perbaruiDisposisi);
            perbaruiDisposisi();
        </script>
    @endif
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-bold"><i class="bi bi-journal-text me-1"></i>Data Surat &amp; Agenda</div>
                <div class="card-body">
                    <h4 class="fw-bold">
                        {{ $surat->nomor_agenda }}
                        <span class="badge bg-{{ match ($surat->jenis_surat) { 'R' => 'danger', 'Kep' => 'warning', 'Sprin' => 'info', 'St' => 'secondary', default => 'primary'} }} fs-6">{{ $surat->jenisLabel() }}</span>
                        <span class="badge bg-{{ match ($surat->status) { 'baru' => 'warning', 'diproses' => 'info', default => 'success'} }} fs-6">{{ $surat->statusLabel() }}</span>
                    </h4>
                    <table class="table table-sm table-borderless mb-0 mt-3">
                        <tr><td class="text-muted" style="width: 35%;">Nomor Surat</td><td class="fw-semibold">{{ $surat->nomor_surat }}</td></tr>
                        <tr><td class="text-muted">Tanggal Surat</td><td>{{ $surat->tanggal_surat->format('d F Y') }}</td></tr>
                        <tr><td class="text-muted">Tanggal Terima (Agenda)</td><td>{{ $surat->tanggal_terima->format('d F Y') }}</td></tr>
                        <tr><td class="text-muted">Asal Surat</td><td>{{ $surat->asal_surat }}</td></tr>
                        <tr><td class="text-muted">Perihal</td><td>{{ $surat->perihal }}</td></tr>
                        <tr><td class="text-muted">Diinput Oleh</td><td>{{ $surat->pembuat->name }} ({{ $surat->created_at->format('d/m/Y H:i') }})</td></tr>
                    </table>
                    <div class="mt-3 d-flex gap-2 flex-wrap">
                        @if ($surat->file_pdf)
                            <a href="{{ route('surat.file', $surat) }}" target="_blank" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-file-pdf me-1"></i>Lihat PDF Surat
                            </a>
                            @if (auth()->user()->role === 'tu')
                                <a href="{{ route('surat.unduh', $surat) }}" class="btn btn-danger btn-sm">
                                    <i class="bi bi-download me-1"></i>Unduh PDF
                                </a>
                            @endif
                        @endif
                        <a href="{{ route('surat.cetak', $surat) }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-printer me-1"></i>Cetak Lembar Agenda (PDF)
                        </a>
                        <form method="POST" action="{{ route('surat.destroy', $surat) }}"
                              onsubmit="return confirm('Hapus surat {{ $surat->nomor_agenda }}? Disposisi, notifikasi, dan file PDF ikut terhapus.')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Hapus Surat</button>
                        </form>
                    </div>
                </div>
            </div>

            @if (auth()->user()->role === 'wadir')
                @php
                    $disposisiDirektur = $surat->disposisis->firstWhere('dari.role', 'direktur');
                    $jabatanDirekturDiceklis = $disposisiDirektur?->target_jabatan ?? [];
                    $targetDirekturLabels = ! empty($jabatanDirekturDiceklis)
                        ? array_values(array_intersect_key(\App\Models\Disposisi::jabatanList(), array_flip($jabatanDirekturDiceklis)))
                        : [];
                @endphp
                <div id="disposisi-wadir" class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-bold"><i class="bi bi-send me-1"></i>Disposisi Wadirkumad</div>
                    <div class="card-body">
                        @if (! empty($targetDirekturLabels))
                            <div class="alert alert-info py-2 px-3 small mb-3">
                                <i class="bi bi-info-circle me-1"></i>
                                <strong>Arahan Dirkumad:</strong> Pejabat yang diceklis Direktur:
                                <strong>{{ implode(', ', $targetDirekturLabels) }}</strong>.
                                Kotak tujuan di bawah otomatis ditandai sesuai arahan Direktur.
                            </div>
                        @endif
                        <form id="form-disposisi-wadir" method="POST" action="{{ route('disposisi.store', $surat) }}">
                            @csrf
                            <p class="small text-muted">Ceklis jabatan tujuan dan perintah. Penerima dipilih otomatis sesuai jabatan, dan akun yang menerima akan diberi notifikasi.</p>
                            <fieldset class="mb-3">
                                <legend class="fs-6 fw-bold mb-2">Kepada Yth. <span class="text-danger">*</span></legend>
                                <div class="row g-2">
                                    @foreach (\App\Models\Disposisi::jabatanWadirList() as $kode => $label)
                                        <div class="col-12 col-sm-6 col-xl-4">
                                            <div class="form-check">
                                                <input id="wadir-jabatan-{{ $kode }}" type="checkbox" name="jabatan[]" value="{{ $kode }}" class="form-check-input" @checked(in_array($kode, (array) old('jabatan', $jabatanDirekturDiceklis), true))>
                                                <label for="wadir-jabatan-{{ $kode }}" class="form-check-label small fw-semibold">{{ $label }}</label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </fieldset>
                            <fieldset class="mb-3">
                                <legend class="fs-6 fw-bold mb-2">Perintah <span class="text-danger">*</span></legend>
                                <div class="row g-2">
                                    @foreach (\App\Models\Disposisi::perintahList() as $kode => $label)
                                        <div class="col-12 col-sm-6 col-xl-4">
                                            <div class="form-check">
                                                <input id="wadir-perintah-{{ $kode }}" type="checkbox" name="perintah[]" value="{{ $kode }}" class="form-check-input" @checked(in_array($kode, (array) old('perintah', []), true))>
                                                <label for="wadir-perintah-{{ $kode }}" class="form-check-label small">{{ $label }}</label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </fieldset>
                            <div class="mb-3">
                                <label for="lain-lain-wadir" class="form-label">Lain-lain <span class="text-muted small">(opsional)</span></label>
                                <textarea id="lain-lain-wadir" name="lain_lain" class="form-control" rows="3" maxlength="2000" placeholder="Tambahkan arahan khusus jika diperlukan...">{{ old('lain_lain') }}</textarea>
                            </div>
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                                <span id="ringkasan-disposisi-wadir" class="small text-muted" aria-live="polite"></span>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Kirim Disposisi</button>
                            </div>
                        </form>
                    </div>
                </div>
                <script>
                    (function () {
                        const form = document.getElementById('form-disposisi-wadir');
                        const ringkasan = document.getElementById('ringkasan-disposisi-wadir');
                        function perbarui() {
                            const tujuan = form.querySelectorAll('input[name="jabatan[]"]:checked').length;
                            const perintah = form.querySelectorAll('input[name="perintah[]"]:checked').length;
                            ringkasan.textContent = `${tujuan} tujuan dipilih · ${perintah} perintah dipilih`;
                        }
                        form.addEventListener('change', perbarui);
                        perbarui();
                    })();
                </script>
            @elseif (auth()->user()->role === 'kasubditbinum')
                <div id="disposisi-kasubdit" class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-bold"><i class="bi bi-send me-1"></i>Disposisi Kasubditbinum</div>
                    <div class="card-body">
                        <form id="form-disposisi-kasubdit" method="POST" action="{{ route('disposisi.store', $surat) }}">
                            @csrf
                            <p class="small text-muted">Ceklis jabatan tujuan dan perintah. Penerima dipilih otomatis sesuai jabatan, dan akun yang menerima akan diberi notifikasi.</p>
                            <fieldset class="mb-3">
                                <legend class="fs-6 fw-bold mb-2">Kepada Yth. <span class="text-danger">*</span></legend>
                                <div class="row g-2">
                                    @foreach (\App\Models\Disposisi::jabatanKasubditList() as $kode => $label)
                                        <div class="col-12 col-sm-6 col-xl-4">
                                            <div class="form-check">
                                                <input id="kasubdit-jabatan-{{ $kode }}" type="checkbox" name="jabatan[]" value="{{ $kode }}" class="form-check-input" @checked(in_array($kode, (array) old('jabatan', []), true))>
                                                <label for="kasubdit-jabatan-{{ $kode }}" class="form-check-label small fw-semibold">{{ $label }}</label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </fieldset>
                            <fieldset class="mb-3">
                                <legend class="fs-6 fw-bold mb-2">Perintah <span class="text-danger">*</span></legend>
                                <div class="row g-2">
                                    @foreach (\App\Models\Disposisi::perintahKasubditList() as $kode => $label)
                                        <div class="col-12 col-sm-6 col-xl-4">
                                            <div class="form-check">
                                                <input id="kasubdit-perintah-{{ $kode }}" type="checkbox" name="perintah[]" value="{{ $kode }}" class="form-check-input" @checked(in_array($kode, (array) old('perintah', []), true))>
                                                <label for="kasubdit-perintah-{{ $kode }}" class="form-check-label small">{{ $label }}</label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </fieldset>
                            <div class="mb-3">
                                <label for="lain-lain-kasubdit" class="form-label">Lain-lain <span class="text-muted small">(opsional)</span></label>
                                <textarea id="lain-lain-kasubdit" name="lain_lain" class="form-control" rows="3" maxlength="2000" placeholder="Tambahkan arahan khusus jika diperlukan...">{{ old('lain_lain') }}</textarea>
                            </div>
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                                <span id="ringkasan-disposisi-kasubdit" class="small text-muted" aria-live="polite"></span>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Kirim Disposisi</button>
                            </div>
                        </form>
                    </div>
                </div>
                <script>
                    (function () {
                        const form = document.getElementById('form-disposisi-kasubdit');
                        const ringkasan = document.getElementById('ringkasan-disposisi-kasubdit');
                        function perbarui() {
                            const tujuan = form.querySelectorAll('input[name="jabatan[]"]:checked').length;
                            const perintah = form.querySelectorAll('input[name="perintah[]"]:checked').length;
                            ringkasan.textContent = `${tujuan} tujuan dipilih · ${perintah} perintah dipilih`;
                        }
                        form.addEventListener('change', perbarui);
                        perbarui();
                    })();
                </script>
            @endif
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                @php
                    $riwayatDisposisi = $surat->disposisis->sortBy('created_at')->values();
                @endphp
                <div class="card-header bg-white fw-bold"><i class="bi bi-arrow-left-right me-1"></i>Riwayat Disposisi ({{ $riwayatDisposisi->count() }})</div>
                <div class="card-body">
                    @forelse ($riwayatDisposisi as $d)
                        <div class="border rounded p-3 mb-3" data-urutan="{{ $loop->iteration }}" data-disposisi-id="{{ $d->id }}">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="fw-bold">
                                    <span class="badge bg-secondary me-1">{{ $loop->iteration }}</span>
                                    {{ $d->dari->name }} <i class="bi bi-arrow-right"></i> {{ $d->ke?->name ?? \App\Models\Disposisi::label($d->jabatan_tujuan, $d->dari->role) }}
                                </div>
                                <small class="text-muted text-nowrap">{{ $d->created_at->format('d/m/Y H:i') }}</small>
                            </div>
                            <div class="small text-secondary mb-2">{{ $d->dari->roleLabel() }} &rarr; {{ $d->jabatan_tujuan ? \App\Models\Disposisi::label($d->jabatan_tujuan, $d->dari->role) : $d->ke?->roleLabel() }}</div>
                            @if (! empty($d->target_jabatan))
                                <div class="small mb-2 p-2 bg-light rounded border">
                                    <span class="text-muted fw-semibold"><i class="bi bi-check2-square me-1"></i>Ceklis Pejabat Dituju:</span>
                                    <span class="fw-bold text-primary">{{ $d->targetJabatanTeks() }}</span>
                                </div>
                            @endif
                            <div class="mb-2"><span class="text-muted small">Instruksi:</span><div style="white-space: pre-line;">{{ $d->instruksi }}</div></div>
                            <div class="mb-2 d-flex gap-1 flex-wrap">
                                <span class="badge {{ $d->dibaca_at ? 'bg-success' : 'bg-secondary' }}">1. Dibaca</span>
                                <span class="badge {{ $d->tindak_lanjut_at ? 'bg-success' : 'bg-secondary' }}">2. Ditindaklanjuti</span>
                                <span class="badge {{ $d->selesai_at ? 'bg-success' : 'bg-secondary' }}">3. Selesai</span>
                            </div>
                            @if ($d->catatan)
                                <div class="small"><span class="text-muted">Catatan:</span> {{ $d->catatan }}</div>
                            @endif

                            @if ($d->ke_user_id === auth()->id() && ! $d->selesai_at)
                                <hr class="my-2">
                                <form method="POST" action="{{ route('disposisi.checklist', $d) }}">
                                    @csrf
                                    <div class="d-flex gap-1 flex-wrap">
                                        @if (! $d->dibaca_at)
                                            <button name="aksi" value="dibaca" class="btn btn-sm btn-outline-success">&#10003; Tandai Dibaca</button>
                                        @endif
                                        @if (! $d->tindak_lanjut_at)
                                            <button name="aksi" value="tindak_lanjut" class="btn btn-sm btn-outline-success">&#10003; Tindak Lanjut</button>
                                        @endif
                                    </div>
                                    <div class="input-group input-group-sm mt-2">
                                        <input name="catatan" class="form-control" placeholder="Catatan hasil (opsional)">
                                        <button name="aksi" value="selesai" class="btn btn-success">Selesai</button>
                                    </div>
                                </form>
                            @endif

                            @if ($d->dari_user_id === auth()->id() || $d->ke_user_id === auth()->id())
                                <form method="POST" action="{{ route('disposisi.destroy', $d) }}" class="mt-2"
                                      onsubmit="return confirm('Hapus riwayat disposisi ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Hapus riwayat</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted small mb-0">
                            Belum ada disposisi.
                            @if (auth()->user()->role === 'tu')
                                Surat ini otomatis diteruskan ke Direktur.
                            @endif
                        </p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection