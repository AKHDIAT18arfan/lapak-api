<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Domain\Pesanan\PelakuPesanan;
use App\Domain\Pesanan\StatusPesanan;
use App\Enums\JenisDompet;
use App\Exceptions\TransisiPesananTidakSah;
use App\Models\LogAudit;
use App\Models\MutasiSaldo;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Toko;
use App\Services\BukuBesar;
use App\Services\LayananPesanan;
use App\Services\PenyelesaianOtomatis;
use Illuminate\Support\Str;

final class TerimaSerempak implements SkenarioBalapan
{
    use SiapkanPesanan;

    public function __construct(
        private readonly LayananPesanan $layanan,
        private readonly PenyelesaianOtomatis $penjadwal,
    ) {}

    public function keterangan(): string
    {
        return 'Pembeli dan penjadwal menyelesaikan pesanan yang sama: dana dilepas satu kali';
    }

    public function proses(): int
    {
        return 8;
    }

    public function siapkan(): array
    {
        $akhiran = Str::lower(Str::random(8));
        $pembeli = Pengguna::factory()->create(['surel' => "pembeli-{$akhiran}@lapak.test"]);
        $penjual = Pengguna::factory()->create(['surel' => "penjual-{$akhiran}@lapak.test"]);
        $toko = Toko::factory()->create([
            'pemilik_id' => $penjual->id,
            'slug' => "toko-{$akhiran}",
        ]);

        $pesanan = $this->buatPesananSiap($pembeli, $toko, StatusPesanan::Dikirim);

        return [
            'pembeli' => $pembeli->id,
            'penjual' => $penjual->id,
            'pesanan' => $pesanan->id,
            'escrow_awal' => $this->saldoSistem(JenisDompet::Escrow),
            'pendapatan_awal' => $this->saldoSistem(JenisDompet::Pendapatan),
        ];
    }

    public function jalankan(array $konteks, int $nomor): string
    {
        $pesanan = Pesanan::query()->findOrFail($konteks['pesanan']);

        if ($nomor % 2 === 0) {
            return $this->penjadwal->selesaikan($pesanan) ? 'sistem: selesai' : 'sistem: lewati';
        }

        try {
            $pembeli = Pengguna::query()->findOrFail($konteks['pembeli']);
            $this->layanan->terima($pesanan, PelakuPesanan::Pembeli, $pembeli);
        } catch (TransisiPesananTidakSah) {
            return 'pembeli: 409 LP-10';
        }

        return 'pembeli: selesai';
    }

    public function periksa(array $konteks, array $hasil): array
    {
        $pesanan = Pesanan::query()->findOrFail($konteks['pesanan']);
        $penjual = Pengguna::query()->findOrFail($konteks['penjual']);

        $selesai = count(array_filter($hasil, fn (string $h) => str_ends_with($h, ': selesai')));
        $keluar = $konteks['escrow_awal'] - $this->saldoSistem(JenisDompet::Escrow);
        $komisi = $this->saldoSistem(JenisDompet::Pendapatan) - $konteks['pendapatan_awal'];
        $diterima = app(BukuBesar::class)->dompetPenjual($penjual)->saldo_rupiah;
        $mutasi = MutasiSaldo::query()->where('rujukan', $pesanan->nomor)->count();
        $jejak = LogAudit::query()->where('sasaran', "pesanan:{$pesanan->nomor}")->count();

        $pelanggaran = [];
        if ($selesai !== 1) {
            $pelanggaran[] = "{$selesai} proses merasa menyelesaikan pesanan, seharusnya tepat 1.";
        }
        if ($pesanan->status !== StatusPesanan::Selesai) {
            $pelanggaran[] = "Status pesanan {$pesanan->status->value}, seharusnya selesai.";
        }
        if ($keluar !== 382_000) {
            $pelanggaran[] = "Escrow berkurang {$keluar}, seharusnya 382000.";
        }
        if ($diterima !== 363_500 || $komisi !== 18_500) {
            $pelanggaran[] = "Penjual menerima {$diterima} dan komisi {$komisi}, "
                .'seharusnya 363500 dan 18500.';
        }
        // Dana masuk, komisi, dan lepas dana: tiga pemindahan, enam baris.
        if ($mutasi !== 6) {
            $pelanggaran[] = "Tercatat {$mutasi} baris mutasi untuk pesanan ini, seharusnya 6.";
        }
        if ($jejak !== 1) {
            $pelanggaran[] = "Tercatat {$jejak} jejak audit pelepasan dana, seharusnya 1.";
        }

        return $pelanggaran;
    }
}
