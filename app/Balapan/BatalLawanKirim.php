<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Domain\Pesanan\PelakuPesanan;
use App\Domain\Pesanan\StatusPesanan;
use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Exceptions\TransisiPesananTidakSah;
use App\Models\MutasiSaldo;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\LayananPesanan;
use Illuminate\Support\Str;

final class BatalLawanKirim implements SkenarioBalapan
{
    use SiapkanPesanan;

    private const STOK_AWAL = 10;

    public function __construct(private readonly LayananPesanan $layanan) {}

    public function keterangan(): string
    {
        return 'Pembeli membatalkan tepat saat penjual mengirim: tepat satu yang menang';
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

        // Empat pesanan dalam status dikemas: tiap pasang proses memperebutkan satu pesanan.
        $daftarPesanan = [];
        foreach (range(1, 4) as $ke) {
            $pesanan = $this->buatPesananSiap($pembeli, $toko, StatusPesanan::Dikemas, self::STOK_AWAL);
            $pesanan->forceFill(['dikemas_pada' => now()])->save();
            $daftarPesanan[] = $pesanan->id;
        }

        return [
            'pembeli' => $pembeli->id,
            'pesanan' => $daftarPesanan,
            'escrow_awal' => $this->saldoSistem(JenisDompet::Escrow),
        ];
    }

    public function jalankan(array $konteks, int $nomor): string
    {
        $idPesanan = $konteks['pesanan'][intdiv($nomor, 2)];
        $pesanan = Pesanan::query()->findOrFail($idPesanan);

        if ($nomor % 2 === 0) {
            try {
                $pembeli = Pengguna::query()->findOrFail($konteks['pembeli']);
                $this->layanan->batalkan($pesanan, PelakuPesanan::Pembeli, $pembeli, 'Berubah pikiran');
            } catch (TransisiPesananTidakSah) {
                return 'pembeli: 409 LP-10';
            }

            return 'pembeli: batal';
        }

        try {
            $this->layanan->kirim($pesanan, 'JNE-'.Str::padLeft((string) $pesanan->id, 8, '0'));
        } catch (TransisiPesananTidakSah) {
            return 'penjual: 409 LP-10';
        }

        return 'penjual: kirim';
    }

    public function periksa(array $konteks, array $hasil): array
    {
        $pelanggaran = [];
        $kembali = 0;

        foreach (Pesanan::query()->with('butir')->findMany($konteks['pesanan']) as $pesanan) {
            $stok = Produk::query()->findOrFail($pesanan->butir[0]->produk_id)->stok;
            $refund = MutasiSaldo::query()
                ->where('rujukan', $pesanan->nomor)
                ->where('jenis', JenisMutasi::Pengembalian)
                ->count();

            $sah = match ($pesanan->status) {
                // Batal: stok kembali, uang kembali (sepasang mutasi), dan tidak ada resi.
                StatusPesanan::Dibatalkan => $stok === self::STOK_AWAL + 2
                    && $refund === 2 && $pesanan->nomor_resi === null,
                // Dikirim: stok tetap, uang tetap ditahan, dan resinya tercatat.
                StatusPesanan::Dikirim => $stok === self::STOK_AWAL
                    && $refund === 0 && $pesanan->nomor_resi !== null,
                default => false,
            };

            if (! $sah) {
                $pelanggaran[] = "Pesanan {$pesanan->nomor} berakhir {$pesanan->status->value} "
                    ."dengan stok {$stok}, {$refund} baris pengembalian, "
                    .'resi '.($pesanan->nomor_resi ?? 'kosong').'.';
            }

            $kembali += $pesanan->status === StatusPesanan::Dibatalkan ? 382_000 : 0;
        }

        $menang = count(array_filter($hasil, fn (string $h) => ! str_contains($h, '409')));
        if ($menang !== count($konteks['pesanan'])) {
            $pelanggaran[] = "{$menang} tindakan berhasil untuk "
                .count($konteks['pesanan']).' pesanan, seharusnya satu per pesanan.';
        }

        $keluar = $konteks['escrow_awal'] - $this->saldoSistem(JenisDompet::Escrow);
        if ($keluar !== $kembali) {
            $pelanggaran[] = "Escrow berkurang {$keluar}, padahal yang dikembalikan {$kembali}.";
        }

        return $pelanggaran;
    }
}
