<?php

use App\Domain\Pesanan\StatusPesanan;
use App\Enums\JenisDompet;
use App\Models\Pengguna;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\BukuBesar;
use Illuminate\Support\Str;
use Tests\Pendukung\PesananUji;

beforeEach(function () {
    $this->sri = Pengguna::factory()->penjual()->create();
    $this->tokoSri = Toko::factory()->for($this->sri, 'pemilik')->create();
    $this->kain = Produk::factory()->for($this->tokoSri)->create([
        'harga_rupiah' => 363_500,
        'stok' => 10,
    ]);

    $this->andi = Pengguna::factory()->pembeli()->create();
    $this->pesanan = PesananUji::dibayar($this->andi, $this->kain);

    $this->alamat = fn (string $akhiran = '') => "/api/v1/toko-saya/pesanan/{$this->pesanan->nomor}{$akhiran}";
    $this->batal = fn (array $badan = ['alasan' => 'Stok di gudang rusak']) => $this
        ->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson(($this->alamat)('/batal'), $badan);
    $this->saldo = fn (string $jenis): int => app(BukuBesar::class)
        ->dompetSistem(JenisDompet::from($jenis))
        ->saldo_rupiah;
});

it('mendaftar hanya pesanan milik toko sendiri dan dapat disaring menurut status', function () {
    $belum = PesananUji::belumDibayar($this->andi, $this->kain);

    $wulan = Pengguna::factory()->penjual()->create();
    $tokoWulan = Toko::factory()->for($wulan, 'pemilik')->create();
    $produkWulan = Produk::factory()->for($tokoWulan)->create();
    PesananUji::dibayar($this->andi, $produkWulan);

    $this->sebagai($this->sri);

    $this->getJson('/api/v1/toko-saya/pesanan')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.nomor', $belum->nomor)
        ->assertJsonPath('data.1.nomor', $this->pesanan->nomor);

    $this->getJson('/api/v1/toko-saya/pesanan?status=dibayar')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.nomor', $this->pesanan->nomor)
        ->assertJsonPath('data.0.tindakan', ['dikemas', 'dibatalkan']);
});

it('menahan alamat kirim sampai pesanan dibayar', function () {
    $belum = PesananUji::belumDibayar($this->andi, $this->kain);

    $this->sebagai($this->sri)->getJson("/api/v1/toko-saya/pesanan/{$belum->nomor}")
        ->assertOk()
        ->assertJsonPath('data.status', 'menunggu_pembayaran')
        ->assertJsonPath('data.alamat_kirim', null)
        ->assertJsonPath('data.tindakan', []);

    $this->getJson(($this->alamat)())
        ->assertOk()
        ->assertJsonPath('data.status', 'dibayar')
        ->assertJsonPath('data.alamat_kirim.nama_penerima', $this->pesanan->alamat_kirim['nama_penerima'])
        ->assertJsonPath('data.tindakan', ['dikemas', 'dibatalkan']);
});

it('menjawab 404 untuk pesanan toko lain pada setiap pintu', function () {
    $wulan = Pengguna::factory()->penjual()->create();
    Toko::factory()->for($wulan, 'pemilik')->create();
    $this->sebagai($wulan);

    $this->getJson(($this->alamat)())->assertNotFound();
    $this->postJson(($this->alamat)('/kemas'))->assertNotFound();
    $this->postJson(($this->alamat)('/kirim'), ['nomor_resi' => 'JNE-00012345'])->assertNotFound();
    ($this->batal)()->assertNotFound();

    expect($this->pesanan->refresh()->status)->toBe(StatusPesanan::Dibayar);
});

it('mengemas lalu mengirim, dan menolak melompati urutan (LP-10)', function () {
    $this->sebagai($this->sri);
    $escrowAwal = ($this->saldo)('escrow');

    // Belum dikemas: tidak dapat langsung dikirim.
    $this->postJson(($this->alamat)('/kirim'), ['nomor_resi' => 'JNE-00012345'])
        ->assertConflict()
        ->assertJsonPath('galat.kode', 'LP-10');

    $this->postJson(($this->alamat)('/kemas'))
        ->assertOk()
        ->assertJsonPath('data.status', 'dikemas')
        ->assertJsonPath('data.tindakan', ['dikirim', 'dibatalkan']);

    // Mengulang "kemas" bukan galat server, melainkan 409: statusnya memang sudah berpindah.
    $this->postJson(($this->alamat)('/kemas'))->assertConflict();

    $this->postJson(($this->alamat)('/kirim'), ['nomor_resi' => ' jne-00012345 '])
        ->assertOk()
        ->assertJsonPath('data.status', 'dikirim')
        ->assertJsonPath('data.nomor_resi', 'JNE-00012345')
        ->assertJsonPath('data.tindakan', []);

    // Mengemas dan mengirim tidak memindahkan uang: dana tetap ditahan (LP-12).
    expect(($this->saldo)('escrow'))->toBe($escrowAwal);
});

it('membatalkan pesanan oleh penjual, mengembalikan stok dan dana secara idempoten (LP-11)', function () {
    $this->sebagai($this->sri);
    $stokSebelum = $this->kain->refresh()->stok;
    $kunci = (string) Str::uuid();

    // Tanpa Idempotency-Key ditolak.
    $this->postJson(($this->alamat)('/batal'), ['alasan' => 'Stok di gudang rusak'])
        ->assertUnprocessable()
        ->assertJsonPath('galat.kode', 'LP-11');

    $pertama = $this->withHeader('Idempotency-Key', $kunci)
        ->postJson(($this->alamat)('/batal'), ['alasan' => 'Stok di gudang rusak'])
        ->assertOk()
        ->assertJsonPath('data.status', 'dibatalkan')
        ->assertJsonPath('data.dibatalkan_oleh', 'penjual')
        ->assertJsonPath('data.alasan_batal', 'Stok di gudang rusak')
        ->assertJsonPath('data.tindakan', []);

    // Pengulangan kunci dan badan yang sama mengembalikan respons tersimpan tanpa efek ganda.
    $this->withHeader('Idempotency-Key', $kunci)
        ->postJson(($this->alamat)('/batal'), ['alasan' => 'Stok di gudang rusak'])
        ->assertOk()
        ->assertHeader('Idempotency-Replayed', 'true')
        ->assertExactJson($pertama->json());

    $dikembalikan = $this->pesanan->butir->sum('kuantitas');

    expect($this->kain->refresh()->stok)->toBe($stokSebelum + $dikembalikan)
        ->and(($this->saldo)('escrow'))->toBe(0);
});

it('menolak pembatalan bila pesanan sudah dikirim (LP-10)', function () {
    $this->sebagai($this->sri);

    $this->postJson(($this->alamat)('/kemas'))->assertOk();
    $this->postJson(($this->alamat)('/kirim'), ['nomor_resi' => 'JNE-00012345'])->assertOk();

    ($this->batal)()
        ->assertConflict()
        ->assertJsonPath('galat.kode', 'LP-10');
});

it('menutup pintu pesanan toko bagi yang tidak memegang izin pesanan.proses', function (string $pemegang) {
    $this->sebagai(Pengguna::factory()->{$pemegang}()->create());

    $this->getJson('/api/v1/toko-saya/pesanan')
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'DITOLAK');
    $this->postJson(($this->alamat)('/kemas'))->assertForbidden();
})->with(['pembeli', 'admin', 'keuangan', 'auditor']);
