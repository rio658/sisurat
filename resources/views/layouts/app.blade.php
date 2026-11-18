<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') - SISURAT</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; }
        .bg-foto { position: fixed; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: 50% 65%; z-index: -2; }
        .bg-lapis { position: fixed; inset: 0; background: rgba(241, 245, 249, .6); z-index: -1; }
        .navbar-utama { background: rgba(255, 255, 255, .9); backdrop-filter: blur(4px); }
        .sidebar { width: 250px; min-height: 100vh; background: #0f172a; position: fixed; top: 0; left: 0; z-index: 100; }
        .sidebar .nav-link { color: #cbd5e1; border-radius: .5rem; margin-bottom: .25rem; }
        .sidebar .nav-link:hover { color: #fff; background: #1e293b; }
        .sidebar .nav-link.active { background: #2563eb; color: #fff; }
        .main { margin-left: 250px; }
        @media (max-width: 768px) {
            .sidebar { position: static; width: 100%; min-height: auto; }
            .main { margin-left: 0; }
        }
    </style>
</head>
<body>
<img src="{{ asset('images/surat_menyurat_tni_3d.png') }}" alt="" class="bg-foto">
<div class="bg-lapis"></div>
@php
    $pengguna = auth()->user();
    $belumDibaca = $pengguna->unreadNotifications()->count();
    $notifikasiTerbaru = $pengguna->unreadNotifications()->latest()->limit(5)->get();
@endphp
<div class="sidebar d-flex flex-column p-3">
    <a href="{{ route('dashboard') }}" class="text-white text-decoration-none fs-4 fw-bold d-flex align-items-center">
        <img src="{{ asset('images/logo.png') }}" alt="Logo SISURAT" style="height: 34px;" class="me-2"> SISURAT
    </a>
    <span class="text-secondary small">Agenda &amp; Disposisi Surat</span>
    <hr class="text-secondary">
    <ul class="nav nav-pills flex-column mb-auto">
        @if ($pengguna->role === 'super_admin')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.akun.*') ? 'active' : '' }}" href="{{ route('admin.akun.index') }}">
                    <i class="bi bi-people me-2"></i>Kelola Akun
                </a>
            </li>
        @else
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                    <i class="bi bi-speedometer2 me-2"></i>Dashboard
                </a>
            </li>
            @if (in_array($pengguna->role, ['direktur', 'wadir', 'kasubditbinum'], true))
                <li>
                    <a class="nav-link {{ request()->routeIs('disposisi.index') ? 'active' : '' }}" href="{{ route('disposisi.index') }}">
                        <i class="bi bi-send me-2"></i>Disposisi
                    </a>
                </li>
            @endif
            @if ($pengguna->role === 'tu')
                <li>
                    <a class="nav-link {{ request()->routeIs('agenda.index') ? 'active' : '' }}" href="{{ route('agenda.index') }}">
                        <i class="bi bi-journal-text me-2"></i>Agenda
                    </a>
                </li>
            @endif
            <li>
                <a class="nav-link {{ request()->routeIs('surat.index') ? 'active' : '' }}" href="{{ route('surat.index') }}">
                    <i class="bi bi-eye me-2"></i>Pantau Surat
                </a>
            </li>
            @if ($pengguna->role === 'tu')
                <li>
                    <a class="nav-link {{ request()->routeIs('surat.create') ? 'active' : '' }}" href="{{ route('surat.create') }}">
                        <i class="bi bi-plus-circle me-2"></i>Input Surat Masuk
                    </a>
                </li>
            @endif
        @endif
        <li>
            <a class="nav-link {{ request()->routeIs('password.*') ? 'active' : '' }}" href="{{ route('password.edit') }}">
                <i class="bi bi-key me-2"></i>Ganti Password Saya
            </a>
        </li>
        <li>
            <a class="nav-link {{ request()->routeIs('bantuan-password.*') ? 'active' : '' }}" href="{{ route('bantuan-password.create') }}">
                <i class="bi bi-headset me-2" aria-hidden="true"></i>Hubungi Super Admin
            </a>
        </li>
        <li>
            <a class="nav-link {{ request()->routeIs('notifikasi.index') ? 'active' : '' }}" href="{{ route('notifikasi.index') }}">
                <i class="bi bi-bell me-2"></i>Notifikasi
                @if ($belumDibaca)<span class="badge bg-danger ms-1">{{ $belumDibaca }}</span>@endif
            </a>
        </li>
    </ul>
    <hr class="text-secondary">
    <div class="text-white small">
        <div class="fw-bold">{{ $pengguna->name }}</div>
        <div class="text-secondary">{{ $pengguna->roleLabel() }}</div>
        <form method="POST" action="{{ route('logout') }}" class="mt-2">
            @csrf
            <button class="btn btn-outline-light btn-sm w-100"><i class="bi bi-box-arrow-right me-1"></i>Logout</button>
        </form>
    </div>
</div>

<div class="main">
    <nav class="navbar navbar-utama border-bottom px-4 py-2 sticky-top">
        <span class="navbar-brand fw-semibold mb-0">@yield('title', 'Dashboard')</span>
        <div class="dropdown">
            <a class="btn btn-light position-relative dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-label="Notifikasi">
                <i class="bi bi-bell fs-5"></i>
                @if ($belumDibaca)
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ $belumDibaca }}</span>
                @endif
            </a>
            <div class="dropdown-menu dropdown-menu-end shadow" style="width: 340px;">
                <h6 class="dropdown-header">Notifikasi</h6>
                @forelse ($notifikasiTerbaru as $n)
                    <a class="dropdown-item small text-wrap" href="{{ route('notifikasi.baca', $n->id) }}">
                        <div class="fw-bold">{{ $n->data['judul'] }}</div>
                        <div class="text-secondary">{{ $n->data['pesan'] }}</div>
                        <div class="text-muted" style="font-size: .75rem;">{{ $n->created_at->diffForHumans() }}</div>
                    </a>
                @empty
                    <span class="dropdown-item-text text-muted small">Tidak ada notifikasi baru.</span>
                @endforelse
                <div class="dropdown-divider"></div>
                <a class="dropdown-item text-center small" href="{{ route('notifikasi.index') }}">Lihat semua</a>
                <form method="POST" action="{{ route('notifikasi.bacaSemua') }}">
                    @csrf
                    <button class="dropdown-item text-center small text-primary">Tandai semua dibaca</button>
                </form>
            </div>
        </div>
    </nav>

    <main class="p-4">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>