it('menghasilkan hash yang dapat dihitung ulang dari baris yang tersimpan', function () {
    $this->pencatat->catat(PeristiwaAudit::PencairanDisetujui, $this->admin, 'pencairan:3', [
        'jumlah_rupiah' => 80_000, 'pengaju' => 4, 'catatan' => 'Sesuai mutasi — lunas',
    ]);

    // Dibaca ulang dari basis data: urutan kunci JSON boleh berubah, hash harus tetap cocok.
    $tersimpan = LogAudit::query()->sole();

    expect(RantaiHash::hitung($tersimpan->hash_sebelumnya, $tersimpan->isiTerlindung()))
        ->toBe($tersimpan->hash);
});

it('menolak rincian yang memuat rahasia, di tingkat mana pun', function (array $rincian) {
    expect(fn () => $this->pencatat->catat(PeristiwaAudit::MasukGagal, null, null, $rincian))
        ->toThrow(LogicException::class, 'tidak boleh memuat');

    expect(LogAudit::count())->toBe(0);
})->with([
    'kata sandi' => [['surel' => 'a@b.c', 'kata_sandi' => 'rahasia123']],
    'kunci sesi' => [['kunci' => 'lpk_...']],
    'bersarang' => [['permintaan' => ['badan' => ['tanda_tangan' => 'abc']]]],
]);

it('tidak dapat diubah maupun dihapus lewat model', function () {
    $log = $this->pencatat->catat(PeristiwaAudit::TokoDisetujui, $this->admin, 'toko:4');

    expect(fn () => $log->forceFill(['sasaran' => 'toko:5'])->save())
        ->toThrow(LogicException::class, 'LP-17');
    expect(fn () => $log->delete())->toThrow(LogicException::class, 'LP-17');

    expect(LogAudit::sole()->sasaran)->toBe('toko:4');
});

it('ikut batal bila transaksi tindakannya batal', function () {
    try {
        DB::transaction(function () {
            $this->pencatat->catat(PeristiwaAudit::DanaDilepas, null, 'pesanan:LPK-1');

            throw new RuntimeException('tindakan gagal setelah dicatat');
        });
    } catch (RuntimeException) {
        // memang diharapkan
    }

    expect(LogAudit::count())->toBe(0);
});