<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PeranIzinSeeder::class,
            PenggunaSeeder::class,
            AlamatSeeder::class,
            KatalogSeeder::class,
            DompetSeeder::class,
            ContohMutasiSeeder::class,
        ]);
    }
}
