<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class BalapanAnak extends Command
{
    protected $signature = 'lapak:balapan-anak {skenario} {nomor} {muatan}';

    protected $description = 'Proses anak uji balapan';

    protected $hidden = true;

    public function handle(): int
    {
        if (app()->isProduction()) {
            return self::FAILURE;
        }

        $skenario = Balapan::muat($this->argument('skenario'));
        $muatan = base64_decode($this->argument('muatan'));
        $konteks = json_decode($muatan, true, 512, JSON_THROW_ON_ERROR);

        DB::connection()->getPdo();

        $tunggu = $konteks['mulai'] - microtime(true);
        if ($tunggu > 0) {
            usleep((int) ($tunggu * 1_000_000));
        }

        try {
            $hasil = $skenario->jalankan($konteks, (int) $this->argument('nomor'));
        } catch (Throwable $galat) {
            $pesan = $galat instanceof QueryException
                ? $galat->getMessage()
                : class_basename($galat).': '.$galat->getMessage();
            $hasil = 'GALAT '.Str::limit($pesan, 64);
        }

        $this->line($hasil);

        return self::SUCCESS;
    }
}
