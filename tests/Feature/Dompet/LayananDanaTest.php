<?php

declare(strict_types=1);

use App\Domain\Uang;
use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Models\Dompet;
use App\Models\MutasiSaldo;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Toko;
use App\Services\BukuBesar;
use App\Services\LayananDana;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config()->set('lapak.komisi_persen', 5);

    $this->buku = app(BukuBesar::class);
    $this->dana = app(LayananDana::class);

    $this->sri = Pengguna::factory()->create();
    $toko = Toko::factory()->for($this->sri, 'pemilik')->create();

    $this->pesanan = Pesanan::factory()->create([
        'toko_id' => $toko->id,
        'subtotal_rupiah' => 370_000,
        'ongkir_rupiah' => 12_000,
        'total_rupiah' => 382_000,
    ]);

    // Simulasikan dana pembeli sudah masuk ke escrow saat pembayaran lunas (2 baris mutasi)
    DB::transaction(function () {
        $this->buku->pindahkan(
            $this->buku->dompetSistem(JenisDompet::Eksternal),
            $this->buku->dompetSistem(JenisDompet::Escrow),
            Uang::dari(382_000),
            JenisMutasi::DanaMasuk,
            $this->pesanan->nomor,
            'Pembayaran masuk ke escrow',
        );
    });

    $this->saldo = fn (string $kode): int => (int) Dompet::query()
        ->where('kode', $kode)
        ->value('saldo_rupiah');
});

it('melepas dana menjadi saldo penjual dan komisi, tanpa sisa di escrow (LP-12)', function () {
    DB::transaction(fn () => $this->dana->lepaskan($this->pesanan));

    expect(($this->saldo)('escrow'))->toBe(0)
        ->and(($this->saldo)('pendapatan'))->toBe(18_500)
        ->and(($this->saldo)("penjual:{$this->sri->id}"))->toBe(363_500)
        // Dana masuk (2 baris) dan dua pemindahan keluar (4 baris), semuanya merujuk nomor pesanan.
        ->and(MutasiSaldo::query()->where('rujukan', $this->pesanan->nomor)->count())->toBe(6)
        ->and((int) MutasiSaldo::query()->sum('jumlah_rupiah'))->toBe(0);
});

it('mengembalikan seluruh nilai pesanan kepada pembeli', function () {
    DB::transaction(fn () => $this->dana->kembalikan($this->pesanan));

    expect(($this->saldo)('escrow'))->toBe(0)
        ->and(($this->saldo)('eksternal'))->toBe(0)
        ->and(($this->saldo)('pendapatan'))->toBe(0);
});

it('menolak dana yang sama keluar dua kali, lewat jalan mana pun', function (string $kedua) {
    DB::transaction(fn () => $this->dana->lepaskan($this->pesanan));

    expect(fn () => DB::transaction(fn () => $this->dana->{$kedua}($this->pesanan)))
        ->toThrow(LogicException::class, 'sudah pernah keluar');

    expect(($this->saldo)("penjual:{$this->sri->id}"))->toBe(363_500);
})->with(['lepaskan', 'kembalikan']);
