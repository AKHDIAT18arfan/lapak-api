<?php

declare(strict_types=1);

namespace App\Exceptions;

final class SesiTidakSegar extends GalatDomain
{
    public function __construct()
    {
        parent::__construct(
            'LP-15',
            'Tindakan ini memerlukan konfirmasi kata sandi.',
            403
        );
    }
}
