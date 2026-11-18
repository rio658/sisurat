@extends('layouts.app')

@section('title', 'Ganti Password Saya')

@section('content')
    <div class="mb-4">
        <h1 class="h3 fw-bold mb-2">Ganti Password Saya</h1>
        <p class="text-secondary mb-0">Halaman ini hanya mengubah password akun Anda sendiri, bukan password akun pengguna lain.</p>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <h2 class="h5 fw-bold mb-3">Akun Saya</h2>
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
            <div class="alert alert-info mb-0">
                Lupa password saat ini? Hubungi Super Admin Infolahta Ditkumad dengan menyampaikan username dan jabatan Anda.
                <a href="{{ route('bantuan-password.create') }}" class="btn btn-outline-primary btn-sm mt-2"><i class="bi bi-headset me-1" aria-hidden="true"></i>Hubungi Super Admin Infolahta Ditkumad</a>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 fw-bold mb-3">Password Baru</h2>
                    <form method="POST" action="{{ route('password.update') }}">
                        @csrf
                        @method('PATCH')
                        <div class="mb-4">
                            <label for="current_password" class="form-label fw-semibold">Password saat ini</label>
                            <input type="password" id="current_password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" autocomplete="current-password" required>
                            @error('current_password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">Password baru</label>
                            <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" minlength="8" maxlength="72" aria-describedby="password-help" required>
                            <div id="password-help" class="form-text">Minimal 8 karakter, maksimal 72 byte. Pilih password yang kuat dan jangan bagikan kepada orang lain.</div>
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
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Simpan Password Baru</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
