<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Alamat;
use App\Models\Pengguna;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alamat>
 */
final class AlamatFactory extends Factory
{
    protected $model = Alamat::class;

    public function definition(): array
    {
        return [
            'pengguna_id' => Pengguna::factory(),
            'label' => 'Rumah',
            'nama_penerima' => $this->faker->name(),
            'telepon' => '081234567890',
            'jalan' => $this->faker->streetAddress(),
            'kota' => 'Surakarta',
            'kode_pos' => '57111',
            'utama' => true,
        ];
    }

    public function cadangan(): self
    {
        return $this->state(fn () => [
            'label' => 'Kantor',
            'utama' => false,
        ]);
    }
}
