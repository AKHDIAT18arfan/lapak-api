<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('lapak:pangkas-sesi')->dailyAt('03:15');

// LP-10: checkout yang lewat batas bayar dibatalkan dan stoknya dikembalikan.
Schedule::command('lapak:batalkan-kedaluwarsa')->everyTenMinutes()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
