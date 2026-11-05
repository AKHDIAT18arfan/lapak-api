<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Balapan\SkenarioBalapan;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

final class Balapan extends Command
{
    protected $signature = 'lapak:balapan {skenario=serempak} {--proses=}';

    protected $description = 'Menjalankan skenario pengujian balapan serempak';

    public static function muat(string $nama): SkenarioBalapan
    {
        $kelas = 'App\\Balapan\\'.Str::studly($nama);
        if (! class_exists($kelas)) {
            throw new \InvalidArgumentException("Skenario [{$nama}] tidak ditemukan.");
        }

        return new $kelas;
    }

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Uji balapan tidak boleh dijalankan di lingkungan produksi!');

            return self::FAILURE;
        }

        if (config('database.default') === 'sqlite') {
            $this->error('Basis data saat ini SQLite, yang hanya mengizinkan satu penulis dan mengabaikan lockForUpdate(). Hasilnya tidak berarti. Pakai MySQL.');

            return self::FAILURE;
        }

        $nama = (string) $this->argument('skenario');

        return $this->jalankan($nama) ? self::SUCCESS : self::FAILURE;
    }

    private function jalankan(string $nama): bool
    {
        $skenario = self::muat($nama);
        $jumlah = (int) ($this->option('proses') ?: $skenario->proses());

        $this->newLine();
        $this->line("<options=bold>{$nama}</>: {$skenario->keterangan()}");

        $konteks = $skenario->siapkan();
        $konteks['mulai'] = microtime(true) + max(2.0, $jumlah * 0.3);

        $muatan = base64_encode(json_encode($konteks, JSON_THROW_ON_ERROR));

        $anak = [];
        for ($nomor = 1; $nomor <= $jumlah; $nomor++) {
            $proses = new Process(
                [PHP_BINARY, 'artisan', 'lapak:balapan-anak', $nama, (string) $nomor, $muatan],
                base_path(),
            );
            $proses->setTimeout(120);
            $proses->start();
            $anak[] = $proses;
        }

        $hasil = [];
        foreach ($anak as $proses) {
            $proses->wait();
            $keluaran = trim($proses->getOutput());
            $hasil[] = $keluaran !== ''
                ? $keluaran
                : 'GALAT '.Str::limit(trim($proses->getErrorOutput()), 90);
        }

        $rekap = array_count_values($hasil);
        ksort($rekap);

        $this->table(
            ['Hasil', 'Jumlah proses'],
            array_map(null, array_keys($rekap), array_values($rekap)),
        );

        $pelanggaran = $skenario->periksa($konteks, $hasil);
        foreach ($pelanggaran as $pesan) {
            $this->error('PELANGGARAN: '.$pesan);
        }

        if ($pelanggaran === []) {
            $this->info("LULUS: {$jumlah} proses serentak, tidak ada aturan yang dilanggar.");
        }

        return $pelanggaran === [];
    }
}
