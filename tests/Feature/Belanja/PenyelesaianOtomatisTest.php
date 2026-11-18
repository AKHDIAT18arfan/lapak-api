<?php

declare(strict_types=1);

use App\Domain\Pesanan\PelakuPesanan;
use App\Domain\Pesanan\StatusPesanan;
use App\Models\Dompet;
use App\Models\LogAudit;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\LayananPesanan;
use App\Services\PenyelesaianOtomatis;
use Tests\Pendukung\PesananUji;

beforeEach(function () {
    $this->andi = Pengguna::factory()->pembeli()->create();
    $this->sri = Pengguna::factory()->penjual()->create();
    $toko = Toko::factory()->for($this->sri, 'pemilik')->create();
    $this->kain = Produk::factory()->for($toko)->create(['stok' => 10]);

    $this->dikirimSejak = function (int $hari): Pesanan {
        $pesanan = PesananUji::dibayar($this->andi, $this->kain);
        app(LayananPesanan::class)->kemas($pesanan);
        $pesanan = app(LayananPesanan::class)->kirim($pesanan, 'JNE-00012345');
        $pesanan->forceFill(['dikirim_pada' => now()->subDays($hari)])->save();

        return $pesanan->refresh();
    };

    $this->saldoSri = fn (): int => (int) (Dompet::query()
        ->where('kode', "penjual:{$this->sri->id}")
        ->value('saldo_rupiah') ?? 0);
});

it('menyelesaikan pesanan yang dikirim lebih dari tiga hari lalu (LP-12)', function () {
    $lama = ($this->dikirimSejak)(3);
    $baru = ($this->dikirimSejak)(2);

    $this->artisan('lapak:selesaikan-otomatis')
        ->expectsOutputToContain('1 pesanan diselesaikan')
        ->assertSuccessful();

    expect($lama->refresh()->status)->toBe(StatusPesanan::Selesai)
        ->and($baru->refresh()->status)->toBe(StatusPesanan::Dikirim)
        ->and(($this->saldoSri)())->toBe(363_500);

    $jejak = LogAudit::query()->sole();
    // Pelakunya kosong karena bukan orang. Siapa yang bertindak tetap tertulis pada rincian.
    expect($jejak->peristiwa)->toBe('dana.dilepas')
        ->and($jejak->pelaku_id)->toBeNull()
        ->and($jejak->rincian['oleh'])->toBe('sistem');
});

it('tidak melepas dana dua kali bila dijalankan berulang', function () {
    ($this->dikirimSejak)(5);

    $this->artisan('lapak:selesaikan-otomatis')->assertSuccessful();
    $this->artisan('lapak:selesaikan-otomatis')
        ->expectsOutputToContain('0 pesanan diselesaikan')
        ->assertSuccessful();

    expect(($this->saldoSri)())->toBe(363_500);
});

it('melewati pesanan yang baru saja dikonfirmasi pembelinya', function () {
    $pesanan = ($this->dikirimSejak)(5);
    $basi = Pesanan::query()->findOrFail($pesanan->id);

    // Pembeli mengonfirmasi setelah penjadwal membaca daftar kandidatnya.
    app(LayananPesanan::class)->terima($pesanan, PelakuPesanan::Pembeli, $this->andi);

    expect(app(PenyelesaianOtomatis::class)->selesaikan($basi))->toBeFalse()
        ->and(($this->saldoSri)())->toBe(363_500)
        ->and(LogAudit::query()->count())->toBe(1);
});
