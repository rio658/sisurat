<?php

namespace App\Http\Controllers;

use App\Models\Disposisi;
use App\Models\Surat;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->role === 'super_admin') {
            return redirect()->route('admin.akun.index');
        }

        if ($user->role === 'staf') {
            $disposisiSaya = Disposisi::with(['surat', 'dari'])
                ->where('ke_user_id', $user->id)->latest()->get();

            $stats = [
                'diterima' => $disposisiSaya->pluck('surat_id')->unique()->count(),
                'tugas' => $disposisiSaya->count(),
                'belum_dibaca' => $disposisiSaya->whereNull('dibaca_at')->count(),
                'diproses' => $disposisiSaya->whereNotNull('dibaca_at')->whereNull('selesai_at')->count(),
                'selesai' => $disposisiSaya->whereNotNull('selesai_at')->count(),
            ];

            $sebutan = $user->jabatanNama() ?? $user->name;

            return view('dashboard.staf', compact('stats', 'disposisiSaya', 'sebutan'));
        }

        $base = Surat::untukUser($user);

        $stats = [
            'total' => (clone $base)->count(),
            'baru' => (clone $base)->where('status', 'baru')->count(),
            'diproses' => (clone $base)->where('status', 'diproses')->count(),
            'selesai' => (clone $base)->where('status', 'selesai')->count(),
        ];

        $suratTerbaru = (clone $base)->latest()->limit(5)->get();

        if ($user->role === 'tu') {
            return view('dashboard.tu', compact('stats', 'suratTerbaru'));
        }

        $perluDisposisi = (clone $base)->where(function ($query) use ($user) {
            $query->where('status', 'baru')
                ->orWhereHas('disposisis', fn ($sub) => $sub->where('ke_user_id', $user->id)->whereNull('selesai_at'));
        })->latest()->limit(5)->get();

        return view('dashboard.pimpinan', compact('stats', 'suratTerbaru', 'perluDisposisi'));
    }
}
