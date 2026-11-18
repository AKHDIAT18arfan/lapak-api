<?php

declare(strict_types=1);

use App\Enums\PeristiwaAudit;
use App\Models\Pengguna;
use App\Services\PencatatAudit;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->auditor = Pengguna::factory()->auditor()->create();
});

it('membuka log audit dan ujung rantai bagi auditor dengan sesi segar (LP-17)', function () {
    $pencatat = app(PencatatAudit::class);
    $pencatat->catat(PeristiwaAudit::TokoDisetujui, null, 'toko:1');
    $pencatat->catat(PeristiwaAudit::DanaDilepas, null, 'pesanan:LPK-20261101-000001');

    $this->sebagai($this->auditor);

    $this->getJson('/api/v1/audit')
        ->assertOk()
        ->assertJsonPath('data.0.peristiwa', 'dana.dilepas')
        ->assertJsonPath('data.1.peristiwa', 'toko.disetujui');

    $this->getJson('/api/v1/audit?peristiwa=toko.disetujui')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/audit/rantai')
        ->assertOk()
        ->assertJsonPath('data.jumlah', 2)
        ->assertJsonPath('data.jangkar', fn (string $j) => str_starts_with($j, '2:'));
});

it('hanya terbuka bagi auditor: staf keuangan dan admin pun ditolak', function (string $pemegang) {
    $this->sebagai(Pengguna::factory()->{$pemegang}()->create())
        ->getJson('/api/v1/audit')
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'DITOLAK');
})->with(['pembeli', 'penjual', 'admin', 'keuangan']);

it('menuntut sesi segar (LP-15)', function () {
    $this->sebagai($this->auditor, segar: false)
        ->getJson('/api/v1/audit')
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'LP-15');
});

it('tidak memiliki satu pun pintu yang menulis ke log audit', function () {
    $menulis = collect(Route::getRoutes())
        ->filter(fn ($rute) => str_starts_with($rute->uri(), 'api/v1/audit'))
        ->flatMap(fn ($rute) => array_diff($rute->methods(), ['GET', 'HEAD']));

    expect($menulis->all())->toBe([]);
});
