<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ButirPesanan;
use App\Models\Pesanan;
use App\Models\Produk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ButirPesanan>
 */
final class ButirPesananFactory extends Factory
{
    protected $model = ButirPesanan::class;

    public function definition(): array
    {
        return [
            'pesanan_id' => Pesanan::factory(),
            'produk_id' => Produk::factory(),
            'nama_produk' => 'Kain Batik Cap',
            'harga_rupiah' => 92_500,
            'kuantitas' => 2,
            'subtotal_rupiah' => 185_000,
        ];
    }
}
