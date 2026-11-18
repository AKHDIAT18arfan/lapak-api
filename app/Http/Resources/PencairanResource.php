<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Uang;
use App\Models\Pencairan;
use Illuminate\Http\Request;

/** @mixin Pencairan */
final class PencairanResource extends SumberDayaJson
{
    public function toArray(Request $request): array
    {
        return [
            'kode' => $this->kode,
            'jumlah_rupiah' => $this->jumlah_rupiah,
            'jumlah' => $this->uang(Uang::dari($this->jumlah_rupiah)),
            'rekening_tujuan' => $this->rekening_tujuan,
            'status' => $this->status->value,
            'catatan' => $this->catatan,
            'dibuat_pada' => $this->created_at->toIso8601String(),
            'diputuskan_pada' => $this->diputuskan_pada?->toIso8601String(),
        ];
    }
}
