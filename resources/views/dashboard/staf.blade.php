@extends('layouts.app')

@section('title', 'Dashboard '.$sebutan)

@section('content')
    <div class="mb-3 d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <h5 class="fw-bold mb-0">Dashboard {{ $sebutan }}</h5>
            <span class="text-muted small">{{ auth()->user()->name }} &middot; {{ auth()->user()->roleLabel() }} &middot; username: {{ auth()->user()->username }}</span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('surat.index') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>Pantau Surat</a>
            <a href="{{ route('notifikasi.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-bell me-1"></i>Notifikasi</a>
        </div>
    </div>

    @php
        $persenSelesai = $stats['tugas'] > 0 ? (int) round($stats['selesai'] / $stats['tugas'] * 100) : 0;
    @endphp

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Surat Diterima</div>
                    <div class="fs-2 fw-bold text-primary">{{ $stats['diterima'] }}</div>
                    <div class="small text-muted">{{ $stats['tugas'] }} tugas disposisi</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Belum Dibaca</div>
                    <div class="fs-2 fw-bold text-danger">{{ $stats['belum_dibaca'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Sedang Dikerjakan</div>
                    <div class="fs-2 fw-bold text-warning">{{ $stats['diproses'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Selesai</div>
                    <div class="fs-2 fw-bold text-success">{{ $stats['selesai'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="fw-bold"><i class="bi bi-inbox me-1"></i>Surat &amp; Tugas Saya</span>
                <span class="small text-muted">{{ $stats['selesai'] }} dari {{ $stats['tugas'] }} tugas selesai</span>
            </div>
            <div class="progress mt-2" style="height: 6px;">
                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $persenSelesai }}%;" aria-valuenow="{{ $persenSelesai }}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Surat</th>
                        <th>Dari</th>
                        <th>Instruksi</th>
                        <th>Checklist</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($disposisiSaya as $d)
                        @php
                            $tahap = $d->selesai_at
                                ? ['Selesai', 'success']
                                : ($d->tindak_lanjut_at ? ['Tindak Lanjut', 'primary'] : ($d->dibaca_at ? ['Dibaca', 'info'] : ['Belum Dibaca', 'secondary']));
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('surat.show', $d->surat) }}" class="fw-semibold text-decoration-none">{{ $d->surat->nomor_agenda }}</a>
                                <div class="small text-muted">{{ \Illuminate\Support\Str::limit($d->surat->perihal, 45) }}</div>
                            </td>
                            <td>
                                {{ $d->dari->name }}
                                <div class="small text-muted">{{ $d->dari->roleLabel() }}</div>
                            </td>
                            <td class="small" style="white-space: pre-line;">{{ \Illuminate\Support\Str::limit($d->instruksi, 60) }}</td>
                            <td>
                                <span class="badge bg-{{ $tahap[1] }}">{{ $tahap[0] }}</span>
                                <div class="small mt-1 d-flex gap-1 flex-wrap">
                                    <span class="badge {{ $d->dibaca_at ? 'bg-success' : 'bg-secondary' }}">Dibaca</span>
                                    <span class="badge {{ $d->tindak_lanjut_at ? 'bg-success' : 'bg-secondary' }}">Tindak</span>
                                    <span class="badge {{ $d->selesai_at ? 'bg-success' : 'bg-secondary' }}">Selesai</span>
                                </div>
                            </td>
                            <td class="text-nowrap">
                                <div class="d-flex flex-column gap-1">
                                    <a href="{{ route('surat.show', $d->surat) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right me-1"></i>Buka</a>
                                    @if (! $d->selesai_at)
                                        <form method="POST" action="{{ route('disposisi.checklist', $d) }}" class="d-flex gap-1 flex-wrap">
                                            @csrf
                                            @if (! $d->dibaca_at)
                                                <button name="aksi" value="dibaca" class="btn btn-sm btn-outline-success">Dibaca</button>
                                            @endif
                                            @if (! $d->tindak_lanjut_at)
                                                <button name="aksi" value="tindak_lanjut" class="btn btn-sm btn-outline-success">Tindak Lanjut</button>
                                            @else
                                                <button name="aksi" value="selesai" class="btn btn-sm btn-success">Selesai</button>
                                            @endif
                                        </form>
                                    @else
                                        <span class="badge bg-success"><i class="bi bi-check-lg"></i> Tuntas</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Belum ada surat untuk Anda. Surat akan muncul setelah pimpinan mendisposisikan ke {{ $sebutan }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
