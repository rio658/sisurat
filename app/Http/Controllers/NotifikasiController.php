<?php

namespace App\Http\Controllers;

class NotifikasiController extends Controller
{
    public function index()
    {
        $notifikasis = auth()->user()->notifications()->latest()->paginate(15);

        return view('notifikasi.index', compact('notifikasis'));
    }

    public function baca(string $id)
    {
        $notifikasi = auth()->user()->notifications()->findOrFail($id);
        $notifikasi->markAsRead();

        return redirect($notifikasi->data['url'] ?? route('dashboard'));
    }

    public function bacaSemua()
    {
        auth()->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function destroy(string $id)
    {
        $notifikasi = auth()->user()->notifications()->findOrFail($id);
        $notifikasi->delete();

        return back()->with('success', 'Notifikasi dihapus.');
    }

    public function destroySemua()
    {
        auth()->user()->notifications()->delete();

        return back()->with('success', 'Semua notifikasi Anda telah dihapus.');
    }
}