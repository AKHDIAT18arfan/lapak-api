<?php

declare(strict_types=1);

use App\Domain\Uang;
use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Enums\StatusPencairan;
use App\Models\Dompet;
use App\Models\LogAudit;
use App\Models\Pencairan;
use App\Models\Pengguna;
use App\Services\BukuBesar;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->sri = Pengguna::factory()->penjual()->create();
    $this->keuangan = Pengguna::factory()->keuangan()->create();

    $buku = app(BukuBesar::class);
    $buku->pindahkan(
        $buku->dompetSistem(JenisDompet::Eksternal),
        $buku->dompetPenjual($this->sri),
        Uang::dari(363_500),
        JenisMutasi::LepasDana,
        'LPK-UJI-0001',
    );

    $this->saldo = fn (string $kode): int => (int) (Dompet::query()
        ->where('kode', $kode)
        ->value('saldo_rupiah') ?? 0);
    $this->saldoSri = fn (): int => ($this->saldo)("penjual:{$this->sri->id}");

    $this->ajukan = fn (int $jumlah, string $rekening = 'BCA 1234567890 a.n. Sri') => $this
        ->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/dompet/pencairan', [
            'jumlah_rupiah' => $jumlah,
            'rekening_tujuan' => $rekening,
        ]);

    $this->kirim = fn (string $alamat, array $badan = []) => $this
        ->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/{$alamat}", $badan);
});

it('mengajukan pencairan dan langsung menahan dana di dompet pencairan (LP-16)', function () {
    $this->sebagai($this->sri);

    ($this->ajukan)(200_000, 'BRI 00987654321 a.n. Sri Wahyuni')
        ->assertCreated()
        ->assertJsonPath('data.status', 'menunggu')
        ->assertJsonPath('data.jumlah_rupiah', 200_000);

    $pencairan = Pencairan::query()->sole();
    expect($pencairan->kode)->toStartWith('CAIR-')
        ->and(($this->saldoSri)())->toBe(163_500)
        ->and(($this->saldo)('pencairan'))->toBe(200_000);

    $jejak = LogAudit::query()->sole();
    expect($jejak->peristiwa)->toBe('pencairan.diajukan')
        ->and($jejak->pelaku_id)->toBe($this->sri->id)
        ->and($jejak->rincian)->not->toHaveKey('rekening_tujuan');

    $this->artisan('lapak:periksa-buku')->assertSuccessful();
});

it('menolak jumlah di bawah batas minimal pencairan (LP-16)', function () {
    $this->sebagai($this->sri);

    ($this->ajukan)(49_999)
        ->assertUnprocessable()
        ->assertJsonPath('galat.kode', 'LP-16');

    expect(Pencairan::query()->count())->toBe(0);
});

it('menolak jumlah yang melebihi saldo tanpa meninggalkan pengajuan', function () {
    $this->sebagai($this->sri);

    ($this->ajukan)(363_501)->assertConflict()->assertJsonPath('galat.kode', 'LP-13');

    expect(Pencairan::query()->count())->toBe(0)
        ->and(($this->saldoSri)())->toBe(363_500)
        ->and(LogAudit::query()->count())->toBe(0);
});

it('tidak dapat mengajukan uang yang sama dua kali', function () {
    $this->sebagai($this->sri);

    ($this->ajukan)(200_000)->assertCreated();

    // Saldo yang tersisa 163.500: pengajuan kedua sebesar 200.000 tidak punya sumbernya.
    ($this->ajukan)(200_000)->assertConflict()->assertJsonPath('galat.kode', 'LP-13');

    ($this->ajukan)(163_500)->assertCreated();

    expect(($this->saldoSri)())->toBe(0)->and(($this->saldo)('pencairan'))->toBe(363_500);
    $this->artisan('lapak:periksa-buku')->assertSuccessful();
});

it('menyetujui pencairan dan mengeluarkan dana ke eksternal', function () {
    $this->sebagai($this->sri);
    ($this->ajukan)(200_000)->assertCreated();
    $pencairan = Pencairan::query()->sole();

    $this->sebagai($this->keuangan);
    ($this->kirim)("keuangan/pencairan/{$pencairan->kode}/setujui")
        ->assertOk()
        ->assertJsonPath('data.status', 'disetujui')
        ->assertJsonPath('data.diputuskan_oleh.id', $this->keuangan->id);

    expect(($this->saldo)('pencairan'))->toBe(0)
        ->and(($this->saldoSri)())->toBe(163_500)
        ->and(LogAudit::query()->where('peristiwa', 'pencairan.disetujui')->count())->toBe(1);

    $this->artisan('lapak:periksa-buku')->assertSuccessful();
});

it('menolak pencairan dan mengembalikan dana ke dompet penjual', function () {
    $this->sebagai($this->sri);
    ($this->ajukan)(200_000)->assertCreated();
    $pencairan = Pencairan::query()->sole();

    $this->sebagai($this->keuangan);
    ($this->kirim)("keuangan/pencairan/{$pencairan->kode}/tolak", [
        'catatan' => 'Nama pemilik rekening berbeda',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'ditolak')
        ->assertJsonPath('data.catatan', 'Nama pemilik rekening berbeda');

    expect(($this->saldo)('pencairan'))->toBe(0)
        ->and(($this->saldoSri)())->toBe(363_500)
        ->and(LogAudit::query()->where('peristiwa', 'pencairan.ditolak')->count())->toBe(1);

    $this->artisan('lapak:periksa-buku')->assertSuccessful();
});

it('melarang pengaju memutuskan pencairannya sendiri (LP-16)', function () {
    // Bu Sri diangkat menjadi staf keuangan. Izinnya ada; aturannya tetap melarang.
    $sri = Pengguna::factory()->penjual()->keuangan()->create();
    $milikSri = Pencairan::factory()->create(['pengaju_id' => $sri->id]);

    $this->sebagai($sri);
    ($this->kirim)("keuangan/pencairan/{$milikSri->kode}/setujui")
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'LP-16');

    expect($milikSri->refresh()->status)->toBe(StatusPencairan::Menunggu)
        ->and(LogAudit::query()->where('peristiwa', 'pencairan.disetujui')->count())->toBe(0);
});

it('menolak keputusan ganda atas pengajuan yang sudah diputuskan (LP-16)', function () {
    $this->sebagai($this->sri);
    ($this->ajukan)(200_000)->assertCreated();
    $pencairan = Pencairan::query()->sole();

    $this->sebagai($this->keuangan);
    ($this->kirim)("keuangan/pencairan/{$pencairan->kode}/setujui")->assertOk();
    ($this->kirim)("keuangan/pencairan/{$pencairan->kode}/tolak", ['catatan' => 'Terlambat'])
        ->assertConflict()
        ->assertJsonPath('galat.kode', 'LP-16');
});

it('menutup pintu pencairan bagi peran yang tidak berwenang', function () {
    $auditor = Pengguna::factory()->auditor()->create();
    $this->sebagai($auditor);

    $this->getJson('/api/v1/keuangan/pencairan')->assertForbidden();
    $this->getJson('/api/v1/dompet/pencairan')->assertForbidden();
});
