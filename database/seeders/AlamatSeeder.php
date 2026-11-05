<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Alamat;
use App\Models\Pengguna;
use Illuminate\Database\Seeder;

final class AlamatSeeder extends Seeder
{
    public function run(): void
    {
        $andi = Pengguna::query()->where('surel', 'andi@lapak.test')->first();
        if ($andi !== null) {
            Alamat::factory()->for($andi, 'pengguna')->create([
                'label' => 'Rumah',
                'nama_penerima' => 'Andi Nugroho',
                'telepon' => '081200000008',
                'jalan' => 'Jl. Slamet Riyadi No. 12',
                'kota' => 'Surakarta',
                'kode_pos' => '57111',
                'utama' => true,
            ]);
            Alamat::factory()->cadangan()->for($andi, 'pengguna')->create([
                'label' => 'Kantor',
                'nama_penerima' => 'Andi Nugroho (Kantor)',
                'telepon' => '081200000008',
                'jalan' => 'Jl. Jend. Sudirman No. 45',
                'kota' => 'Surakarta',
                'kode_pos' => '57112',
                'utama' => false,
            ]);
        }

        $dewi = Pengguna::query()->where('surel', 'dewi@lapak.test')->first();
        if ($dewi !== null) {
            Alamat::factory()->for($dewi, 'pengguna')->create([
                'label' => 'Toko Batik Dewi',
                'nama_penerima' => 'Dewi Lestari',
                'telepon' => '081200000001',
                'jalan' => 'Jl. Dr. Rajiman No. 88',
                'kota' => 'Surakarta',
                'kode_pos' => '57114',
                'utama' => true,
            ]);
        }

        $sri = Pengguna::query()->where('surel', 'sri@lapak.test')->first();
        if ($sri !== null) {
            Alamat::factory()->for($sri, 'pengguna')->create([
                'label' => 'Rumah Ibu Sri',
                'nama_penerima' => 'Sri Wahyuni',
                'telepon' => '081200000002',
                'jalan' => 'Jl. Veteran No. 10',
                'kota' => 'Surakarta',
                'kode_pos' => '57115',
                'utama' => true,
            ]);
        }
    }
}
