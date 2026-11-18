<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - SISURAT</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; background: #0f172a; }
        .bg-foto { position: fixed; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: 50% 65%; z-index: -2; }
        .bg-gelap { position: fixed; inset: 0; background: rgba(15, 23, 42, .55); z-index: -1; }
    </style>
</head>
<body>
<img src="{{ asset('images/surat_menyurat_tni_3d.png') }}" alt="" class="bg-foto">
<div class="bg-gelap"></div>
<div class="container d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="col-md-6 col-lg-4 py-4">
        <div class="card shadow-lg border-0 rounded-4">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <img src="{{ asset('images/logo.png') }}" alt="Logo SISURAT" style="height: 90px;" class="mb-2">
                    <h4 class="fw-bold mb-0">SISURAT</h4>
                    <p class="text-muted small">Sistem Agenda &amp; Disposisi Surat</p>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger small">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('login.attempt') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input name="username" value="{{ old('username') }}" class="form-control" required autofocus placeholder="Masukkan username">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" name="password" class="form-control" required placeholder="Masukkan password">
                            <button class="btn btn-outline-secondary" type="button" onclick="toggleMata(this)" tabindex="-1" aria-label="Lihat password"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="remember" class="form-check-input" id="remember">
                        <label for="remember" class="form-check-label small">Ingat saya</label>
                    </div>
                    <button class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right me-1"></i>Login</button>
                </form>

                <div class="alert alert-info small mt-3 mb-0">
                    <strong>Lupa password?</strong> Semua aktor dapat menghubungi Super Admin Infolahta Ditkumad untuk mengganti password akun Anda. Sampaikan username dan jabatan Anda.
                    <a href="{{ route('bantuan-password.create') }}" class="btn btn-outline-primary btn-sm w-100 mt-2"><i class="bi bi-headset me-1" aria-hidden="true"></i>Hubungi Super Admin Infolahta Ditkumad</a>
                </div>
            </div>
        </div>
        <p class="text-center text-white-50 small mt-3">&copy; {{ date('Y') }} SISURAT</p>
    </div>
</div>
<script>
    function toggleMata(btn) {
        const input = btn.parentElement.querySelector('input');
        const icon = btn.querySelector('i');
        if (input.type === 'password') { input.type = 'text'; icon.className = 'bi bi-eye-slash'; }
        else { input.type = 'password'; icon.className = 'bi bi-eye'; }
    }
</script>
</body>
</html>