<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Pesanan\AturanTransisi;
use App\Domain\Pesanan\PelakuPesanan;
use App\Domain\Pesanan\StatusPesanan;
use App\Domain\Uang;
use App\Models\ButirPesanan;
use App\Models\Pesanan;
use App\Services\LayananDana;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Pesanan */
final class PesananTokoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dana = app(LayananDana::class);

        return [
            'nomor' => $this->nomor,
            'status' => $this->status->value,
            'butir' => $this->butir->map(fn (ButirPesanan $butir) => [
                'produk_id' => $butir->produk_id,
                'nama' => $butir->nama_produk,
                'harga_satuan' => $butir->harga_rupiah,
                'kuantitas' => $butir->kuantitas,
                'subtotal' => $butir->subtotal_rupiah,
            ])->all(),
            'subtotal' => $this->subtotal_rupiah,
            'ongkir' => $this->ongkir_rupiah,
            'total' => $this->uang(Uang::dari($this->total_rupiah)),
            'komisi' => $this->uang($dana->komisi($this->resource)),
            'bagian_penjual' => $this->uang($dana->bagianPenjual($this->resource)),
            // Nama, telepon, dan alamat orang adalah data pribadi. Penjual baru memerlukannya
            // ketika ada barang yang harus dikirim, yaitu setelah pesanan dibayar.
            'alamat_kirim' => $this->dibayar_pada === null ? null : $this->alamat_kirim,
            // Dibaca dari aturan yang sama dengan yang dipakai MesinStatusPesanan, bukan disalin.
            'tindakan' => array_map(
                fn (StatusPesanan $ke) => $ke->value,
                AturanTransisi::tujuan($this->status, PelakuPesanan::Penjual),
            ),
            'dibuat_pada' => $this->created_at->toIso8601String(),
            'dibayar_pada' => $this->dibayar_pada?->toIso8601String(),
            'dikemas_pada' => $this->dikemas_pada?->toIso8601String(),
            'dikirim_pada' => $this->dikirim_pada?->toIso8601String(),
            'nomor_resi' => $this->nomor_resi,
            'selesai_pada' => $this->selesai_pada?->toIso8601String(),
            'dibatalkan_pada' => $this->dibatalkan_pada?->toIso8601String(),
            'dibatalkan_oleh' => $this->dibatalkan_oleh,
            'alasan_batal' => $this->alasan_batal,
        ];
    }

    /** @return array{rupiah: int, tampilan: string} */
    private function uang(Uang $uang): array
    {
        return ['rupiah' => $uang->rupiah, 'tampilan' => $uang->format()];
    }
}
