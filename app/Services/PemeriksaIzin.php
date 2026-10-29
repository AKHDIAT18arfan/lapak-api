<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NamaIzin;
use App\Models\Pengguna;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class PemeriksaIzin
{
    private const KUNCI_GENERASI = 'izin:generasi';

    public function punya(Pengguna $pengguna, NamaIzin $izin): bool
    {
        return in_array($izin->value, $this->daftar($pengguna), true);
    }

    public function daftar(Pengguna $pengguna): array
    {
        return Cache::remember(
            $this->kunci($pengguna),
            now()->addMinutes(config('lapak.otorisasi.ingat_menit')),
            fn (): array => $this->bacaDariBasisData($pengguna),
        );
    }

    public function lupakan(Pengguna $pengguna): void
    {
        Cache::forget($this->kunci($pengguna));
    }

    public function lupakanSemua(): void
    {
        Cache::forever(self::KUNCI_GENERASI, $this->generasi() + 1);
    }

    private function bacaDariBasisData(Pengguna $pengguna): array
    {
        return DB::table('izin')
            ->join('peran_izin', 'peran_izin.izin_id', '=', 'izin.id')
            ->join('pengguna_peran', 'pengguna_peran.peran_id', '=', 'peran_izin.peran_id')
            ->where('pengguna_peran.pengguna_id', $pengguna->id)
            ->distinct()
            ->orderBy('izin.nama')
            ->pluck('izin.nama')
            ->all();
    }

    private function kunci(Pengguna $pengguna): string
    {
        return "izin:{$this->generasi()}:{$pengguna->id}";
    }

    private function generasi(): int
    {
        return (int) Cache::get(self::KUNCI_GENERASI, 1);
    }
}
