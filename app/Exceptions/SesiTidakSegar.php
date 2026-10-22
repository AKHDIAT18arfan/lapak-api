<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SesiTidakSegar extends Exception
{
    /**
     * Render HTTP response untuk exception ini.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'pesan' => 'Sesi membutuhkan konfirmasi ulang.',
            'tindakan' => 'POST /api/v1/sesi/konfirmasi',
        ], 403);
    }
}
