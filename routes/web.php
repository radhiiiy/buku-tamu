<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PublicVisitController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\VisitController;
use Illuminate\Support\Facades\Route;

// ── Buku tamu (publik, mobile) ────────────────────────────────────────────
Route::name('public.')->controller(PublicVisitController::class)->group(function () {
    Route::get('/', 'welcome')->name('welcome');
    Route::get('/bahasa/{locale}', 'language')->whereIn('locale', ['id', 'en'])->name('lang');
    Route::get('/kunjungan', 'form')->name('form');
    Route::post('/kunjungan', 'store')->middleware('throttle:10,1')->name('store');
    Route::get('/kunjungan/berhasil/{visit}', 'done')->middleware('signed')->name('done');
});

// ── Autentikasi resepsionis ───────────────────────────────────────────────
Route::get('/masuk', [AuthController::class, 'create'])->middleware('guest')->name('login');
Route::post('/masuk', [AuthController::class, 'store'])->middleware(['guest', 'throttle:6,1'])->name('login.store');
Route::post('/keluar', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

// ── Dasbor resepsionis ────────────────────────────────────────────────────
Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::controller(VisitController::class)->group(function () {
        Route::get('/kunjungan', 'index')->name('visits.index');
        Route::get('/kunjungan/tambah', 'create')->name('visits.create');
        Route::post('/kunjungan', 'store')->name('visits.store');
        Route::post('/kunjungan-massal', 'bulk')->name('visits.bulk');
        Route::get('/kunjungan/{visit}', 'show')->whereNumber('visit')->name('visits.show');
        Route::get('/kunjungan/{visit}/cetak', 'print')->whereNumber('visit')->name('visits.print');
        Route::get('/kunjungan/{visit}/pratinjau-checkout', 'checkoutPreview')->whereNumber('visit')->name('visits.checkout.preview');
        Route::post('/kunjungan/{visit}/konfirmasi', 'confirm')->whereNumber('visit')->name('visits.confirm');
        Route::post('/kunjungan/{visit}/tolak', 'reject')->whereNumber('visit')->name('visits.reject');
        Route::post('/kunjungan/{visit}/pengingat', 'remind')->whereNumber('visit')->name('visits.remind');
        Route::post('/kunjungan/{visit}/checkout', 'checkout')->whereNumber('visit')->name('visits.checkout');
    });

    Route::controller(ReportController::class)->group(function () {
        Route::get('/laporan', 'index')->name('reports.index');
        Route::get('/laporan/excel', 'excel')->name('reports.excel');
        Route::get('/laporan/excel/pratinjau', 'preview')->name('reports.preview');
        Route::post('/laporan/excel', 'excelStore')->name('reports.excel.store');
        Route::get('/laporan/ekspor/{export}/unduh', 'download')->whereNumber('export')->name('reports.download');
        Route::get('/laporan/sheets', 'sheets')->name('reports.sheets');
        Route::post('/laporan/sheets', 'sheetsStore')->name('reports.sheets.store');
    });

    Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifikasi/poll', [NotificationController::class, 'poll'])->name('notifications.poll');
    Route::post('/notifikasi/baca-semua', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifikasi/preferensi', [NotificationController::class, 'preferences'])->name('notifications.prefs');
    Route::get('/notifikasi/{notification}/buka', [NotificationController::class, 'open'])->whereNumber('notification')->name('notifications.open');

    Route::controller(SettingController::class)->prefix('pengaturan')->group(function () {
        Route::get('/{section?}', 'show')->whereIn('section', ['profil', 'tim', 'penerima', 'notifikasi', 'integrasi', 'privasi'])->name('settings.show');
        Route::put('/profil', 'updateOffice')->name('settings.office');
        Route::put('/privasi', 'updatePrivacy')->name('settings.privacy');
        Route::post('/tim', 'storeMember')->name('settings.members.store');
        Route::post('/tim/{user}/izin', 'togglePhonePermission')->whereNumber('user')->name('settings.members.permission');
        Route::post('/penerima', 'storeHost')->name('settings.hosts.store');
        Route::put('/penerima/{host}', 'updateHost')->whereNumber('host')->name('settings.hosts.update');
        Route::post('/penerima/{host}/aktif', 'toggleHost')->whereNumber('host')->name('settings.hosts.toggle');
        Route::delete('/penerima/{host}', 'destroyHost')->whereNumber('host')->name('settings.hosts.destroy');
    });

    Route::get('/google/hubungkan', [ReportController::class, 'googleConnect'])->name('google.connect');
    Route::post('/google/putuskan', [ReportController::class, 'googleDisconnect'])->name('google.disconnect');

    Route::view('/bantuan', 'help.index')->name('help');
});

// Callback OAuth Google (harus sama dengan GOOGLE_REDIRECT_URI)
Route::get('/google/callback', [ReportController::class, 'googleCallback'])->middleware('auth')->name('google.callback');
