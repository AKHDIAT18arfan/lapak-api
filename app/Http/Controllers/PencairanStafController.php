<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StatusPencairan;
use App\Http\Requests\TolakPencairanRequest;
use App\Http\Resources\PencairanStafResource;
use App\Models\Pencairan;
use App\Services\LayananPencairan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PencairanStafController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = StatusPencairan::tryFrom((string) $request->query('status'));

        $halaman = Pencairan::query()
            ->with(['pengaju', 'pemutus'])
            ->when($status, fn (Builder $kueri, StatusPencairan $s) => $kueri->where('status', $s))
            ->orderByDesc('id')
            ->paginate(config('lapak.katalog.per_halaman_bawaan'), ['*'], 'halaman');

        return $this->berhalaman($halaman, PencairanStafResource::class);
    }

    public function setujui(Request $request, LayananPencairan $layanan, string $kode): JsonResponse
    {
        $pencairan = Pencairan::query()->where('kode', $kode)->firstOrFail();
        $hasil = $layanan->setujui($pencairan, $request->user());

        return $this->sukses(PencairanStafResource::make($hasil->load(['pengaju', 'pemutus'])));
    }

    public function tolak(TolakPencairanRequest $request, LayananPencairan $layanan, string $kode): JsonResponse
    {
        $pencairan = Pencairan::query()->where('kode', $kode)->firstOrFail();
        $hasil = $layanan->tolak($pencairan, $request->user(), $request->validated('catatan'));

        return $this->sukses(PencairanStafResource::make($hasil->load(['pengaju', 'pemutus'])));
    }
}
