<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Enums\NamaPeran;
use App\Models\Alamat;
use App\Models\Checkout;
use App\Models\Kategori;
use App\Models\Pembayaran;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\LayananKeranjang;
use App\Services\PenerbitSesi;
use App\Services\PengelolaPeran;
use Illuminate\Support\Str;

/**
 * rebutan-stok yang dijalankan lewat SELURUH lapisan: dua belas pembeli berbeda, masing-masing
 * dengan sesi, keranjang, alamat, dan Idempotency-Key-nya sendiri, berebut lima barang terakhir.
 *
 * Aturannya (LP-4, LP-7): tepat lima checkout lengkap, tujuh ditolak 409 LP-4, stok habis tanpa
 * sisa, dan jumlah checkout, pesanan, serta tagihan sama dengan jumlah jawaban 201, sehingga
 * tidak ada checkout setengah jadi milik pembeli yang ditolak.
 */
final class RebutanCheckout implements SkenarioBalapan
{
    use PanggilHttp;

    private const STOK_AWAL = 5;

    private const PEMBELI = 12;

    public function keterangan(): string
    {
        return 'Dua belas pembeli checkout serentak lewat HTTP untuk '.self::STOK_AWAL.' barang';
    }

    public function proses(): int
    {
        return self::PEMBELI;
    }

    public function siapkan(): array
    {
        $akhiran = Str::lower(Str::random(8));

        $toko = Toko::factory()->create(['slug' => "toko-{$akhiran}"]);
        $produk = Produk::factory()->for($toko)->for(Kategori::query()->firstOrFail())->create([
            'slug' => "produk-{$akhiran}",
            'stok' => self::STOK_AWAL,
            'harga_rupiah' => 50_000, // jauh di bawah batas sesi segar (LP-15)
        ]);

        $peserta = [];
        foreach (range(1, self::PEMBELI) as $ke) {
            $pembeli = new Pengguna([
                'nama' => "Pembeli Balapan {$ke}",
                'surel' => "balapan-{$akhiran}-{$ke}@lapak.test",
                'kata_sandi' => Str::random(24),
            ]);
            $pembeli->save();
            app(PengelolaPeran::class)->beri($pembeli, NamaPeran::Pembeli);

            // Kunci diterbitkan SETELAH peran diberikan: pemberian peran mencabut sesi lama.
            [, $kunciSesi] = app(PenerbitSesi::class)->terbitkan($pembeli, 'Uji balapan');

            $alamat = Alamat::factory()->for($pembeli, 'pengguna')->create();

            // Satu butir per pembeli.
            app(LayananKeranjang::class)->aturButir($pembeli, $produk->id, 1);

            $peserta[] = [
                'pembeli' => $pembeli->id,
                'alamat' => $alamat->id,
                'kunci_sesi' => $kunciSesi,
                'kunci_idempotensi' => "rebutan{$ke}".Str::random(20),
            ];
        }

        return ['produk' => $produk->id, 'peserta' => $peserta];
    }

    public function jalankan(array $konteks, int $nomor): string
    {
        $saya = $konteks['peserta'][$nomor - 1];

        [$status, $json] = $this->panggil('POST', '/api/v1/checkout', ['alamat_id' => $saya['alamat']], [
            'Authorization' => 'Bearer '.$saya['kunci_sesi'],
            'Idempotency-Key' => $saya['kunci_idempotensi'],
        ]);

        // "201", atau "409 LP-4" bila stok sudah habis.
        return $this->ringkas($status, $json);
    }

    public function periksa(array $konteks, array $hasil): array
    {
        $berhasil = count(array_filter($hasil, fn (string $h) => $h === '201'));
        $ditolak = count(array_filter($hasil, fn (string $h) => $h === '409 LP-4'));
        $lain = count($hasil) - $berhasil - $ditolak;

        $pembeli = array_column($konteks['peserta'], 'pembeli');
        $checkout = Checkout::query()->whereIn('pembeli_id', $pembeli)->get();
        $pesanan = Pesanan::query()->whereIn('checkout_id', $checkout->pluck('id'))->count();
        $tagihan = Pembayaran::query()->whereIn('checkout_id', $checkout->pluck('id'))->count();
        $sisa = Produk::query()->findOrFail($konteks['produk'])->stok;

        $pelanggaran = [];
        if ($berhasil !== self::STOK_AWAL) {
            $pelanggaran[] = "{$berhasil} checkout berhasil, seharusnya tepat ".self::STOK_AWAL.'.';
        }
        if ($sisa !== self::STOK_AWAL - $berhasil) {
            $pelanggaran[] = "Sisa stok {$sisa}, seharusnya ".(self::STOK_AWAL - $berhasil).'.';
        }
        if ($lain > 0) {
            $pelanggaran[] = "{$lain} proses menerima jawaban selain 201 dan 409 LP-4 (mis. kebuntuan).";
        }
        if ($checkout->count() !== $berhasil) {
            $pelanggaran[] = "Ada {$checkout->count()} checkout untuk {$berhasil} jawaban 201.";
        }
        if ($pesanan !== $berhasil) {
            $pelanggaran[] = "Ada {$pesanan} pesanan untuk {$berhasil} jawaban 201.";
        }
        if ($tagihan !== $berhasil) {
            $pelanggaran[] = "Ada {$tagihan} tagihan untuk {$berhasil} jawaban 201.";
        }

        return $pelanggaran;
    }
}
