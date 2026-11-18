<?php

declare(strict_types=1);

use App\Domain\Audit\RantaiHash;
use App\Enums\PeristiwaAudit;
use App\Models\LogAudit;
use App\Services\PemeriksaRantaiAudit;
use App\Services\PencatatAudit;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $pencatat = app(PencatatAudit::class);
    $this->pemeriksa = app(PemeriksaRantaiAudit::class);

    $this->id = [];
    foreach (range(1, 5) as $ke) {
        $log = $pencatat->catat(PeristiwaAudit::MasukBerhasil, null, "sesi:{$ke}", ['ke' => $ke]);
        $this->id[] = $log->id;
    }

    $this->jangkar = $this->pemeriksa->periksa()->jangkar();
});

it('menyatakan rantai utuh ketika tidak ada baris yang disentuh', function () {
    $hasil = $this->pemeriksa->periksa($this->jangkar);

    expect($hasil->utuh())->toBeTrue()
        ->and($hasil->jumlah)->toBe(5)
        ->and($hasil->jangkar())->toBe($this->jangkar);
});

it('menemukan baris di tengah yang isinya diubah', function () {
    DB::table('log_audit')->where('id', $this->id[2])->update(['sasaran' => 'sesi:999']);

    $hasil = $this->pemeriksa->periksa();

    expect($hasil->utuh())->toBeFalse()
        ->and($hasil->temuan[0])->toContain("Baris {$this->id[2]} tidak cocok dengan hash-nya");
});

it('menemukan baris di tengah yang dihapus', function () {
    DB::table('log_audit')->where('id', $this->id[2])->delete();

    $hasil = $this->pemeriksa->periksa();

    expect($hasil->utuh())->toBeFalse()
        ->and($hasil->temuan[0])->toContain("Baris {$this->id[3]} tidak menyambung ke baris sebelumnya");
});

it('menemukan ekor yang dipotong, walau sambungan antarbaris masih utuh', function () {
    DB::table('log_audit')->where('id', $this->id[4])->delete();

    $hasil = $this->pemeriksa->periksa();

    expect($hasil->temuan)->toHaveCount(1)
        ->and($hasil->temuan[0])->toContain('Kepala rantai mencatat 5 baris, log berisi 4');
});

it('menemukan baris yang ditulis tanpa melalui PencatatAudit', function () {
    $ekor = LogAudit::query()->orderByDesc('id')->first();

    // Penyisip yang teliti: ia menyambung ke ekor dan menghitung hash-nya dengan benar.
    $palsu = new LogAudit;
    $palsu->forceFill([
        'peristiwa' => 'peran.diberikan',
        'pelaku_id' => null,
        'sasaran' => 'pengguna:66',
        'rincian' => ['peran' => 'admin'],
        'dicatat_pada' => now()->startOfSecond(),
        'hash_sebelumnya' => $ekor->hash,
    ]);
    $palsu->hash = RantaiHash::hitung($ekor->hash, $palsu->isiTerlindung());
    $palsu->save();

    $hasil = $this->pemeriksa->periksa();

    expect($hasil->temuan)->toHaveCount(1)
        ->and($hasil->temuan[0])->toContain('Kepala rantai mencatat 5 baris, log berisi 6');
});

it('menemukan rantai yang dihitung ulang seluruhnya oleh pemegang basis data', function () {
    // Serangan terkuat: ubah satu baris, lalu hitung ulang SEMUA hash sesudahnya dan
    // perbarui kepala rantai. Dari dalam, log ini konsisten sempurna.
    DB::table('log_audit')->where('id', $this->id[1])->update(['sasaran' => 'pengguna:99']);

    $sebelumnya = DB::table('log_audit')->where('id', $this->id[0])->value('hash');
    foreach (array_slice($this->id, 1) as $id) {
        DB::table('log_audit')->where('id', $id)->update([
            'hash_sebelumnya' => "sementara-{$id}",
            'hash' => "sementara-{$id}",
        ]);
    }
    foreach (array_slice($this->id, 1) as $id) {
        $log = LogAudit::query()->findOrFail($id);
        $hash = RantaiHash::hitung($sebelumnya, $log->isiTerlindung());
        DB::table('log_audit')->where('id', $id)->update([
            'hash_sebelumnya' => $sebelumnya,
            'hash' => $hash,
        ]);
        $sebelumnya = $hash;
    }
    DB::table('rantai_audit')->update(['hash_terakhir' => $sebelumnya]);

    // Tanpa jangkar: lolos. Inilah batas rantai hash yang berdiri sendiri.
    expect($this->pemeriksa->periksa()->utuh())->toBeTrue();

    // Dengan jangkar yang disimpan di luar: ketahuan.
    $hasil = $this->pemeriksa->periksa($this->jangkar);
    expect($hasil->utuh())->toBeFalse()
        ->and($hasil->temuan[0])->toContain('Baris ke-5 tidak cocok dengan jangkar');
});
