<?php

declare(strict_types=1);

use App\Enums\NamaPeran;
use App\Enums\PeristiwaAudit;
use App\Enums\StatusAkun;
use App\Models\LogAudit;
use App\Models\Pengguna;
use App\Services\PenerbitSesi;
use App\Services\PengelolaPeran;
use Database\Seeders\PeranIzinSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed(PeranIzinSeeder::class);

    $this->sri = Pengguna::factory()->create([
        'surel' => 'sri@lapak.test',
        'kata_sandi' => Hash::make('BatikSolo2026'),
        'status' => StatusAkun::Aktif,
    ]);

    $this->jejak = fn (string $peristiwa) => LogAudit::query()->where('peristiwa', $peristiwa);
});

it('mencatat masuk yang berhasil bersama penerbitan sesi', function (): void {
    $respon = $this->postJson('/api/v1/masuk', [
        'surel' => 'sri@lapak.test',
        'kata_sandi' => 'BatikSolo2026',
        'nama_perangkat' => 'Laptop',
    ])->assertCreated();

    $pengenal = $respon->json('data.sesi.pengenal');
    $jejak = ($this->jejak)(PeristiwaAudit::MasukBerhasil->value)->sole();

    expect($jejak->pelaku_id)->toBe($this->sri->id)
        ->and($jejak->sasaran)->toBe("sesi:{$pengenal}")
        ->and($jejak->rincian)->toEqual(['perangkat' => 'Laptop']);
});

it('mencatat masuk yang gagal tanpa menyimpan kata sandi yang dicoba', function (): void {
    $this->postJson('/api/v1/masuk', [
        'surel' => 'sri@lapak.test',
        'kata_sandi' => 'BatikSolo2025',
        'nama_perangkat' => 'Laptop',
    ])->assertUnauthorized();

    $jejak = ($this->jejak)(PeristiwaAudit::MasukGagal->value)->sole();

    // Tanpa pelaku: kata sandi yang salah tidak membuktikan siapa pun.
    expect($jejak->pelaku_id)->toBeNull()
        ->and($jejak->rincian)->toEqual([
            'alasan' => 'kredensial_salah',
            'surel' => 'sri@lapak.test',
        ])
        ->and(json_encode(LogAudit::query()->get()))->not->toContain('BatikSolo2025');
});

it('mencatat pelaku saat akun ditangguhkan mencoba masuk dengan kata sandi benar', function (): void {
    $this->sri->forceFill(['status' => StatusAkun::Ditangguhkan])->save();

    $this->postJson('/api/v1/masuk', [
        'surel' => 'sri@lapak.test',
        'kata_sandi' => 'BatikSolo2026',
        'nama_perangkat' => 'Laptop',
    ])->assertForbidden();

    $jejak = ($this->jejak)(PeristiwaAudit::MasukGagal->value)->sole();

    expect($jejak->pelaku_id)->toBe($this->sri->id)
        ->and($jejak->rincian)->toEqual([
            'alasan' => 'akun_ditangguhkan',
            'surel' => 'sri@lapak.test',
        ]);
});

it('tidak mencatat permintaan masuk yang gugur di validasi', function (): void {
    // Orang kadang mengetik kata sandi di kolom surel. Isian itu tidak boleh sampai ke log.
    $this->postJson('/api/v1/masuk', [
        'surel' => 'BatikSolo2026',
        'kata_sandi' => 'BatikSolo2026',
        'nama_perangkat' => 'Laptop',
    ])->assertUnprocessable();

    expect(LogAudit::query()->count())->toBe(0);
});

it('mencatat pencabutan sesi saat keluar dan saat mencabut perangkat lain', function (): void {
    $penerbit = app(PenerbitSesi::class);
    [$sesiLaptop, $kunciLaptop] = $penerbit->terbitkan($this->sri, 'Laptop');
    [$sesiPonsel] = $penerbit->terbitkan($this->sri, 'Ponsel');

    $this->withToken($kunciLaptop)
        ->deleteJson("/api/v1/sesi/{$sesiPonsel->pengenal}")
        ->assertOk();

    $this->withToken($kunciLaptop)
        ->postJson('/api/v1/keluar')
        ->assertOk();

    $jejak = ($this->jejak)(PeristiwaAudit::SesiDicabut->value)->orderBy('id')->get();

    expect($jejak)->toHaveCount(2)
        ->and($jejak[0]->pelaku_id)->toBe($this->sri->id)
        ->and($jejak[0]->sasaran)->toBe("sesi:{$sesiPonsel->pengenal}")
        ->and($jejak[0]->rincian)->toEqual(['perangkat' => 'Ponsel'])
        ->and($jejak[1]->pelaku_id)->toBe($this->sri->id)
        ->and($jejak[1]->sasaran)->toBe("sesi:{$sesiLaptop->pengenal}")
        ->and($jejak[1]->rincian)->toEqual(['perangkat' => 'Laptop']);
});

it('mencatat pemberian dan pencabutan peran', function (): void {
    $admin = Pengguna::factory()->create();
    $pengelola = app(PengelolaPeran::class);

    $pengelola->beri($this->sri, NamaPeran::Auditor, $admin);
    $pengelola->cabut($this->sri, NamaPeran::Auditor, $admin);

    $diberikan = ($this->jejak)(PeristiwaAudit::PeranDiberikan->value)->sole();
    $dicabut = ($this->jejak)(PeristiwaAudit::PeranDicabut->value)->sole();

    expect($diberikan->pelaku_id)->toBe($admin->id)
        ->and($diberikan->sasaran)->toBe("pengguna:{$this->sri->id}")
        ->and($diberikan->rincian)->toEqual(['peran' => NamaPeran::Auditor->value])
        ->and($dicabut->pelaku_id)->toBe($admin->id)
        ->and($dicabut->sasaran)->toBe("pengguna:{$this->sri->id}")
        ->and($dicabut->rincian)->toEqual(['peran' => NamaPeran::Auditor->value]);
});

it('membatalkan perubahan peran bila jejaknya gagal ditulis', function (): void {
    LogAudit::creating(fn () => throw new RuntimeException('Tabel log audit bermasalah.'));

    expect(fn () => app(PengelolaPeran::class)->beri($this->sri, NamaPeran::Auditor))
        ->toThrow(RuntimeException::class);

    // Tindakan dan jejaknya satu transaksi: tidak ada peran tanpa catatan.
    expect($this->sri->fresh()->punyaPeran(NamaPeran::Auditor))->toBeFalse();
});
