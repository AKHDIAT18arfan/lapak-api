<?php

use App\Http\Controllers\BukuBesarController;
use App\Http\Controllers\DompetController;
use App\Http\Controllers\PembayaranSayaController;
use App\Http\Controllers\PencairanSayaController;
use App\Http\Controllers\PencairanStafController;
use App\Http\Controllers\WebhookPembayaranController;
use Illuminate\Support\Facades\Route;

// Dompet milik sendiri: hanya pemegang izin dompet.lihat, yaitu penjual.
Route::middleware(['auth:sesi', 'izin:dompet.lihat'])->group(function (): void {
    Route::get('dompet', [DompetController::class, 'show'])->name('dompet.show');

    // Sesi segar: kunci yang sah saja belum cukup untuk membuka rincian uang (LP-15).
    Route::get('dompet/mutasi', [DompetController::class, 'mutasi'])
        ->middleware('sesi.segar')
        ->name('dompet.mutasi');
});

// Buku besar seluruh marketplace: staf pemegang izin buku-besar.lihat, selalu dengan sesi segar.
// Admin tidak memegang izin ini. Mengelola peran dan toko tidak berarti boleh melihat uang.
Route::middleware(['auth:sesi', 'izin:buku-besar.lihat', 'sesi.segar'])
    ->prefix('buku-besar')
    ->name('buku-besar.')
    ->whereNumber('id')
    ->group(function (): void {
        Route::get('dompet', [BukuBesarController::class, 'index'])->name('dompet.index');
        Route::get('dompet/{id}/mutasi', [BukuBesarController::class, 'mutasi'])->name('dompet.mutasi');
    });

// Pencairan saldo sendiri (LP-16). Izin pencairan.ajukan lahir bersama rute ini.
Route::middleware(['auth:sesi', 'izin:pencairan.ajukan'])->group(function (): void {
    Route::get('dompet/pencairan', [PencairanSayaController::class, 'index'])
        ->name('dompet.pencairan.index');

    // Mengeluarkan uang: sesi segar (LP-15), batas laju, dan Idempotency-Key (LP-11).
    Route::post('dompet/pencairan', [PencairanSayaController::class, 'store'])
        ->middleware(['sesi.segar', 'throttle:transaksi', 'idempoten'])
        ->name('dompet.pencairan.store');
});

// Keputusan staf atas pencairan. Auditor tidak memegang izin pencairan.setujui.
// Alamatnya sengaja BUKAN di bawah /buku-besar: awalan itu hanya-baca, dan dijaga uji Sesi 3.
Route::middleware(['auth:sesi', 'izin:pencairan.setujui', 'sesi.segar'])
    ->prefix('keuangan/pencairan')
    ->name('keuangan.pencairan.')
    ->group(function (): void {
        Route::get('/', [PencairanStafController::class, 'index'])->name('index');

        Route::middleware('idempoten')->group(function (): void {
            Route::post('{kode}/setujui', [PencairanStafController::class, 'setujui'])
                ->name('setujui');
            Route::post('{kode}/tolak', [PencairanStafController::class, 'tolak'])->name('tolak');
        });
    });

// Tagihan milik pembeli yang sedang masuk. Pembeli hanya dapat MELIHAT tagihannya.
Route::get('pembayaran/{kode}', [PembayaranSayaController::class, 'show'])
    ->middleware(['auth:sesi', 'izin:pesanan.buat'])
    ->name('pembayaran.show');

// Satu-satunya rute tulis tanpa kunci sesi. Pemanggilnya gerbang pembayaran, dan yang
// menjaganya adalah tanda tangan HMAC di dalam controller. Terdaftar pada RUTE_PUBLIK.
Route::post('webhook/pembayaran', WebhookPembayaranController::class)->name('webhook.pembayaran');
