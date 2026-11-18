<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Pesanan\StatusPesanan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DaftarPesananTokoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', Rule::enum(StatusPesanan::class)],
        ];
    }
}
