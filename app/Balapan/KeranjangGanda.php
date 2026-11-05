<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Enums\NamaPeran;
use App\Models\Alamat;
use App\Models\Checkout;
use App\Models\Pengguna;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\LayananKeranjang;
use App\Services\PenerbitSesi;
use App\Services\PengelolaPeran;
use Illuminate\Support\Str;

final class KeranjangGanda implements SkenarioBalapan
{
    use PanggilHttp;

    private const STOK_AWAL = 10;

    public function keterangan(): string
    {
        return 'Satu keranjang di-checkout dari banyak tab, kunci berbeda: tepat satu checkout';
    }

    public function proses(): int
    {
        return 6;
    }

    public function siapkan(): array
    {
        $pembeli = Pengguna::factory()->create();
        app(PengelolaPeran::class)->beri($pembeli, NamaPeran::Pembeli);
        [, $kunci] = app(PenerbitSesi::class)->terbitkan($pembeli, 'Uji keranjang ganda');

        $alamat = Alamat::factory()->for($pembeli, 'pengguna')->create();
        $toko = Toko::factory()->create();
        $produk = Produk::factory()->for($toko)->create(['stok' => self::STOK_AWAL]);

        app(LayananKeranjang::class)->aturButir($pembeli, $produk->id, 2);

        return [
            'pembeli' => $pembeli->id,
            'kunci' => $kunci,
            'alamat' => $alamat->id,
            'produk' => $produk->id,
        ];
    }

    public function jalankan(array $konteks, int $nomor): string
    {
        $badan = ['alamat_id' => $konteks['alamat']];

        [$status, $json] = $this->panggil('POST', '/api/v1/checkout', $badan, [
            'Authorization' => 'Bearer '.$konteks['kunci'],
            'Idempotency-Key' => "tab-{$nomor}-".Str::random(16),
        ]);

        return $this->ringkas($status, $json);
    }

    public function periksa(array $konteks, array $hasil): array
    {
        $checkout = Checkout::query()->where('pembeli_id', $konteks['pembeli'])->count();
        $stok = Produk::query()->findOrFail($konteks['produk'])->stok;
        $pelanggaran = [];

        if ($checkout !== 1) {
            $pelanggaran[] = "Lahir {$checkout} checkout dari satu keranjang, seharusnya 1.";
        }

        if ($stok !== self::STOK_AWAL - 2) {
            $terjual = self::STOK_AWAL - $stok;
            $pelanggaran[] = "Stok berkurang {$terjual}, padahal keranjang hanya berisi 2.";
        }

        return $pelanggaran;
    }
}
