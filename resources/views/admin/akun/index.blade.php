@extends('layouts.app')

@section('title', 'Super Admin · Kelola Akun')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <span class="badge text-bg-primary mb-2">Administrasi · Super Admin</span>
            <h1 class="h3 fw-bold mb-2">Kelola Akun</h1>
            <p class="text-secondary mb-0">Ganti password akun seluruh peran dan jabatan atas permintaan pemilik akun.</p>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3 px-4">
                <div class="text-secondary small">Total akun</div>
                <div class="fs-3 fw-bold">{{ $jumlahAkun }}</div>
            </div>
        </div>
    </div>

    <div class="alert alert-info">
        <i class="bi bi-shield-lock me-2" aria-hidden="true"></i>Pastikan identitas pemilik akun sebelum mengganti password. Sampaikan password baru secara pribadi.
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.akun.index') }}" class="mb-4" role="search">
                <label for="q" class="form-label fw-semibold">Cari akun</label>
                <div class="d-flex flex-wrap gap-2">
                    <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control flex-grow-1" style="flex-basis: 260px;" placeholder="Nama, username, atau jabatan" aria-describedby="search-help">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1" aria-hidden="true"></i>Cari</button>
                    @if (request()->filled('q'))
                        <a href="{{ route('admin.akun.index') }}" class="btn btn-outline-secondary">Hapus Pencarian</a>
                    @endif
                </div>
                <div id="search-help" class="form-text">Cari berdasarkan nama, username, atau jabatan pemilik akun.</div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <caption class="visually-hidden">Daftar akun seluruh peran dan jabatan untuk penggantian password oleh Super Admin.</caption>
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Nama</th>
                            <th scope="col">Username</th>
                            <th scope="col">Peran</th>
                            <th scope="col">Jabatan</th>
                            <th scope="col" class="text-end">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $akun)
                            <tr>
                                <th scope="row" class="fw-semibold text-break">{{ $akun->name }}</th>
                                <td class="text-break">{{ $akun->username }}</td>
                                <td>{{ $akun->roleLabel() }}</td>
                                <td>{{ $akun->jabatanNama() ?: $akun->roleLabel() }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.akun.edit', $akun) }}" class="btn btn-outline-primary btn-sm text-nowrap">
                                        <i class="bi bi-key me-1" aria-hidden="true"></i>Ganti Password<span class="visually-hidden"> untuk {{ $akun->username }}</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-secondary py-5">
                                    @if (request()->filled('q'))
                                        Tidak ada akun yang sesuai dengan pencarian Anda.
                                    @else
                                        Belum ada akun yang tersedia.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                {{ $users->withQueryString()->links() }}
            </div>
        </div>
    </div>
@endsection
