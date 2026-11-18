@extends('layouts.app')

@php
    $peranDisposisi = match (auth()->user()->role) {
        'wadir' => 'Wakil Direktur',
        'kasubditbinum' => 'Kasubdit Binum',
        default => 'Direktur',
    };
    $anchorDisposisi = match (auth()->user()->role) {
        'wadir' => '#disposisi-wadir',
        'kasubditbinum' => '#disposisi-kasubdit',
        default => '#disposisi-direktur',
    };
@endphp
@section('title', 'Disposisi '.$peranDisposisi)

@section('content')
    <div class="mb-3">
        <h5 class="fw-bold mb-0">Disposisi {{ $peranDisposisi }}</h5>
        <span class="text-muted small">Beri disposisi surat masuk dan pantau tindak lanjut seluruh penerima secara global.</span>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Menunggu Disposisi</div>
                    <div class="fs-2 fw-bold text-warning">{{ $stats['menunggu'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Diteruskan ke Saya</div>
                    <div class="fs-2 fw-bold text-info">{{ $stats['diteruskan'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Surat Didisposisikan</div>
                    <div class="fs-2 fw-bold text-primary">{{ $stats['surat'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Belum Dibaca Penerima</div>
                    <div class="fs-2 fw-bold text-danger">{{ $stats['belum_dibaca'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Penerima Selesai</div>
                    <div class="fs-2 fw-bold text-success">{{ $stats['selesai'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="bi bi-inbox me-1 text-info"></i>Surat Diteruskan ke Saya</span>
            <span class="badge bg-info text-dark">{{ $stats['diteruskan'] }} surat</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>No Agenda</th>
                        <th>Diteruskan Oleh</th>
                        <th>Instruksi</th>
                        <th>Status Surat</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($diteruskan as $s)
                        @php $masuk = $s->disposisis->first(); @endphp
                        <tr>
                            <td class="fw-semibold">
                                {{ $s->nomor_agenda }}
                                <div class="small text-muted">{{ \Illuminate\Support\Str::limit($s->perihal, 40) }}</div>
                            </td>
                            <td>
                                {{ $masuk?->dari?->name ?? '-' }}
                                <div class="small text-muted">{{ $masuk?->dari?->roleLabel() }}</div>
                            </td>
                            <td class="small" style="white-space: pre-line;">{{ \Illuminate\Support\Str::limit($masuk?->instruksi, 80) }}</td>
                            <td><span class="badge bg-{{ match ($s->status) { 'baru' => 'warning', 'diproses' => 'info', default => 'success'} }}">{{ $s->statusLabel() }}</span></td>
                            <td class="text-nowrap">
                                <a href="{{ route('surat.show', $s) }}{{ $anchorDisposisi }}" class="btn btn-sm btn-success">
                                    <i class="bi bi-send me-1"></i>Disposisi Lanjutan
                                </a>
                                <a href="{{ route('surat.show', $s) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>Buka
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                Belum ada surat yang diteruskan ke akun Anda.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="bi bi-hourglass-split me-1 text-warning"></i>Menunggu Disposisi Anda</span>
            <span class="badge bg-warning text-dark">{{ $stats['menunggu'] }} surat</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>No Agenda</th>
                        <th>Tgl Terima</th>
                        <th>Asal Surat</th>
                        <th>Perihal</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($menunggu as $s)
                        <tr>
                            <td class="fw-semibold">{{ $s->nomor_agenda }}</td>
                            <td class="text-nowrap">{{ $s->tanggal_terima->format('d/m/Y') }}</td>
                            <td>{{ $s->asal_surat }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($s->perihal, 50) }}</td>
                            <td><span class="badge bg-warning">{{ $s->statusLabel() }}</span></td>
                            <td class="text-nowrap">
                                <a href="{{ route('surat.show', $s) }}{{ $anchorDisposisi }}" class="btn btn-sm btn-success">
                                    <i class="bi bi-send me-1"></i>Beri Disposisi
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Tidak ada surat yang menunggu disposisi. Semua surat sudah Anda disposisikan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mb-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-globe2 me-1"></i>Monitor Progres Disposisi (Global)</h6>
        <span class="text-muted small">Pantau status Dibaca, Tindak Lanjut, dan Selesai dari setiap penerima pada surat yang Anda disposisikan.</span>
    </div>

    @forelse ($suratDisposisi as $s)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <span class="fw-bold">{{ $s->nomor_agenda }}</span>
                    <span class="badge bg-{{ match ($s->status) { 'baru' => 'warning', 'diproses' => 'info', default => 'success'} }} ms-1">{{ $s->statusLabel() }}</span>
                    <div class="small text-muted mt-1">{{ $s->asal_surat }} &mdash; {{ \Illuminate\Support\Str::limit($s->perihal, 70) }}</div>
                </div>
                <a href="{{ route('surat.show', $s) }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Buka Surat
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Jabatan Tujuan</th>
                            <th>Penerima</th>
                            <th>Instruksi</th>
                            <th class="text-center">Dibaca</th>
                            <th class="text-center">Tindak Lanjut</th>
                            <th class="text-center">Selesai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($s->disposisis->sortBy('created_at') as $d)
                            <tr>
                                <td class="fw-semibold">
                                    <span class="badge bg-secondary me-1">{{ $loop->iteration }}</span>
                                    {{ $d->jabatan_tujuan ? \App\Models\Disposisi::label($d->jabatan_tujuan, $d->dari?->role) : $d->ke?->roleLabel() }}
                                    @if ($d->dari_user_id !== auth()->id())
                                        <div class="small text-muted">diteruskan {{ $d->dari->name }}</div>
                                    @endif
                                </td>
                                <td>{{ $d->ke?->name ?? 'Belum ada akun' }}</td>
                                <td class="small" style="white-space: pre-line;">{{ \Illuminate\Support\Str::limit($d->instruksi, 90) }}</td>
                                <td class="text-center">
                                    @if ($d->dibaca_at)
                                        <span class="badge bg-success" title="{{ $d->dibaca_at->format('d/m/Y H:i') }}"><i class="bi bi-check-lg"></i> {{ $d->dibaca_at->format('d/m') }}</span>
                                    @else
                                        <span class="badge bg-secondary">Belum</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($d->tindak_lanjut_at)
                                        <span class="badge bg-success" title="{{ $d->tindak_lanjut_at->format('d/m/Y H:i') }}"><i class="bi bi-check-lg"></i> {{ $d->tindak_lanjut_at->format('d/m') }}</span>
                                    @else
                                        <span class="badge bg-secondary">Belum</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($d->selesai_at)
                                        <span class="badge bg-success" title="{{ $d->selesai_at->format('d/m/Y H:i') }}"><i class="bi bi-check-lg"></i> {{ $d->selesai_at->format('d/m') }}</span>
                                    @else
                                        <span class="badge bg-secondary">Belum</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-5">
                Belum ada disposisi yang Anda kirim. Pilih surat pada daftar di atas untuk mulai mendisposisikan.
            </div>
        </div>
    @endforelse
@endsection
