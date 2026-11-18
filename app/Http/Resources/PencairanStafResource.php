<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Uang;
use App\Models\Pencairan;
use Illuminate\Http\Request;

/** @mixin Pencairan */
final class PencairanStafResource extends SumberDayaJson
{
    public function toArray(Request $request): array
    {
        return [
            'kode' => $this->kode,
            'pengaju' => [
                'id' => $this->pengaju->id,
                'nama' => $this->pengaju->nama,
            ],
            'jumlah_rupiah' => $this->jumlah_rupiah,
            'jumlah' => $this->uang(Uang::dari($this->jumlah_rupiah)),
            'rekening_tujuan' => $this->rekening_tujuan,
            'status' => $this->status->value,
            'diputuskan_oleh' => $this->pemutus === null ? null : [
                'id' => $this->pemutus->id,
                'nama' => $this->pemutus->nama,
            ],
            'catatan' => $this->catatan,
            'dibuat_pada' => $this->created_at->toIso8601String(),
            'diputuskan_pada' => $this->diputuskan_pada?->toIso8601String(),
        ];
    }
}
