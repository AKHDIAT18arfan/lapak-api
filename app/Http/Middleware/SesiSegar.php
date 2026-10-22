<?php

namespace App\Http\Middleware;

use App\Exceptions\SesiTidakSegar;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SesiSegar
{
    /**
     * Memastikan sesi pengguna masih segar (baru saja dikonfirmasi).
     *
     * @throws SesiTidakSegar
     */
    public function handle(Request $request, Closure $next): Response
    {
        $sesi = $request->get('sesi');

        if ($sesi === null || ! $sesi->segar()) {
            throw new SesiTidakSegar;
        }

        return $next($request);
    }
}