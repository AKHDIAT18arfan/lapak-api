<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Enums\NamaPeran;
use App\Models\Alamat;
use App\Models\Checkout;
use App\Models\Kategori;
use App\Models\Pengguna;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\LayananKeranjang;
use App\Services\PenerbitSesi;
use App\Services\PengelolaPeran;
use Illuminate\Support\Str;

/**
 * Satu checkout dikirim berulang kali SECARA BERSAMAAN dengan Idempotency-Key yang sama
 * (klien yang menekan "Bayar" berkali-kali, atau jaringan yang mengulang permintaan).
 * Aturannya (LP-11): efeknya satu kali, dan semua pengirim menerima jawaban yang sama.
 */
final class KunciGanda implements SkenarioBalapan
{
    use PanggilHttp;

    private const STOK_AWAL = 50;

    public function keterangan(): string
    {
        return 'Checkout yang sama diulang serentak dengan satu Idempotency-Key: efeknya satu kali';
    }

    public function proses(): int
    {
        return 6;
    }

    public function siapkan(): array
    {
        $akhiran = Str::lower(Str::random(8));

        $pembeli = new Pengguna([
            'nama' => 'Pembeli Balapan',
            'surel' => "balapan-{$akhiran}@lapak.test",
            'kata_sandi' => Str::random(24),
        ]);
        $pembeli->save();
        app(PengelolaPeran::class)->beri($pembeli, NamaPeran::Pembeli);

        // Kunci diterbitkan SETELAH peran diberikan: pemberian peran mencabut sesi lama.
        [, $kunciSesi] = app(PenerbitSesi::class)->terbitkan($pembeli, 'Uji balapan');

        $alamat = Alamat::factory()->for($pembeli, 'pengguna')->create();

        $toko = Toko::factory()->create(['slug' => "toko-{$akhiran}"]);
        $produk = Produk::factory()->for($toko)->for(Kategori::query()->firstOrFail())->create([
            'slug' => "produk-{$akhiran}",
            'stok' => self::STOK_AWAL,
            // Jauh di bawah batas sesi segar (LP-15), supaya yang diuji hanya idempotensi.
            'harga_rupiah' => 50_000,
        ]);

        // Dua butir: stok akhir yang benar adalah STOK_AWAL - 2 = 48.
        app(LayananKeranjang::class)->aturButir($pembeli, $produk->id, 2);

        return [
            'pembeli' => $pembeli->id,
            'produk' => $produk->id,
            'alamat' => $alamat->id,
            'kunci_sesi' => $kunciSesi,
            // 16 sampai 64 karakter; SATU kunci untuk semua proses.
            'kunci_idempotensi' => 'balapan'.Str::random(24),
        ];
    }

    public function jalankan(array $konteks, int $nomor): string
    {
        $badan = ['alamat_id' => $konteks['alamat']];

        [$status, $json, $header] = $this->panggil('POST', '/api/v1/checkout', $badan, [
            'Authorization' => 'Bearer '.$konteks['kunci_sesi'],
            'Idempotency-Key' => $konteks['kunci_idempotensi'],
        ]);

        if ($status !== 201) {
            return $this->ringkas($status, $json);
        }

        $jenis = isset($header['idempotency-replayed']) ? 'ulangan' : 'pertama';

        // Kode checkout ikut dicatat: semua proses harus menerima checkout YANG SAMA.
        return "201 {$jenis} ".substr($json['data']['kode'], -6);
    }

    public function periksa(array $konteks, array $hasil): array
    {
        $checkout = Checkout::query()->where('pembeli_id', $konteks['pembeli'])->count();
        $stok = Produk::query()->findOrFail($konteks['produk'])->stok;
        $pertama = count(array_filter(
            $hasil,
            fn (string $h) => str_starts_with($h, '201 pertama'),
        ));
        $kode = array_unique(array_map(fn (string $h) => substr($h, -6), $hasil));

        $pelanggaran = [];
        if ($checkout !== 1) {
            $pelanggaran[] = "Lahir {$checkout} checkout dari satu Idempotency-Key, seharusnya 1.";
        }
        if ($pertama !== 1) {
            $pelanggaran[] = "{$pertama} proses merasa menjadi yang pertama, seharusnya tepat 1.";
        }
        if (count($kode) !== 1) {
            $pelanggaran[] = 'Proses yang berbeda menerima jawaban yang berbeda.';
        }
        if ($stok !== self::STOK_AWAL - 2) {
            $pelanggaran[] = 'Stok berkurang '.(self::STOK_AWAL - $stok).', padahal keranjang hanya berisi 2.';
        }

        return $pelanggaran;
    }
}
