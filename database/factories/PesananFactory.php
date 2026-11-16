<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Pesanan\PelakuPesanan; // (PERBARUI)
use App\Domain\Pesanan\StatusPesanan;
use App\Models\Checkout;
use App\Models\Pesanan;
use App\Models\Toko;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pesanan>
 */
final class PesananFactory extends Factory
{
    protected $model = Pesanan::class;

    public function definition(): array
    {
        return [
            'checkout_id' => Checkout::factory(),
            'pembeli_id' => fn (array $isi) => Checkout::query()
                ->findOrFail($isi['checkout_id'])
                ->pembeli_id,
            'toko_id' => Toko::factory(),
            'status' => StatusPesanan::MenungguPembayaran,
            'subtotal_rupiah' => 185_000,
            'ongkir_rupiah' => 12_000,
            'total_rupiah' => 197_000,
            'alamat_kirim' => [
                'nama_penerima' => 'Andi Nugroho',
                'telepon' => '081200000008',
                'jalan' => 'Jl. Slamet Riyadi No. 12',
                'kota' => 'Surakarta',
                'kode_pos' => '57111',
            ],
        ];
    }

    public function dibayar(): static
    {
        return $this->state(['status' => StatusPesanan::Dibayar, 'dibayar_pada' => now()]);
    }

    // (PERBARUI) Tambahan state status proses dan pembatalan
    public function dikemas(): static
    {
        return $this->state([
            'status' => StatusPesanan::Dikemas,
            'dibayar_pada' => now()->subMinutes(10),
            'dikemas_pada' => now(),
        ]);
    }

    public function dikirim(string $nomorResi = 'JNE-0012345678'): static
    {
        return $this->state([
            'status' => StatusPesanan::Dikirim,
            'dibayar_pada' => now()->subMinutes(20),
            'dikemas_pada' => now()->subMinutes(10),
            'dikirim_pada' => now(),
            'nomor_resi' => $nomorResi,
        ]);
    }

    public function selesai(): static
    {
        return $this->state([
            'status' => StatusPesanan::Selesai,
            'dibayar_pada' => now()->subDays(2),
            'dikemas_pada' => now()->subDays(2)->addHour(),
            'dikirim_pada' => now()->subDay(),
            'nomor_resi' => 'JNE-0012345678',
            'selesai_pada' => now(),
        ]);
    }

    public function dibatalkan(
        PelakuPesanan $oleh = PelakuPesanan::Pembeli,
        string $alasan = 'Dibatalkan oleh pembeli',
    ): static {
        return $this->state([
            'status' => StatusPesanan::Dibatalkan,
            'dibatalkan_pada' => now(),
            'dibatalkan_oleh' => $oleh,
            'alasan_batal' => $alasan,
        ]);
    }
    // (PERBARUI) Akhir tambahan state
}