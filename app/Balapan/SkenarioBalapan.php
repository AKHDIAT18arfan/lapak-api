<?php

declare(strict_types=1);

namespace App\Balapan;

interface SkenarioBalapan
{
    public function keterangan(): string;

    public function proses(): int;

    public function siapkan(): array;

    public function jalankan(array $konteks, int $nomor): string;

    public function periksa(array $konteks, array $hasil): array;
}
