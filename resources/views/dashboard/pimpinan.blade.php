@extends('layouts.app')

@section('title', 'Dashboard '.auth()->user()->roleLabel())

@section('content')
    <div class="mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="fw-bold mb-0">Dashboard {{ auth()->user()->roleLabel() }}</h5>
            <span class="text-muted small">{{ auth()->user()->name }} &middot; {{ auth()->user()->roleLabel() }} &middot; username: {{ auth()->user()->username }}</span>
        </div>
        <a href="{{ route('disposisi.index') }}" class="btn btn-sm btn-primary"><i class="bi bi-send me-1"></i>Halaman Disposisi</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Total Surat</div>
                    <div class="fs-2 fw-bold text-primary">{{ $stats['total'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Terkirim ke Direktur</div>
                    <div class="fs-2 fw-bold text-warning">{{ $stats['baru'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Diproses</div>
                    <div class="fs-2 fw-bold text-info">{{ $stats['diproses'] }}</div>
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

    @if ($perluDisposisi->isNotEmpty())
        <div class="card border-0 shadow-sm mb-3 border-start border-warning border-4">
            <div class="card-header bg-white fw-bold"><i class="bi bi-hourglass-split me-1 text-warning"></i>Menunggu Disposisi Anda</div>
            <div class="list-group list-group-flush">
                @foreach ($perluDisposisi as $s)
                    <a href="{{ route('surat.show', $s) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><span class="fw-semibold">{{ $s->nomor_agenda }}</span> &mdash; {{ \Illuminate\Support\Str::limit($s->perihal, 50) }}</span>
                        <span class="btn btn-sm btn-warning">Disposisi</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-bold">Surat Terbaru</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>No Agenda</th>
                        <th>Tgl Terima</th>
                        <th>Perihal</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suratTerbaru as $s)
                        <tr>
                            <td class="fw-semibold">{{ $s->nomor_agenda }}</td>
                            <td>{{ $s->tanggal_terima->format('d/m/Y') }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($s->perihal, 50) }}</td>
                            <td>
                                <span class="badge bg-{{ match ($s->status) { 'baru' => 'warning', 'diproses' => 'info', default => 'success'} }}">
                                    {{ $s->statusLabel() }}
                                </span>
                            </td>
                            <td><a href="{{ route('surat.show', $s) }}" class="btn btn-sm btn-outline-primary">Detail</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Belum ada surat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
