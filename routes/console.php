<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Schedule::command('lapak:pangkas-sesi')->dailyAt('03:15');

// Catatan Idempotency-Key yang sudah lama tidak mungkin diulang lagi oleh kliennya.
Schedule::command('lapak:pangkas-idempotensi')->dailyAt('03:30');

// LP-17: rantai log audit dihitung ulang setiap dini hari. Pemeriksaan yang gagal tanpa ada
// yang diberi tahu sama saja dengan tidak memeriksa, maka kegagalannya masuk log tingkat kritis.
Schedule::command('lapak:verifikasi-audit')
    ->dailyAt('03:45')
    ->onFailure(fn () => Log::critical('LP-17: rantai log audit putus.'));

// LP-10: checkout yang lewat batas bayar dibatalkan dan stoknya dikembalikan.
Schedule::command('lapak:batalkan-kedaluwarsa')->everyTenMinutes()->withoutOverlapping();

// LP-12: pesanan yang dikirim lebih dari masa tahan diselesaikan dan dananya dilepas.
Schedule::command('lapak:selesaikan-otomatis')->hourly()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
