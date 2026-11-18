<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class KirimPesananRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nomor_resi'))) {
            $this->merge([
                'nomor_resi' => mb_strtoupper(trim($this->input('nomor_resi'))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'nomor_resi' => ['required', 'string', 'min:4', 'max:60'],
        ];
    }
}
