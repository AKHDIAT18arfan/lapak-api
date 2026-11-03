<?php

declare(strict_types=1);

namespace App\Balapan;

final class Serempak implements SkenarioBalapan
{
    private const JENDELA_DETIK = 0.25;

    public function keterangan(): string
    {
        return 'Semua proses mulai dalam jendela '.(int) (self::JENDELA_DETIK * 1000).' milidetik';
    }

    public function proses(): int
    {
        return 8;
    }

    public function siapkan(): array
    {
        return [];
    }

    public function jalankan(array $konteks, int $nomor): string
    {
        $selisih = microtime(true) - $konteks['mulai'];

        return $selisih <= self::JENDELA_DETIK ? 'tepat waktu' : 'terlambat';
    }

    public function periksa(array $konteks, array $hasil): array
    {
        $terlambat = count(array_filter($hasil, fn (string $h) => $h !== 'tepat waktu'));

        return $terlambat === 0 ? [] : ["{$terlambat} proses mulai terlambat."];
    }
}
