<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Domain\Uang;
use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Enums\StatusPencairan;
use App\Exceptions\SaldoTidakCukup;
use App\Models\Pencairan;
use App\Models\Pengguna;
use App\Services\BukuBesar;
use App\Services\LayananPencairan;
use Illuminate\Support\Str;

final class PencairanGanda implements SkenarioBalapan
{
    private const SALDO_AWAL = 300_000;

    private const DIMINTA = 200_000;

    public function __construct(
        private readonly BukuBesar $buku,
        private readonly LayananPencairan $layanan,
    ) {}

    public function keterangan(): string
    {
        return 'Enam pengajuan pencairan atas saldo yang sama: hanya yang tercukupi diterima';
    }

    public function proses(): int
    {
        return 6;
    }

    public function siapkan(): array
    {
        $akhiran = Str::lower(Str::random(8));
        $penjual = Pengguna::factory()->penjual()->create([
            'surel' => "penjual-{$akhiran}@lapak.test",
        ]);

        $this->buku->pindahkan(
            $this->buku->dompetSistem(JenisDompet::Eksternal),
            $this->buku->dompetPenjual($penjual),
            Uang::dari(self::SALDO_AWAL),
            JenisMutasi::LepasDana,
            "BALAPAN-{$akhiran}",
            'Modal uji pencairan ganda',
        );

        return [
            'penjual' => $penjual->id,
            'tahanan_awal' => $this->buku->dompetSistem(JenisDompet::Pencairan)->refresh()->saldo_rupiah,
        ];
    }

    public function jalankan(array $konteks, int $nomor): string
    {
        $penjual = Pengguna::query()->findOrFail($konteks['penjual']);

        try {
            $this->layanan->ajukan($penjual, Uang::dari(self::DIMINTA), "Rekening uji {$nomor}");
        } catch (SaldoTidakCukup) {
            return '409 LP-13 saldo tidak cukup';
        }

        return 'diterima';
    }

    public function periksa(array $konteks, array $hasil): array
    {
        $penjual = Pengguna::query()->findOrFail($konteks['penjual']);
        $saldo = $this->buku->dompetPenjual($penjual)->refresh()->saldo_rupiah;
        $ditahan = $this->buku->dompetSistem(JenisDompet::Pencairan)->refresh()->saldo_rupiah
            - $konteks['tahanan_awal'];
        $diterima = count(array_filter($hasil, fn (string $h) => $h === 'diterima'));

        $tercatat = Pencairan::query()
            ->where('pengaju_id', $penjual->id)
            ->where('status', StatusPencairan::Menunggu)
            ->get();

        $pelanggaran = [];
        if ($diterima !== 1) {
            $pelanggaran[] = "{$diterima} pengajuan diterima, padahal saldo hanya cukup untuk 1.";
        }
        if ($saldo !== self::SALDO_AWAL - self::DIMINTA) {
            $pelanggaran[] = "Saldo penjual {$saldo}, seharusnya "
                .(self::SALDO_AWAL - self::DIMINTA).'.';
        }
        if ($ditahan !== (int) $tercatat->sum('jumlah_rupiah')) {
            $pelanggaran[] = "Dana ditahan {$ditahan}, tetapi pengajuan menunggu berjumlah "
                .$tercatat->sum('jumlah_rupiah').'.';
        }
        if ($tercatat->count() !== $diterima) {
            $pelanggaran[] = "Tersimpan {$tercatat->count()} pengajuan untuk {$diterima} jawaban "
                .'diterima.';
        }

        return $pelanggaran;
    }
}
