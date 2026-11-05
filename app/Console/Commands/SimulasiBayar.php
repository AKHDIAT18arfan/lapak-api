<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Pembayaran;
use App\Services\TandaTanganGateway;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

final class SimulasiBayar extends Command
{
    protected $signature = 'lapak:simulasi-bayar
                            {kode : Kode tagihan (BYR-...)}
                            {--jumlah= : Jumlah rupiah yang dibayar (bawaan: dari tagihan)}
                            {--ganda : Kirim dua kali untuk menguji kabar kembar}
                            {--rahasia= : Gunakan rahasia lain untuk menguji pemalsuan}';

    protected $description = 'Menyimulasikan pengiriman webhook pelunasan dari gerbang pembayaran';

    public function handle(TandaTanganGateway $tandaTangan, Kernel $kernel): int
    {
        if (app()->isProduction()) {
            $this->error('Perintah ini tidak boleh dijalankan di lingkungan produksi.');

            return self::FAILURE;
        }

        $kode = $this->argument('kode');
        $pembayaran = Pembayaran::query()->where('kode', $kode)->first();

        if (! $pembayaran) {
            $this->error("Tagihan {$kode} tidak ditemukan.");

            return self::FAILURE;
        }

        $jumlah = $this->option('jumlah') !== null
            ? (int) $this->option('jumlah')
            : $pembayaran->jumlah_rupiah;

        $kabar = [
            'id_peristiwa' => 'evt_' . bin2hex(random_bytes(8)),
            'kode_pembayaran' => $pembayaran->kode,
            'jumlah_rupiah' => $jumlah,
        ];

        $badan = (string) json_encode($kabar);
        $rahasia = $this->option('rahasia') ? (string) $this->option('rahasia') : null;
        $tanda = $tandaTangan->buat($badan, null, $rahasia);

        $kirim = function () use ($kernel, $badan, $tanda) {
            $request = Request::create(
                '/api/v1/webhook/pembayaran',
                'POST',
                [],
                [],
                [],
                [
                    'HTTP_X_GATEWAY_SIGNATURE' => $tanda,
                    'CONTENT_TYPE' => 'application/json',
                ],
                $badan
            );

            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);

            return $response;
        };

        $respons = $kirim();
        $this->info("Kabar 1: HTTP {$respons->getStatusCode()} - {$respons->getContent()}");

        if ($this->option('ganda')) {
            $responsDua = $kirim();
            $this->info("Kabar 2: HTTP {$responsDua->getStatusCode()} - {$responsDua->getContent()}");
        }

        return self::SUCCESS;
    }
}