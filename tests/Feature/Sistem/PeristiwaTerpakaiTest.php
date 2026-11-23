<?php

use App\Enums\PeristiwaAudit;

/** @return array<string, list<string>> nama kasus enum => berkas yang mencatatnya */
function pencatatPerPeristiwa(): array
{
    $peta = array_fill_keys(array_column(PeristiwaAudit::cases(), 'name'), []);
    $berkas = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

    foreach ($berkas as $satu) {
        $jalur = $satu->getPathname();

        // Enum itu sendiri dan skenario uji balapan bukan pencatat sungguhan.
        if (! $satu->isFile()
            || $satu->getFilename() === 'PeristiwaAudit.php'
            || str_contains($jalur, DIRECTORY_SEPARATOR.'Balapan'.DIRECTORY_SEPARATOR)) {
            continue;
        }

        preg_match_all('/PeristiwaAudit::(\w+)\b/', file_get_contents($jalur), $cocok);

        foreach (array_unique($cocok[1]) as $nama) {
            if (array_key_exists($nama, $peta)) {
                $peta[$nama][] = $satu->getFilename();
            }
        }
    }

    return $peta;
}

it('tidak menyimpan peristiwa yang tidak pernah dicatat', function () {
    $menganggur = array_keys(array_filter(pencatatPerPeristiwa(), fn (array $b) => $b === []));
    $nama = array_map(
        fn (string $kasus) => constant(PeristiwaAudit::class.'::'.$kasus)->value,
        $menganggur,
    );

    expect($nama)->toBeEmpty('Peristiwa tanpa pencatat: '.implode(', ', $nama));
});

it('menamai peristiwa <benda>.<kejadian> dan tidak ada yang kembar', function () {
    $nilai = array_column(PeristiwaAudit::cases(), 'value');

    foreach ($nilai as $satu) {
        expect($satu)->toMatch('/^[a-z]+\.[a-z_]+$/');
    }

    expect($nilai)->toHaveCount(count(array_unique($nilai)));
});
