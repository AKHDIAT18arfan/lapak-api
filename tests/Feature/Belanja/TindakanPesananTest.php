<?php

declare(strict_types=1);

use App\Domain\Pesanan\StatusPesanan;
use App\Enums\NamaPeran;
use App\Models\Checkout;
use App\Models\Dompet;
use App\Models\LogAudit;
use App\Models\Pengguna;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\LayananPesanan;
use Illuminate\Support\Str;
use Tests\Pendukung\PesananUji;

beforeEach(function () {
    $this->andi = Pengguna::factory()->pembeli()->create();
    $this->sri = Pengguna::factory()->denganPeran(NamaPeran::Pembeli, NamaPeran::Penjual)->create();
    $toko = Toko::factory()->for($this->sri, 'pemilik')->create();
    $this->kain = Produk::factory()->for($toko)->create(['stok' => 3]);

    $this->pesanan = PesananUji::dibayar($this->andi, $this->kain);
    app(LayananPesanan::class)->kemas($this->pesanan);
    $this->pesanan = app(LayananPesanan::class)->kirim($this->pesanan, 'JNE-00012345');

    $this->alamat = "/api/v1/pesanan/{$this->pesanan->nomor}/terima";
    $this->kirim = fn (string $alamat, array $badan = [], ?string $kunci = null) => $this
        ->withHeader('Idempotency-Key', $kunci ?? (string) Str::uuid())
        ->postJson($alamat, $badan);
    $this->saldo = fn (string $kode): int => (int) (Dompet::query()
        ->where('kode', $kode)
        ->value('saldo_rupiah') ?? 0);
    $this->saldoSri = fn (): int => ($this->saldo)("penjual:{$this->sri->id}");
});

it('menyelesaikan pesanan dan melepas dana kepada penjual (LP-12)', function () {
    $this->sebagai($this->andi);

    ($this->kirim)($this->alamat)
        ->assertOk()
        ->assertJsonPath('data.status', 'selesai')
        ->assertJsonPath('data.nomor', $this->pesanan->nomor);

    expect(($this->saldo)('escrow'))->toBe(0)
        ->and(($this->saldo)('pendapatan'))->toBe(18_500)
        ->and(($this->saldoSri)())->toBe(363_500)
        ->and($this->pesanan->refresh()->selesai_pada)->not->toBeNull();

    $jejak = LogAudit::query()->sole();
    expect($jejak->peristiwa)->toBe('dana.dilepas')
        ->and($jejak->pelaku_id)->toBe($this->andi->id)
        ->and($jejak->rincian['oleh'])->toBe('pembeli')
        ->and($jejak->rincian['bagian_penjual_rupiah'])->toBe(363_500);
});

it('melepas dana satu kali walau tombolnya ditekan berulang (LP-11)', function () {
    $this->sebagai($this->andi);

    ($this->kirim)($this->alamat, [], 'terima-kain-000001')->assertOk();
    ($this->kirim)($this->alamat, [], 'terima-kain-000001')
        ->assertOk()
        ->assertHeader('Idempotency-Replayed', 'true');

    // Kunci berbeda: bukan putaran ulang. Yang menolak sekarang aturan status.
    ($this->kirim)($this->alamat, [], 'terima-kain-000002')
        ->assertConflict()
        ->assertJsonPath('galat.kode', 'LP-10');

    expect(($this->saldoSri)())->toBe(363_500)->and(LogAudit::query()->count())->toBe(1);
});

it('menjawab 404 untuk pesanan orang lain, dan menolak penjualnya sendiri', function () {
    $dewi = Pengguna::factory()->pembeli()->create();
    $this->sebagai($dewi);
    ($this->kirim)($this->alamat)->assertNotFound();

    // Bu Sri memegang peran pembeli dan penjual. Ia bukan pembeli pesanan ini: 404.
    // Penjual tidak pernah dapat melepas dana untuk dirinya sendiri.
    $this->sebagai($this->sri);
    ($this->kirim)($this->alamat)->assertNotFound();

    expect($this->pesanan->refresh()->status)->toBe(StatusPesanan::Dikirim)
        ->and(($this->saldo)('escrow'))->toBe(382_000);
});

it('membatalkan satu pesanan yang sudah dibayar, mengembalikan stok dan dananya', function () {
    $dibayar = PesananUji::dibayar($this->andi, $this->kain);
    $this->sebagai($this->andi);

    ($this->kirim)("/api/v1/pesanan/{$dibayar->nomor}/batal", ['alasan' => 'Salah pilih motif'])
        ->assertOk()
        ->assertJsonPath('data.status', 'dibatalkan')
        ->assertJsonPath('data.dibatalkan_oleh', 'pembeli')
        ->assertJsonPath('data.alasan_batal', 'Salah pilih motif');

    expect($this->kain->refresh()->stok)->toBe(5)
        ->and(($this->saldo)('escrow'))->toBe(382_000);
});

it('menolak pembatalan pesanan yang sudah dikirim (LP-10)', function () {
    $this->sebagai($this->andi);

    ($this->kirim)("/api/v1/pesanan/{$this->pesanan->nomor}/batal", ['alasan' => 'Berubah pikiran'])
        ->assertConflict()
        ->assertJsonPath('galat.kode', 'LP-10');
});

it('membatalkan checkout yang belum dibayar beserta seluruh pesanannya', function () {
    $belum = PesananUji::belumDibayar($this->andi, $this->kain);
    $checkout = Checkout::query()->findOrFail($belum->checkout_id);
    $this->sebagai($this->andi);

    ($this->kirim)("/api/v1/checkout/{$checkout->kode}/batal", ['alasan' => 'Tidak jadi beli'])
        ->assertOk();

    expect($belum->refresh()->status)->toBe(StatusPesanan::Dibatalkan)
        ->and($this->kain->refresh()->stok)->toBe(5);
});

it('menolak pembatalan checkout yang sudah lunas dengan 409 (LP-10)', function () {
    $checkout = Checkout::query()->findOrFail($this->pesanan->checkout_id);
    $this->sebagai($this->andi);

    ($this->kirim)("/api/v1/checkout/{$checkout->kode}/batal", ['alasan' => 'Terlambat'])
        ->assertConflict()
        ->assertJsonPath('galat.kode', 'LP-10');
});

it('menolak permintaan tanpa Idempotency-Key (LP-11)', function () {
    $this->sebagai($this->andi);

    $this->postJson($this->alamat)->assertBadRequest();
});
