<?php

declare(strict_types=1);

use App\Models\KunciIdempotensi;
use App\Models\Pengguna;

it('memangkas catatan idempotensi yang melewati masa simpan dan menyisakan yang baru', function () {
    $pengguna = Pengguna::factory()->create();

    $buatCatatan = function (string $kunci) use ($pengguna): KunciIdempotensi {
        $catatan = new KunciIdempotensi;
        $catatan->forceFill([
            'pengguna_id' => $pengguna->id,
            'kunci' => $kunci,
            'rute' => 'POST api/v1/checkout',
            'sidik' => hash('sha256', $kunci),
            'kode_status' => 201,
            'badan_respons' => '{}',
        ])->save();

        return $catatan;
    };

    $buatCatatan('kunci-lama-1234567890');

    $this->travel(config('lapak.idempotensi.simpan_hari') + 1)->days();

    $baru = $buatCatatan('kunci-baru-1234567890');

    $this->artisan('lapak:pangkas-idempotensi')
        ->expectsOutput('1 catatan idempotensi dipangkas.')
        ->assertSuccessful();

    expect(KunciIdempotensi::pluck('kunci')->all())
        ->toBe([$baru->kunci]);
});
