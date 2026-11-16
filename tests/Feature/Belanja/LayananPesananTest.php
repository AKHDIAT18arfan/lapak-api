<?php

declare(strict_types=1);

use App\Domain\Pesanan\PelakuPesanan;
use App\Domain\Pesanan\StatusPesanan;
use App\Enums\StatusPembayaran;
use App\Exceptions\GalatDomain;
use App\Exceptions\TransisiPesananTidakSah;
use App\Models\Dompet;
use App\Models\LogAudit;
use App\Models\Pembayaran;
use App\Models\Pengguna;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\LayananPesanan;
use Tests\Pendukung\PesananUji;

/*
 * PENTING: blok beforeEach di bawah adalah tebakan. Samakan pembuatan $andi, $sri, $kain, dan
 * dua closure ($saldo, $peristiwa) dengan yang sudah kamu pakai di
 * tests/Feature/Dompet/LayananDanaTest.php (A2). Sisa berkas ini tidak bergantung pada
 * cara pembuatannya, hanya pada nama-namanya.
 */
beforeEach(function () {
    $this->layanan = app(LayananPesanan::class);

    $this->andi = Pengguna::factory()->create();
    $this->sri = Pengguna::factory()->create();
    $toko = Toko::factory()->create(['pemilik_id' => $this->sri->id]);

    // Dua buah sudah "diambil" saat checkout: stok awal 5, tersisa 3.
    $this->kain = Produk::factory()->create(['toko_id' => $toko->id, 'stok' => 3]);

    $this->pesanan = PesananUji::dibayar($this->andi, $this->kain);

    $this->saldo = fn (string $kode): int => (int) (Dompet::query()
        ->where('kode', $kode)
        ->value('saldo_rupiah') ?? 0);

    $this->peristiwa = fn (): array => LogAudit::query()
        ->orderBy('id')
        ->pluck('peristiwa')
        ->map(fn ($p) => $p instanceof BackedEnum ? $p->value : $p)
        ->all();
});

it('mengemas pesanan yang sudah dibayar dan mencatat waktunya', function () {
    $dikemas = $this->layanan->kemas($this->pesanan);

    expect($dikemas->status)->toBe(StatusPesanan::Dikemas)
        ->and($dikemas->dikemas_pada)->not->toBeNull()
        ->and(($this->saldo)('escrow'))->toBe(382_000);
});

it('mengirim pesanan: nomor resi dan waktu kirim tercatat', function () {
    $this->layanan->kemas($this->pesanan);

    $dikirim = $this->layanan->kirim($this->pesanan, 'JNE-00012345');

    expect($dikirim->status)->toBe(StatusPesanan::Dikirim)
        ->and($dikirim->nomor_resi)->toBe('JNE-00012345')
        ->and($dikirim->dikirim_pada)->not->toBeNull()
        ->and(($this->saldo)('escrow'))->toBe(382_000);
});

it('menyelesaikan pesanan dan melepas dananya dalam satu langkah (LP-12)', function () {
    $this->layanan->kemas($this->pesanan);
    $this->layanan->kirim($this->pesanan, 'JNE-00012345');
    $pesanan = $this->pesanan;

    $selesai = $this->layanan->terima($pesanan, PelakuPesanan::Pembeli, $this->andi);

    expect($selesai->status)->toBe(StatusPesanan::Selesai)
        ->and($selesai->selesai_pada)->not->toBeNull()
        ->and(($this->saldo)('escrow'))->toBe(0)
        ->and(($this->saldo)("penjual:{$this->sri->id}"))->toBe(363_500)
        ->and(($this->saldo)('pendapatan'))->toBe(18_500);

    expect(LogAudit::query()->where('peristiwa', 'dana.dilepas')->sole())
        ->pelaku_id->toBe($this->andi->id)
        ->sasaran->toBe("pesanan:{$pesanan->nomor}")
        ->rincian->toMatchArray([
            'oleh' => 'pembeli', 'bagian_penjual_rupiah' => 363_500, 'komisi_rupiah' => 18_500,
        ]);

    $this->artisan('lapak:periksa-buku')->assertSuccessful();
});

it('menolak konfirmasi terima sebelum pesanan dikirim, tanpa memindahkan uang', function () {
    expect(fn () => $this->layanan->terima($this->pesanan, PelakuPesanan::Pembeli, $this->andi))
        ->toThrow(TransisiPesananTidakSah::class);

    expect(($this->saldo)('escrow'))->toBe(382_000)
        ->and(LogAudit::count())->toBe(0);
});

