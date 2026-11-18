<?php

use App\Http\Controllers\AdminAkunController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BantuanPasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisposisiController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\SuratController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('bantuan-password', [BantuanPasswordController::class, 'create'])->name('bantuan-password.create');
Route::post('bantuan-password', [BantuanPasswordController::class, 'store'])
    ->middleware('throttle:3,10')->name('bantuan-password.store');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'login'])->name('login');
    Route::post('login', [AuthController::class, 'authenticate'])->name('login.attempt');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register'])->name('register.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::patch('password', [PasswordController::class, 'update'])->name('password.update');

    Route::middleware('role:super_admin')->prefix('admin/akun')->name('admin.akun.')->group(function () {
        Route::get('/', [AdminAkunController::class, 'index'])->name('index');
        Route::get('{user}/password', [AdminAkunController::class, 'edit'])->name('edit');
        Route::patch('{user}/password', [PasswordController::class, 'updateAkun'])->name('update');
    });

    Route::middleware('role:tu,direktur,wadir,kasubditbinum,staf')->group(function () {
        Route::get('agenda', [SuratController::class, 'index'])->middleware('role:tu')->name('agenda.index');
        Route::get('surat', [SuratController::class, 'index'])->name('surat.index');
        Route::get('surat/input', [SuratController::class, 'create'])->middleware('role:tu')->name('surat.create');
        Route::post('surat', [SuratController::class, 'store'])->middleware('role:tu')->name('surat.store');
        Route::post('surat/kosongkan', [SuratController::class, 'kosongkan'])->middleware('role:tu')->name('surat.kosongkan');
        Route::delete('surat/{surat}', [SuratController::class, 'destroy'])->name('surat.destroy');
        Route::get('surat/pratinjau-nomor', [SuratController::class, 'pratinjauNomor'])->middleware('role:tu')->name('surat.pratinjauNomor');
        Route::get('surat/{surat}', [SuratController::class, 'show'])->name('surat.show');
        Route::get('surat/{surat}/file', [SuratController::class, 'file'])->name('surat.file');
        Route::get('surat/{surat}/unduh', [SuratController::class, 'unduh'])->middleware('role:tu')->name('surat.unduh');
        Route::get('surat/{surat}/cetak', [SuratController::class, 'cetak'])->name('surat.cetak');

        Route::get('disposisi', [DisposisiController::class, 'index'])
            ->middleware('role:direktur,wadir,kasubditbinum')->name('disposisi.index');
        Route::post('surat/{surat}/disposisi', [DisposisiController::class, 'store'])
            ->middleware('role:direktur,wadir,kasubditbinum')->name('disposisi.store');
        Route::post('disposisi/{disposisi}/checklist', [DisposisiController::class, 'checklist'])->name('disposisi.checklist');
        Route::delete('disposisi/{disposisi}', [DisposisiController::class, 'destroy'])->name('disposisi.destroy');
    });

    Route::get('notifikasi', [NotifikasiController::class, 'index'])->name('notifikasi.index');
    Route::get('notifikasi/{id}/baca', [NotifikasiController::class, 'baca'])->name('notifikasi.baca');
    Route::delete('notifikasi/{id}', [NotifikasiController::class, 'destroy'])->name('notifikasi.destroy');
    Route::delete('notifikasi', [NotifikasiController::class, 'destroySemua'])->name('notifikasi.destroySemua');
    Route::post('notifikasi/baca-semua', [NotifikasiController::class, 'bacaSemua'])->name('notifikasi.bacaSemua');
});
