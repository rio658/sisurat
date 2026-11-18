@extends('layouts.app')

@section('title', 'Dashboard Tata Usaha')

@section('content')
    <div class="mb-3 d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <h5 class="fw-bold mb-0">Dashboard Tata Usaha</h5>
            <span class="text-muted small">{{ auth()->user()->name }} &middot; {{ auth()->user()->roleLabel() }} &middot; username: {{ auth()->user()->username }}</span>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('surat.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Input Surat Baru</a>
            <a href="{{ route('agenda.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-clipboard-check me-1"></i>Agenda Surat</a>
            <a href="{{ route('surat.index') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>Pantau Surat</a>
        </div>
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
                    <div class="text-muted small">Surat Baru</div>
                    <div class="fs-2 fw-bold text-warning">{{ $stats['baru'] }}</div>
                    <div class="small text-muted">Belum didisposisikan</div>
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

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-bold"><i class="bi bi-journal-text me-1"></i>Surat Terbaru</div>
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
                            <td class="text-nowrap">
                                <a href="{{ route('surat.show', $s) }}" class="btn btn-sm btn-outline-primary">Detail</a>
                                @if ($s->file_pdf)
                                    <a href="{{ route('surat.unduh', $s) }}" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-download me-1"></i>PDF
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Belum ada surat. Mulai dengan input surat baru.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
