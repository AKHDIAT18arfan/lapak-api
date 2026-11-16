<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Pesanan\AturanTransisi;
use App\Domain\Pesanan\PelakuPesanan;
use App\Domain\Pesanan\StatusPesanan;
use App\Exceptions\TransisiPesananTidakSah;
use App\Models\Pesanan;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Satu-satunya pintu perubahan status pesanan (LP-10).
 */
final class MesinStatusPesanan
{
    /**
     * @throws TransisiPesananTidakSah 409 LP-10 bila perpindahan itu tidak ada jalurnya
     */
    public function pindahkan(
        Pesanan $pesanan,
        StatusPesanan $ke,
        PelakuPesanan $oleh,
        ?string $alasanBatal = null,
        ?string $nomorResi = null, // (PERBARUI)
    ): Pesanan {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Status pesanan hanya boleh diubah di dalam GembokCheckout.');
        }

        $terkini = Pesanan::query()->lockForUpdate()->findOrFail($pesanan->id);

        if (! AturanTransisi::boleh($terkini->status, $ke, $oleh)) {
            throw new TransisiPesananTidakSah($terkini->status, $ke);
        }

        // (PERBARUI) Tambah cap waktu dikemas, dikirim (+ nomor_resi), selesai, dan dibatalkan_oleh
        $cap = match ($ke) {
            StatusPesanan::Dibayar => ['dibayar_pada' => now()],
            StatusPesanan::Dikemas => ['dikemas_pada' => now()],
            StatusPesanan::Dikirim => [
                'dikirim_pada' => now(),
                'nomor_resi' => $nomorResi,
            ],
            StatusPesanan::Selesai => ['selesai_pada' => now()],
            StatusPesanan::Dibatalkan => [
                'dibatalkan_pada' => now(),
                'dibatalkan_oleh' => $oleh,
                'alasan_batal' => $alasanBatal,
            ],
            default => [],
        };
        // (PERBARUI) Akhir pembaruan cap waktu

        $terkini->forceFill(['status' => $ke, ...$cap])->save();

        return $terkini;
    }
}