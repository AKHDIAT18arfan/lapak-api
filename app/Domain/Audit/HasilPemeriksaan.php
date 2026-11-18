<?php

declare(strict_types=1);

namespace App\Domain\Audit;

final readonly class HasilPemeriksaan
{
    /** @param list<string> $temuan */
    public function __construct(
        public int $jumlah,
        public string $hashTerakhir,
        public array $temuan = [],
    ) {}

    public function utuh(): bool
    {
        return $this->temuan === [];
    }

    public function jangkar(): string
    {
        return "{$this->jumlah}:{$this->hashTerakhir}";
    }
}
