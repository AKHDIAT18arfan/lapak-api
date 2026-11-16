
<?php

use App\Domain\Audit\RantaiHash;

it('menghasilkan hash awal berupa 64 karakter nol', function () {
    expect(RantaiHash::awal())->toBe(str_repeat('0', 64));
});

it('menghasilkan hash SHA-256 dari isi kanonik', function () {
    $isi = ['nama' => 'Nabhan', 'jumlah' => 10000];

    $hash = RantaiHash::hitung(RantaiHash::awal(), $isi);

    expect($hash)->toBe(hash(
        'sha256',
        RantaiHash::awal()."\n".RantaiHash::kanonik($isi)
    ));
});

it('menghasilkan bentuk kanonik dengan kunci terurut', function () {
    $isi = ['z' => 1, 'a' => 2];

    expect(RantaiHash::kanonik($isi))->toBe('{"a":2,"z":1}');
});

it('menghasilkan bentuk kanonik yang sama untuk urutan kunci berbeda', function () {
    $isiPertama = ['nama' => 'Nabhan', 'jumlah' => 10000];
    $isiKedua = ['jumlah' => 10000, 'nama' => 'Nabhan'];

    expect(RantaiHash::kanonik($isiPertama))
        ->toBe(RantaiHash::kanonik($isiKedua));
});

it('menolak nilai pecahan', function () {
    RantaiHash::kanonik(['jumlah' => 10.5]);
})->throws(InvalidArgumentException::class, 'tidak boleh memuat pecahan.');

it('menolak objek', function () {
    RantaiHash::kanonik(['data' => (object) ['nama' => 'Nabhan']]);
})->throws(InvalidArgumentException::class, 'harus berupa skalar atau larik.');
