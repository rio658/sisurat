@extends('layouts.app')

@section('title', 'Notifikasi')

@section('content')
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="fw-bold"><i class="bi bi-bell me-1"></i>Semua Notifikasi</span>
            <div class="d-flex gap-2">
                <form method="POST" action="{{ route('notifikasi.bacaSemua') }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-primary">Tandai semua dibaca</button>
                </form>
                <form method="POST" action="{{ route('notifikasi.destroySemua') }}"
                      onsubmit="return confirm('Hapus semua notifikasi Anda? Tindakan ini tidak dapat dibatalkan.')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Hapus semua</button>
                </form>
            </div>
        </div>
        <div class="list-group list-group-flush">
            @forelse ($notifikasis as $n)
                <div class="list-group-item {{ is_null($n->read_at) ? 'list-group-item-warning' : '' }}">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <a href="{{ route('notifikasi.baca', $n->id) }}" class="text-decoration-none text-dark flex-grow-1">
                            <div class="d-flex justify-content-between">
                                <div class="fw-bold">
                                    {{ $n->data['judul'] }}
                                    @if (is_null($n->read_at))<span class="badge bg-danger ms-1">Baru</span>@endif
                                </div>
                                <small class="text-muted">{{ $n->created_at->diffForHumans() }}</small>
                            </div>
                            <div class="small">{{ $n->data['pesan'] }}</div>
                            @if (isset($n->data['bantuan_password']))
                                @php($bantuan = $n->data['bantuan_password'])
                                <dl class="row small mt-3 mb-2">
                                    <dt class="col-sm-3">Nama</dt>
                                    <dd class="col-sm-9 text-break">{{ $bantuan['nama'] }}</dd>
                                    <dt class="col-sm-3">Username</dt>
                                    <dd class="col-sm-9 text-break">{{ $bantuan['username'] }}</dd>
                                    <dt class="col-sm-3">Jabatan</dt>
                                    <dd class="col-sm-9 text-break">{{ $bantuan['jabatan'] }}</dd>
                                    <dt class="col-sm-3">Nomor kontak</dt>
                                    <dd class="col-sm-9 text-break">{{ $bantuan['kontak'] }}</dd>
                                    <dt class="col-sm-3">Pesan</dt>
                                    <dd class="col-sm-9 text-break" style="white-space: pre-line;">{{ $bantuan['pesan'] ?: '-' }}</dd>
                                </dl>
                                <div class="small text-secondary mb-2">{{ $bantuan['terautentikasi'] ? 'Identitas berasal dari akun yang sedang login.' : 'Identitas diisi pemohon dan belum terverifikasi.' }}</div>
                                <div class="small fw-semibold">Hubungi pemohon dan verifikasi identitas sebelum mengganti password. Klik untuk membuka Kelola Akun.</div>
                            @endif
                        </a>
                        <form method="POST" action="{{ route('notifikasi.destroy', $n->id) }}"
                              onsubmit="return confirm('Hapus notifikasi ini?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" title="Hapus notifikasi" aria-label="Hapus notifikasi">
                                <i class="bi bi-trash me-1"></i>Hapus
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="list-group-item text-center text-muted py-4">Belum ada notifikasi.</div>
            @endforelse
        </div>
        @if ($notifikasis->hasPages())
            <div class="card-footer bg-white">{{ $notifikasis->links() }}</div>
        @endif
    </div>
@endsection
