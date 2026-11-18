<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Domain\Uang;
use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Enums\StatusPencairan;
use App\Exceptions\GalatDomain;
use App\Models\Pencairan;
use App\Models\Pengguna;
use App\Services\BukuBesar;
use App\Services\LayananPencairan;
use Illuminate\Support\Str;

final class PutusanGanda implements SkenarioBalapan
{
    private const MODAL = 500_000;

    private const JUMLAH = 200_000;

    public function __construct(
        private readonly BukuBesar $buku,
        private readonly LayananPencairan $layanan,
    ) {}

    public function keterangan(): string
    {
        return 'Dua petugas memutuskan pencairan yang sama: tepat satu putusan berlaku';
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
        $petugasA = Pengguna::factory()->keuangan()->create([
            'surel' => "keuangan-a-{$akhiran}@lapak.test",
        ]);
        $petugasB = Pengguna::factory()->keuangan()->create([
            'surel' => "keuangan-b-{$akhiran}@lapak.test",
        ]);

        $this->buku->pindahkan(
            $this->buku->dompetSistem(JenisDompet::Eksternal),
            $this->buku->dompetPenjual($penjual),
            Uang::dari(self::MODAL),
            JenisMutasi::LepasDana,
            "BALAPAN-{$akhiran}",
            'Modal uji putusan ganda',
        );

        $pencairan = $this->layanan->ajukan(
            $penjual,
            Uang::dari(self::JUMLAH),
            'BCA 1234567890 a.n. Penjual Uji',
        );

        return [
            'pencairan' => $pencairan->id,
            'penjual' => $penjual->id,
            'petugas' => [$petugasA->id, $petugasB->id],
            'saldo_penjual_awal' => $this->buku->dompetPenjual($penjual)->refresh()->saldo_rupiah,
            'tahanan_awal' => $this->buku->dompetSistem(JenisDompet::Pencairan)->refresh()->saldo_rupiah,
        ];
    }

    public function jalankan(array $konteks, int $nomor): string
    {
        $pencairan = Pencairan::query()->findOrFail($konteks['pencairan']);
        $petugas = Pengguna::query()->findOrFail($konteks['petugas'][$nomor % 2]);

        try {
            if ($nomor % 2 === 0) {
                $this->layanan->setujui($pencairan, $petugas);

                return 'disetujui';
            }

            $this->layanan->tolak($pencairan, $petugas, 'Nomor rekening tidak sesuai');

            return 'ditolak';
        } catch (GalatDomain) {
            return '409 LP-16';
        }
    }

    public function periksa(array $konteks, array $hasil): array
    {
        $pencairan = Pencairan::query()->findOrFail($konteks['pencairan']);
        $penjual = Pengguna::query()->findOrFail($konteks['penjual']);

        $menang = array_values(array_filter($hasil, fn (string $h) => $h !== '409 LP-16'));
        $saldoPenjual = $this->buku->dompetPenjual($penjual)->refresh()->saldo_rupiah;
        $tahanan = $this->buku->dompetSistem(JenisDompet::Pencairan)->refresh()->saldo_rupiah;

        $pelanggaran = [];
        if (count($menang) !== 1) {
            $pelanggaran[] = count($menang).' proses berhasil memutuskan, seharusnya tepat 1.';

            return $pelanggaran;
        }

        $putusan = $menang[0];
        $statusHarapan = $putusan === 'disetujui'
            ? StatusPencairan::Disetujui
            : StatusPencairan::Ditolak;

        if ($pencairan->status !== $statusHarapan) {
            $pelanggaran[] = "Status pencairan {$pencairan->status->value}, seharusnya {$statusHarapan->value}.";
        }

        $saldoHarapan = $putusan === 'disetujui'
            ? $konteks['saldo_penjual_awal']
            : $konteks['saldo_penjual_awal'] + self::JUMLAH;

        if ($saldoPenjual !== $saldoHarapan) {
            $pelanggaran[] = "Saldo penjual {$saldoPenjual}, seharusnya {$saldoHarapan}.";
        }

        if ($tahanan !== $konteks['tahanan_awal'] - self::JUMLAH) {
            $pelanggaran[] = "Saldo dompet pencairan {$tahanan}, seharusnya "
                .($konteks['tahanan_awal'] - self::JUMLAH).'.';
        }

        return $pelanggaran;
    }
}
