<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Domain\Pesanan\StatusPesanan;
use App\Domain\Uang;
use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Enums\StatusPembayaran;
use App\Models\ButirPesanan;
use App\Models\Checkout;
use App\Models\Dompet;
use App\Models\Kategori;
use App\Models\Pembayaran;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\BukuBesar;
use Illuminate\Support\Str;

trait SiapkanPesanan
{
    protected function buatPesananSiap(
        Pengguna $pembeli,
        Toko $toko,
        StatusPesanan $status,
        int $stokAwal = 10,
    ): Pesanan {
        $akhiran = Str::lower(Str::random(8));
        $produk = Produk::factory()
            ->for($toko)
            ->for(Kategori::query()->firstOrFail())
            ->create([
                'slug' => "produk-{$akhiran}",
                'harga_rupiah' => 185_000,
                'stok' => $stokAwal,
            ]);

        $subtotal = 370_000;
        $ongkir = 12_000;
        $total = 382_000;

        $checkout = Checkout::factory()->create([
            'pembeli_id' => $pembeli->id,
            'total_rupiah' => $total,
        ]);

        Pembayaran::query()->forceCreate([
            'checkout_id' => $checkout->id,
            'kode' => 'BYR-'.Str::upper(Str::random(10)),
            'status' => StatusPembayaran::Lunas,
            'jumlah_rupiah' => $total,
            'batas_bayar_pada' => now()->addDay(),
        ]);

        $pesanan = Pesanan::query()->forceCreate([
            'checkout_id' => $checkout->id,
            'pembeli_id' => $pembeli->id,
            'toko_id' => $toko->id,
            'nomor' => 'LPK-'.now()->format('Ymd').'-'.Str::padLeft((string) random_int(1, 999_999), 6, '0'),
            'status' => $status,
            'subtotal_rupiah' => $subtotal,
            'ongkir_rupiah' => $ongkir,
            'total_rupiah' => $total,
            'dibayar_pada' => now(),
            'dikemas_pada' => $status === StatusPesanan::Dikirim ? now() : null,
            'dikirim_pada' => $status === StatusPesanan::Dikirim ? now() : null,
            'nomor_resi' => $status === StatusPesanan::Dikirim ? 'JNE-00012345' : null,
            'alamat_kirim' => [
                'nama_penerima' => 'Andi Nugroho',
                'telepon' => '081200000008',
                'jalan' => 'Jl. Slamet Riyadi No. 12',
                'kota' => 'Surakarta',
                'kode_pos' => '57111',
            ],
        ]);

        ButirPesanan::query()->forceCreate([
            'pesanan_id' => $pesanan->id,
            'produk_id' => $produk->id,
            'nama_produk' => $produk->nama,
            'harga_rupiah' => 185_000,
            'kuantitas' => 2,
            'subtotal_rupiah' => $subtotal,
        ]);

        $buku = app(BukuBesar::class);
        $buku->pindahkan(
            $buku->dompetSistem(JenisDompet::Eksternal),
            $buku->dompetSistem(JenisDompet::Escrow),
            Uang::dari($total),
            JenisMutasi::DanaMasuk,
            $pesanan->nomor,
        );

        return $pesanan->refresh();
    }

    protected function saldoSistem(JenisDompet $jenis): int
    {
        return (int) (Dompet::query()
            ->where('kode', $jenis->value)
            ->value('saldo_rupiah') ?? 0);
    }
}
