<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AlamatFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Alamat extends Model
{
    /** @use HasFactory<AlamatFactory> */
    use HasFactory;

    protected $table = 'alamat';

    protected $fillable = ['label', 'nama_penerima', 'telepon', 'jalan', 'kota', 'kode_pos'];

    protected function casts(): array
    {
        return ['utama' => 'boolean'];
    }

    /** @return BelongsTo<Pengguna, $this> */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pengguna_id');
    }

    /**
     * @param  Builder<Alamat>  $kueri
     */
    public function scopeMilik(Builder $kueri, Pengguna $pengguna): void
    {
        $kueri->where('pengguna_id', $pengguna->id);
    }

    /**
     * @return array<string, string>
     */
    public function potret(): array
    {
        return $this->only(['nama_penerima', 'telepon', 'jalan', 'kota', 'kode_pos']);
    }
}
