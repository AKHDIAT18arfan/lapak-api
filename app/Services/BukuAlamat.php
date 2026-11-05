<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GalatDomain;
use App\Models\Alamat;
use App\Models\Pengguna;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pintu perubahan alamat. Menjaga dua aturan yang tidak dapat dijaga per baris:
 * pengguna yang memiliki alamat selalu memiliki TEPAT SATU alamat utama, dan
 * jumlah alamat tidak melebihi batas.
 */
final class BukuAlamat
{
    /**
     * @param  array<string, string>  $data  hasil validasi AlamatRequest
     */
    public function tambah(Pengguna $pemilik, array $data): Alamat
    {
        return $this->terkunci($pemilik, function (Collection $punya) use ($pemilik, $data) {
            if ($punya->count() >= config('lapak.transaksi.alamat_maksimum')) {
                throw new GalatDomain('ALAMAT_PENUH', 'Jumlah alamat sudah mencapai batas.', 422);
            }

            $alamat = new Alamat($data);
            $alamat->pengguna_id = $pemilik->id;
            $alamat->utama = $punya->isEmpty();
            $alamat->save();

            return $alamat;
        });
    }

    public function jadikanUtama(Pengguna $pemilik, int $idAlamat): Alamat
    {
        return $this->terkunci($pemilik, function (Collection $punya) use ($idAlamat): Alamat {
            $terpilih = $punya->firstWhere('id', $idAlamat) ?? abort(404);

            foreach ($punya as $alamat) {
                $alamat->forceFill(['utama' => $alamat->is($terpilih)])->save();
            }

            return $terpilih;
        });
    }

    public function hapus(Pengguna $pemilik, int $idAlamat): void
    {
        $this->terkunci($pemilik, function (Collection $punya) use ($idAlamat): void {
            $dihapus = $punya->firstWhere('id', $idAlamat) ?? abort(404);
            $dihapus->delete();

            $pengganti = $punya->first(fn (Alamat $alamat) => ! $alamat->is($dihapus));

            if ($dihapus->utama && $pengganti !== null) {
                $pengganti->forceFill(['utama' => true])->save();
            }
        });
    }

    /**
     * @template T
     *
     * @param  Closure(Collection<int, Alamat>): T  $kerja  menerima alamat pemilik, urut nomor
     * @return T
     */
    private function terkunci(Pengguna $pemilik, Closure $kerja): mixed
    {
        return DB::transaction(function () use ($pemilik, $kerja) {
            Pengguna::query()->whereKey($pemilik->id)->lockForUpdate()->firstOrFail();

            $punya = Alamat::query()->milik($pemilik)->orderBy('id')->lockForUpdate()->get();

            return $kerja($punya);
        });
    }
}
