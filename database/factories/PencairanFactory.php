<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatusPencairan;
use App\Models\Pencairan;
use App\Models\Pengguna;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Pencairan>
 */
final class PencairanFactory extends Factory
{
    protected $model = Pencairan::class;

    public function definition(): array
    {
        return [
            'kode' => 'CAIR-'.Str::ulid(),
            'pengaju_id' => Pengguna::factory(),
            'jumlah_rupiah' => fake()->numberBetween(50_000, 1_000_000),
            'rekening_tujuan' => 'BRI '.fake()->numerify('################'),
            'status' => StatusPencairan::Menunggu,
            'diputuskan_oleh' => null,
            'diputuskan_pada' => null,
            'catatan' => null,
        ];
    }
}