it('membatalkan pesanan yang sudah dibayar: stok kembali, dana kembali, dua jejak', function (
    PelakuPesanan $oleh,
) {
    $pelaku = $oleh === PelakuPesanan::Pembeli ? $this->andi : $this->sri;

    $batal = $this->layanan->batalkan($this->pesanan, $oleh, $pelaku, 'Salah pilih warna');

    expect($batal)
        ->status->toBe(StatusPesanan::Dibatalkan)
        ->dibatalkan_oleh->toBe($oleh->value)
        ->alasan_batal->toBe('Salah pilih warna')
        ->and($this->kain->refresh()->stok)->toBe(5)
        ->and(($this->saldo)('escrow'))->toBe(0)
        ->and(($this->saldo)('eksternal'))->toBe(0)
        ->and(($this->peristiwa)())->toBe(['pesanan.dibatalkan', 'dana.dikembalikan']);

    $this->artisan('lapak:periksa-buku')->assertSuccessful();
})->with([PelakuPesanan::Pembeli, PelakuPesanan::Penjual]);

it('membatalkan pesanan yang sudah dikemas dengan akibat yang sama', function () {
    $this->layanan->kemas($this->pesanan);

    $batal = $this->layanan->batalkan(
        $this->pesanan, PelakuPesanan::Penjual, $this->sri, 'Barang habis di gudang',
    );

    expect($batal->status)->toBe(StatusPesanan::Dibatalkan)
        ->and($this->kain->refresh()->stok)->toBe(5)
        ->and(($this->saldo)('escrow'))->toBe(0);
});

it('menolak pembatalan setelah dikirim, tanpa mengembalikan stok atau uang', function () {
    $this->layanan->kemas($this->pesanan);
    $this->layanan->kirim($this->pesanan, 'JNE-00012345');

    expect(fn () => $this->layanan->batalkan(
        $this->pesanan, PelakuPesanan::Pembeli, $this->andi, 'Berubah pikiran',
    ))->toThrow(TransisiPesananTidakSah::class);

    expect($this->kain->refresh()->stok)->toBe(3)
        ->and(($this->saldo)('escrow'))->toBe(382_000)
        ->and(LogAudit::count())->toBe(0);
});

it('mengarahkan pembatalan pesanan yang belum dibayar ke pembatalan checkout (LP-10)', function () {
    $belum = PesananUji::belumDibayar($this->andi, $this->kain);

    expect(fn () => $this->layanan->batalkan(
        $belum, PelakuPesanan::Pembeli, $this->andi, 'Tidak jadi membeli',
    ))->toThrow(GalatDomain::class, 'dibatalkan bersama checkout-nya');

    expect($belum->refresh()->status)->toBe(StatusPesanan::MenungguPembayaran);
});

it('membatalkan checkout yang belum dibayar: pesanan batal, tagihan ditutup, stok kembali', function () {
    $belum = PesananUji::belumDibayar($this->andi, $this->kain);
    $this->kain->update(['stok' => 1]);

    $batal = $this->layanan->batalkanCheckout(
        $belum->checkout_id, PelakuPesanan::Pembeli, $this->andi, 'Tidak jadi membeli',
    );

    expect($batal)->toBeTrue()
        ->and($belum->refresh()->status)->toBe(StatusPesanan::Dibatalkan)
        ->and($belum->dibatalkan_oleh)->toBe('pembeli')
        ->and(Pembayaran::query()->where('checkout_id', $belum->checkout_id)->sole()->status)
        ->toBe(StatusPembayaran::Dibatalkan)
        ->and($this->kain->refresh()->stok)->toBe(3)
        ->and(($this->peristiwa)())->toBe(['checkout.dibatalkan']);
});

it('membatalkan checkout yang lewat batas atas nama sistem', function () {
    $belum = PesananUji::belumDibayar($this->andi, $this->kain);
    Pembayaran::query()->where('checkout_id', $belum->checkout_id)
        ->update(['batas_bayar_pada' => now()->subMinute()]);

    $batal = $this->layanan->batalkanCheckout(
        $belum->checkout_id, PelakuPesanan::Sistem, null, 'Tidak dibayar sampai batas waktu',
    );

    expect($batal)->toBeTrue()
        ->and($belum->refresh()->status)->toBe(StatusPesanan::Dibatalkan)
        ->and($belum->dibatalkan_oleh)->toBe('sistem');
});

it('tidak membatalkan checkout yang tagihannya sudah lunas, dan melaporkannya dengan false', function () {
    $batal = $this->layanan->batalkanCheckout(
        $this->pesanan->checkout_id, PelakuPesanan::Pembeli, $this->andi, 'Terlambat membatalkan',
    );

    expect($batal)->toBeFalse()
        ->and($this->pesanan->refresh()->status)->toBe(StatusPesanan::Dibayar)
        ->and($this->kain->refresh()->stok)->toBe(3)
        ->and(($this->saldo)('escrow'))->toBe(382_000)
        ->and(LogAudit::count())->toBe(0);
});
