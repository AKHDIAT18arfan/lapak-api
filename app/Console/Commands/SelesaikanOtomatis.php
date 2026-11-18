<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PenyelesaianOtomatis;
use Illuminate\Console\Command;

final class SelesaikanOtomatis extends Command
{
    protected $signature = 'lapak:selesaikan-otomatis';

    protected $description = 'Menyelesaikan pesanan yang telah melewati masa tahan dana dan melepas dananya.';

    public function handle(PenyelesaianOtomatis $layanan): int
    {
        $jumlah = $layanan->jalankan();

        $this->info("{$jumlah} pesanan diselesaikan.");

        return self::SUCCESS;
    }
}
