@extends('layouts.app')

@section('title', 'Super Admin · Ganti Password')

@section('content')
    <div class="mb-4">
        <span class="badge text-bg-primary mb-2">Administrasi · Super Admin</span>
        <h1 class="h3 fw-bold mb-2">Ganti Password Akun</h1>
        <p class="text-secondary mb-0">Periksa akun tujuan dan konfirmasikan dengan password Super Admin Anda.</p>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <h2 class="h5 fw-bold mb-3">Akun yang Dipilih</h2>
                    <dl class="mb-0">
                        <dt class="text-secondary small">Nama</dt>
                        <dd class="fs-5 fw-semibold text-break">{{ $user->name }}</dd>
                        <dt class="text-secondary small">Username</dt>
                        <dd class="fs-5 fw-semibold text-break">{{ $user->username }}</dd>
                        <dt class="text-secondary small">Peran</dt>
                        <dd>{{ $user->roleLabel() }}</dd>
                        <dt class="text-secondary small">Jabatan</dt>
                        <dd class="mb-0">{{ $user->jabatanNama() ?: $user->roleLabel() }}</dd>
                    </dl>
                </div>
            </div>
            <div class="alert alert-warning mb-0">
                <i class="bi bi-shield-lock me-2" aria-hidden="true"></i>Pastikan identitas pemilik akun sebelum mengganti password. Sampaikan password baru secara pribadi.
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 fw-bold mb-3">Password Baru</h2>
                    <form method="POST" action="{{ route('admin.akun.update', $user) }}">
                        @csrf
                        @method('PATCH')
                        <div class="mb-4">
                            <label for="current_password" class="form-label fw-semibold">Password Super Admin Anda (konfirmasi)</label>
                            <input type="password" id="current_password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" autocomplete="current-password" aria-describedby="current-password-help" required>
                            <div id="current-password-help" class="form-text">Masukkan password akun Super Admin Anda, bukan password pemilik akun yang dipilih.</div>
                            @error('current_password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">Password baru untuk akun yang dipilih</label>
                            <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" minlength="8" maxlength="72" aria-describedby="password-help" required>
                            <div id="password-help" class="form-text">Minimal 8 karakter, maksimal 72 byte. Pilih password yang kuat dan sampaikan hanya kepada pemilik akun.</div>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label fw-semibold">Ulangi password baru</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control @error('password_confirmation') is-invalid @enderror" autocomplete="new-password" minlength="8" maxlength="72" required>
                            @error('password_confirmation')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Simpan Password Baru</button>
                            <a href="{{ route('admin.akun.index') }}" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
