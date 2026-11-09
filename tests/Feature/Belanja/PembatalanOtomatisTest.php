<?php

use App\Domain\Pesanan\StatusPesanan;
use App\Enums\StatusPembayaran;
use App\Models\ButirPesanan;
use App\Models\Checkout;
use App\Models\Pembayaran;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\PembatalanOtomatis;
use Illuminate\Support\Facades\DB;

/*
| LP-10: checkout yang tidak dibayar sampai batas waktunya dibatalkan dan stoknya kembali.
|
| checkoutMenunggu() membuat keadaan SESUDAH checkout berhasil, tanpa lewat HTTP:
|   kain  : stok awal 5, dibeli 2  -> stok kini 3
|   intip : stok awal 10, dibeli 3 -> stok kini 7
| Setelah pembatalan, stok harus kembali ke 5 dan 10.
*/
if (! function_exists('checkoutMenunggu')) {
    /**
     * @param  array<string, mixed>  $atributTagihan  menimpa atribut pembayaran (mis. batas_bayar_pada)
     * @return array{0: Checkout, 1: Pembayaran, 2: list<Produk>}
     */
    function checkoutMenunggu(array $atributTagihan = []): array
    {
        $toko = Toko::factory()->create();
        $kain = Produk::factory()->for($toko)->create(['nama' => 'Kain Batik Cap', 'stok' => 3]);
        $intip = Produk::factory()->for($toko)->create(['nama' => 'Intip Goreng', 'stok' => 7]);

        $checkout = Checkout::factory()->create();
        $pesanan = Pesanan::factory()->for($checkout)->for($toko)->create([
            'status' => StatusPesanan::MenungguPembayaran,
        ]);
        ButirPesanan::factory()->for($pesanan)->create(['produk_id' => $kain->id, 'kuantitas' => 2]);
        ButirPesanan::factory()->for($pesanan)->create(['produk_id' => $intip->id, 'kuantitas' => 3]);

        $tagihan = Pembayaran::factory()->for($checkout)->create(
            $atributTagihan + ['status' => StatusPembayaran::Menunggu],
        );

        return [$checkout, $tagihan, [$kain, $intip]];
    }
}

it('membatalkan checkout yang lewat batas bayar dan mengembalikan stoknya (LP-10)', function () {
    [$checkout, , $produk] = checkoutMenunggu(['batas_bayar_pada' => now()->subMinute()]);

    $this->artisan('lapak:batalkan-kedaluwarsa')
        ->expectsOutputToContain('1 checkout dibatalkan')
        ->assertSuccessful();

    expect($produk[0]->refresh()->stok)->toBe(5)
        ->and($produk[1]->refresh()->stok)->toBe(10)
        ->and($checkout->pesanan()->pluck('status')->unique()->all())->toBe([StatusPesanan::Dibatalkan])
        ->and($checkout->pesanan()->first()->alasan_batal)->toBe(PembatalanOtomatis::ALASAN);
});

it('tidak menyentuh checkout yang belum lewat batas bayar', function () {
    [$checkout, , $produk] = checkoutMenunggu(['batas_bayar_pada' => now()->addHour()]);

    $this->artisan('lapak:batalkan-kedaluwarsa')
        ->expectsOutputToContain('0 checkout dibatalkan')
        ->assertSuccessful();

    expect($produk[0]->refresh()->stok)->toBe(3)
        ->and($produk[1]->refresh()->stok)->toBe(7)
        ->and($checkout->pesanan()->pluck('status')->unique()->all())
        ->toBe([StatusPesanan::MenungguPembayaran]);
});

it('aman dijalankan berulang: stok tidak dikembalikan dua kali', function () {
    [, , $produk] = checkoutMenunggu(['batas_bayar_pada' => now()->subMinute()]);

    $this->artisan('lapak:batalkan-kedaluwarsa')->assertSuccessful();
    $this->artisan('lapak:batalkan-kedaluwarsa')
        ->expectsOutputToContain('0 checkout dibatalkan')
        ->assertSuccessful();

    expect($produk[0]->refresh()->stok)->toBe(5);
});

it('memeriksa ulang tagihan di bawah gembok sebelum membatalkan', function () {
    [$checkout, $tagihan, $produk] = checkoutMenunggu(['batas_bayar_pada' => now()->subMinute()]);

    // Penjadwal sudah memilih checkout ini, tetapi pembayarannya masuk sesaat sebelum gembok.
    $tagihan->forceFill(['status' => StatusPembayaran::Lunas])->save();

    expect(app(PembatalanOtomatis::class)->batalkan($checkout->id))->toBeFalse()
        ->and($checkout->pesanan()->pluck('status')->unique()->all())
        ->toBe([StatusPesanan::MenungguPembayaran])
        ->and($produk[0]->refresh()->stok)->toBe(3);
});

// P1 (Langkah 12.4): menutup kebuntuan dengan checkout. Merah pada versi B2, hijau setelah perbaikan.
it('mengembalikan stok urut nomor produk, apa pun urutan butirnya', function () {
    $checkout = Checkout::factory()->create();
    Pembayaran::factory()->for($checkout)->create(['batas_bayar_pada' => now()->subMinute()]);
    $pesanan = Pesanan::factory()->for($checkout)->create();
    [$kecil, $tengah, $besar] = Produk::factory()->count(3)->create(['stok' => 0])->all();

    // Butir sengaja disimpan dari nomor produk terbesar.
    foreach ([$besar, $kecil, $tengah] as $barang) {
        ButirPesanan::factory()->for($pesanan)->create(['produk_id' => $barang->id]);
    }

    DB::enableQueryLog();
    app(PembatalanOtomatis::class)->batalkan($checkout->id);

    $urutan = collect(DB::getQueryLog())
        // Tanda kutip nama tabel berbeda antara SQLite dan MySQL, maka dicocokkan dengan titik.
        ->filter(fn (array $kueri) => preg_match('/^update .produk. /', $kueri['query']) === 1)
        ->map(fn (array $kueri) => last($kueri['bindings']))
        ->values()
        ->all();

    expect($urutan)->toBe([$kecil->id, $tengah->id, $besar->id]);
});
