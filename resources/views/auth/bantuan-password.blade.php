<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hubungi Super Admin Infolahta Ditkumad - SISURAT</title>
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
<div class="bg-gelap" aria-hidden="true"></div>
<main class="container d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="col-12 col-md-8 col-lg-6 py-4">
        <div class="card shadow-lg border-0 rounded-4">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <img src="{{ asset('images/logo.png') }}" alt="Logo SISURAT" style="height: 90px;" class="mb-2">
                    <div class="h4 fw-bold mb-0">SISURAT</div>
                    <p class="text-muted small">Sistem Agenda &amp; Disposisi Surat</p>
                    <h1 class="h4 fw-bold">Hubungi Super Admin Infolahta Ditkumad</h1>
                    <p class="text-muted small mb-0">Semua aktor yang lupa password dapat mengajukan bantuan untuk mengakses akun Anda.</p>
                </div>

                @if (session('success'))
                    <div class="alert alert-success small" role="status">{{ session('success') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger small" role="alert">
                        <strong>Permintaan belum dapat dikirim. Periksa kembali isian Anda.</strong>
                        <ul class="mb-0 mt-2">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="alert alert-info small" id="panduan-bantuan">
                    <strong>Jangan menuliskan password lama maupun password baru di sini.</strong>
                    Pengiriman permintaan tidak mengubah password secara otomatis. Super Admin Infolahta Ditkumad akan memverifikasi identitas Anda terlebih dahulu.
                </div>
                <p class="text-muted small">Kolom bertanda * wajib diisi.</p>

                <form method="POST" action="{{ route('bantuan-password.store') }}" aria-describedby="panduan-bantuan">
                    @csrf
                    @if ($user)
                        <h2 class="h6 fw-bold">Identitas akun Anda</h2>
                        <dl class="row small mb-4">
                            <dt class="col-sm-4">Nama</dt>
                            <dd class="col-sm-8 text-break">{{ $user->name }}</dd>
                            <dt class="col-sm-4">Username</dt>
                            <dd class="col-sm-8 text-break">{{ $user->username }}</dd>
                            <dt class="col-sm-4">Jabatan</dt>
                            <dd class="col-sm-8 text-break">{{ $user->jabatanNama() ?: $user->roleLabel() }}</dd>
                        </dl>
                    @else
                        <div class="mb-3">
                            <label for="nama" class="form-label">Nama *</label>
                            <input type="text" id="nama" name="nama" value="{{ is_string(old('nama')) ? old('nama') : '' }}" class="form-control @error('nama') is-invalid @enderror" required maxlength="100" autocomplete="name" @error('nama') aria-invalid="true" aria-describedby="nama-error" @enderror>
                            @error('nama')
                                <div id="nama-error" class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" id="username" name="username" value="{{ is_string(old('username')) ? old('username') : '' }}" class="form-control @error('username') is-invalid @enderror" required maxlength="255" autocomplete="username" @error('username') aria-invalid="true" aria-describedby="username-error" @enderror>
                            @error('username')
                                <div id="username-error" class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="jabatan" class="form-label">Jabatan *</label>
                            <select id="jabatan" name="jabatan" class="form-select @error('jabatan') is-invalid @enderror" required @error('jabatan') aria-invalid="true" aria-describedby="jabatan-error" @enderror>
                                <option value="">Pilih jabatan Anda</option>
                                @foreach ($jabatanList as $kode => $label)
                                    <option value="{{ $kode }}" @selected(old('jabatan') === $kode)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('jabatan')
                                <div id="jabatan-error" class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif

                    <div class="mb-3">
                        <label for="kontak" class="form-label">Nomor WhatsApp / telepon *</label>
                        <input type="tel" id="kontak" name="kontak" value="{{ is_string(old('kontak')) ? old('kontak') : '' }}" class="form-control @error('kontak') is-invalid @enderror" required maxlength="25" autocomplete="tel" aria-describedby="kontak-help @error('kontak') kontak-error @enderror" @error('kontak') aria-invalid="true" @enderror>
                        <div id="kontak-help" class="form-text">Super Admin Infolahta Ditkumad akan menghubungi nomor ini secara manual. Tidak ada pengiriman WhatsApp otomatis.</div>
                        @error('kontak')
                            <div id="kontak-error" class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="pesan" class="form-label">Pesan (opsional)</label>
                        <textarea id="pesan" name="pesan" class="form-control @error('pesan') is-invalid @enderror" maxlength="1000" rows="3" aria-describedby="pesan-help @error('pesan') pesan-error @enderror" @error('pesan') aria-invalid="true" @enderror>{{ is_string(old('pesan')) ? old('pesan') : '' }}</textarea>
                        <div id="pesan-help" class="form-text">Jelaskan kendala Anda, maksimal 1.000 karakter. Jangan sertakan password.</div>
                        @error('pesan')
                            <div id="pesan-error" class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send me-1" aria-hidden="true"></i>Kirim Permintaan Bantuan</button>
                </form>

                <div class="text-center mt-3">
                    @if ($user)
                        <a href="{{ route('password.edit') }}" class="small"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Kembali ke pengaturan password</a>
                    @else
                        <a href="{{ route('login') }}" class="small"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Kembali ke login</a>
                    @endif
                </div>
            </div>
        </div>
        <p class="text-center text-white-50 small mt-3">&copy; {{ date('Y') }} SISURAT</p>
    </div>
</main>
</body>
</html>
