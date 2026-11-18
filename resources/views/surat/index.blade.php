@extends('layouts.app')

@section('title', $isAgenda ? 'Agenda TU' : 'Pantau Surat')

@section('content')
    @if ($isAgenda)
        <style>
            .agenda-tu { background: #0d1117; color: #e6edf3; border: 1px solid #30363d !important; border-radius: 12px; overflow: hidden; }
            .agenda-tu .card-header { background: #161b22; border-bottom: 1px solid #30363d; padding: 18px 20px; }
            #kontrol-agenda { --bs-table-bg: #0d1117; --bs-table-color: #e6edf3; --bs-table-border-color: #30363d; --bs-table-hover-bg: #1c2430; --bs-table-hover-color: #fff; font-size: .9rem; }
            #kontrol-agenda thead { --bs-table-bg: #161b22; --bs-table-color: #aab8c5; }
            #kontrol-agenda th { padding: 14px 20px; font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; }
            #kontrol-agenda td { padding: 14px 20px; }
            #kontrol-agenda .form-check-input { background-color: #161b22; border-color: #6e7681; opacity: 1; }
            #kontrol-agenda .form-check-input:checked { background-color: #16a34a; border-color: #22c55e; }
        </style>
    @endif

    @if (! $isAgenda)
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('surat.index') }}" class="row g-2">
                <div class="col-md-6">
                    <input name="q" value="{{ request('q') }}" class="form-control"
                           placeholder="Cari no agenda, nomor surat, perihal, atau asal surat...">
                </div>
                <div class="col-md-3">
                    <select name="jenis" class="form-select">
                        <option value="">Semua Jenis</option>
                        @foreach (\App\Models\Surat::jenisList() as $kode => $label)
                            <option value="{{ $kode }}" @selected(request('jenis') === $kode)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Cari</button>
                </div>
                <div class="col-md-1">
                    <a href="{{ route('surat.index') }}" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>
    @endif

    @if ($isAgenda)
        <div class="agenda-tu card border-0 shadow-sm">
            @if ($agendaTerpilih || $barisAgenda->isNotEmpty())
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h6 class="fw-bold mb-1">Kontrol Agenda Diteruskan</h6>
                        <div class="text-white-50 small">Ceklis otomatis setelah surat atau disposisi masuk ke akun penerima, bukan berdasarkan ceklis tujuan Direktur.</div>
                        @if ($agendaTerpilih)
                            <div class="text-white-50 small">Nomor Agenda: {{ $agendaTerpilih->nomor_agenda }}</div>
                        @else
                            <div class="text-white-50 small">Belum ada surat — tabel urutan jabatan sebagai acuan.</div>
                        @endif
                    </div>
                    @if ($agendaTerpilih?->file_pdf)
                        <a href="{{ route('surat.unduh', $agendaTerpilih) }}" class="btn btn-sm btn-outline-light">
                            <i class="bi bi-download me-1"></i>Unduh PDF
                        </a>
                    @endif
                </div>
                <div class="table-responsive" tabindex="0" role="region" aria-label="Tabel kontrol agenda, geser untuk melihat semua kolom">
                    <table id="kontrol-agenda" class="table table-dark table-hover align-middle mb-0">
                        <caption class="visually-hidden">Penerimaan surat pada akun tujuan</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="ps-3">No</th>
                                <th scope="col">Pejabat</th>
                                <th scope="col" class="text-nowrap">Tanggal Diterima</th>
                                <th scope="col" class="text-center pe-3">Ceklis</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($barisAgenda as $baris)
                                @php
                                    $penerima = $baris['penerima'];
                                    $tanggalDiterima = $baris['tanggal_diterima'];
                                @endphp
                                <tr @if ($penerima) data-penerima-id="{{ $penerima->id }}" @endif>
                                    <td class="text-white-50 ps-3">{{ $loop->iteration }}</td>
                                    <td class="fw-semibold">
                                        {{ $baris['label'] }}
                                        @if ($baris['label'] && $baris['dari']->isNotEmpty())
                                            <div class="small fw-normal text-white-50">dari {{ $baris['dari']->pluck('name')->implode(', ') }}</div>
                                        @endif
                                    </td>
                                    <td class="text-nowrap {{ $tanggalDiterima ? '' : 'text-white-50' }}">{{ $baris['label'] ? ($tanggalDiterima?->format('d/m/Y') ?? '—') : '' }}</td>
                                    <td class="text-center pe-3">
                                        @if ($baris['label'])
                                            <input type="checkbox" class="form-check-input" @checked($tanggalDiterima) disabled
                                                   aria-label="{{ $tanggalDiterima ? 'Sudah diterima' : 'Belum diterima' }} oleh {{ $baris['label'] }}">
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="card-body text-center py-5">
                    <h5 class="fw-bold">{{ $surats->isEmpty() ? 'Belum ada agenda yang ditampilkan' : 'Pilih agenda surat' }}</h5>
                    <p class="text-muted mb-0">{{ $surats->isEmpty() ? 'Tidak ada surat ditemukan. Input surat masuk untuk menampilkan kontrol agenda.' : 'Buka kontrol agenda dari menu Pantau Surat.' }}</p>
                </div>
            @endif
        </div>
    @else
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <span class="fw-bold">Daftar Surat</span><span class="badge bg-secondary ms-2">Sesuai Akses</span>
                <div class="small text-muted mt-1">TU dan Direktur melihat seluruh surat. Akun lain hanya melihat surat yang diterima/didisposisikan atau diteruskan olehnya.</div>
            </div>
            @if (auth()->user()->role === 'tu')
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('surat.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>Input Surat</a>
                    <form method="POST" action="{{ route('surat.kosongkan') }}"
                          onsubmit="return confirm('Kosongkan SEMUA data surat? Semua surat, disposisi, notifikasi, dan file PDF akan dihapus. Akun tetap tersimpan.')">
                        @csrf
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Kosongkan Semua</button>
                    </form>
                </div>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>No Agenda</th>
                        <th>Tgl Terima</th>
                        <th>Jenis</th>
                        <th>No Surat</th>
                        <th>Asal Surat</th>
                        <th>Perihal</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($surats as $s)
                        @php($boleh = in_array(auth()->user()->role, ['tu', 'direktur'], true) || $s->diterima_saya || $s->dikirim_saya)
                        <tr>
                            <td class="fw-semibold">
                                {{ $s->nomor_agenda }}
                                @if ($s->diterima_saya)
                                    <span class="badge bg-success ms-1" title="Surat ini masuk ke akun Anda sesuai ceklis">Diterima</span>
                                @endif
                            </td>
                            <td>{{ $s->tanggal_terima->format('d/m/Y') }}</td>
                            <td>
                                <span class="badge bg-{{ match ($s->jenis_surat) { 'R' => 'danger', 'Kep' => 'warning', 'Sprin' => 'info', 'St' => 'secondary', default => 'primary'} }}">
                                    {{ $s->jenis_surat }}
                                </span>
                            </td>
                            <td>{{ $s->nomor_surat }}</td>
                            <td>{{ $s->asal_surat }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($s->perihal, 40) }}</td>
                            <td>
                                <span class="badge bg-{{ match ($s->status) { 'baru' => 'warning', 'diproses' => 'info', default => 'success'} }}">
                                    {{ $s->statusLabel() }}
                                </span>
                            </td>
                            <td class="text-nowrap">
                                @if ($boleh)
                                    <a href="{{ route('surat.show', $s) }}" class="btn btn-sm btn-outline-primary">Detail</a>
                                @endif
                                @if (auth()->user()->role === 'direktur')
                                    <a href="{{ route('surat.show', $s) }}#disposisi-direktur" class="btn btn-sm btn-outline-success">Disposisi</a>
                                @endif
                                @if (auth()->user()->role === 'tu')
                                    @if ($s->file_pdf)
                                        <a href="{{ route('surat.unduh', $s) }}" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-download me-1"></i>PDF
                                        </a>
                                    @endif
                                    <a href="{{ route('agenda.index', ['agenda' => $s->id]) }}" class="btn btn-sm btn-outline-secondary">Agenda</a>
                                @endif
                                @if ($boleh)
                                    <form method="POST" action="{{ route('surat.destroy', $s) }}" class="d-inline"
                                          onsubmit="return confirm('Hapus surat {{ $s->nomor_agenda }}? Disposisi, notifikasi, dan file PDF ikut terhapus.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Hapus surat" aria-label="Hapus surat {{ $s->nomor_agenda }}">
                                            <i class="bi bi-trash me-1"></i>Hapus
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada surat ditemukan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($surats->hasPages())
            <div class="card-footer bg-white">{{ $surats->links() }}</div>
        @endif
    </div>
    @endif
@endsection