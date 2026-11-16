<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusPencairan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Pencairan extends Model
{
    use HasFactory;

    protected $table = 'pencairan';

    protected $fillable = [
        'kode',
        'pengaju_id',
        'jumlah_rupiah',
        'rekening_tujuan',
        'status',
        'diputuskan_oleh',
        'diputuskan_pada',
        'catatan',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $pencairan): void {
            if (blank($pencairan->kode)) {
                $pencairan->kode = 'CAIR-'.Str::ulid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'jumlah_rupiah' => 'integer',
            'status' => StatusPencairan::class,
            'diputuskan_pada' => 'datetime',
        ];
    }

    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pengaju_id');
    }

    public function pemutus(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diputuskan_oleh');
    }
}