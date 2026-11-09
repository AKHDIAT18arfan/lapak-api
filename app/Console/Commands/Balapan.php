<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Balapan\SkenarioBalapan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Process\Process;

/**
 * Menjalankan skenario uji balapan: beberapa proses PHP sungguhan melakukan hal yang sama
 * pada saat yang sama, lalu keadaan akhirnya diperiksa terhadap aturan yang tidak boleh dilanggar.
 *
 *   php artisan lapak:balapan <skenario> [--proses=N]
 *   php artisan lapak:balapan --semua
 *
 * Keluar dengan kode 1 bila satu skenario saja melaporkan pelanggaran.
 */
final class Balapan extends Command
{
    protected $signature = 'lapak:balapan
        {skenario? : Nama skenario, mis. rebutan-stok}
        {--semua : Jalankan seluruh skenario balapan}
        {--proses= : Timpa jumlah proses yang dilepas bersamaan}';

    protected $description = 'Menjalankan simulasi kondisi balapan (race condition) serentak';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Uji balapan menulis data percobaan dan tidak boleh dijalankan di produksi.');

            return self::FAILURE;
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->error('Uji balapan harus dijalankan pada MySQL: SQLite hanya mengizinkan satu penulis.');

            return self::FAILURE;
        }

        if (! $this->option('semua') && ! $this->argument('skenario')) {
            $this->error('Sebutkan nama skenario, atau pakai --semua.');
            $this->line('Skenario yang tersedia: '.implode(', ', self::semuaSkenario()));

            return self::FAILURE;
        }

        $daftar = $this->option('semua')
            ? self::semuaSkenario()
            : [(string) $this->argument('skenario')];

        $lulus = true;
        foreach ($daftar as $nama) {
            // jalankan() ditulis lebih dulu pada ekspresi ini supaya skenario berikutnya
            // tetap berjalan walaupun satu skenario sudah melanggar.
            $lulus = $this->jalankan($nama) && $lulus;
        }

        return $lulus ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Seluruh skenario pada app/Balapan, berurutan menurut nama.
     * 'serempak' (kalibrasi) selalu paling awal: bila mesin terlalu lambat,
     * hasil skenario lain tidak dapat dipercaya.
     *
     * @return list<string> mis. ['serempak', 'alamat-pertama', ...]
     */
    public static function semuaSkenario(): array
    {
        $nama = [];

        foreach (glob(app_path('Balapan/*.php')) ?: [] as $berkas) {
            $kelas = 'App\\Balapan\\'.basename($berkas, '.php');

            // class_exists() bernilai false untuk antarmuka dan trait (SkenarioBalapan, PanggilHttp).
            if (class_exists($kelas) && is_subclass_of($kelas, SkenarioBalapan::class)) {
                $nama[] = Str::kebab(class_basename($kelas));
            }
        }

        sort($nama);
        usort($nama, fn (string $a, string $b) => ($b === 'serempak') <=> ($a === 'serempak'));

        return $nama;
    }

    /** rebutan-stok -> RebutanStok */
    public static function muat(string $nama): SkenarioBalapan
    {
        $kelas = 'App\\Balapan\\'.Str::studly($nama);

        if (! class_exists($kelas) || ! is_subclass_of($kelas, SkenarioBalapan::class)) {
            throw new InvalidArgumentException(
                "Skenario '{$nama}' tidak ditemukan. Tersedia: ".implode(', ', self::semuaSkenario()),
            );
        }

        return app($kelas);
    }

    private function jalankan(string $nama): bool
    {
        $skenario = self::muat($nama);
        $jumlah = (int) ($this->option('proses') ?: $skenario->proses());

        $this->newLine();
        $this->line("<options=bold>{$nama}</>: {$skenario->keterangan()}");

        $konteks = $skenario->siapkan();

        // Setiap anak perlu waktu untuk menyalakan Laravel. Semuanya menunggu sampai detik ini,
        // lalu berangkat bersama. Tanpa garis mulai, anak pertama sudah selesai sebelum
        // anak terakhir lahir, dan tidak ada yang berlomba.
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
