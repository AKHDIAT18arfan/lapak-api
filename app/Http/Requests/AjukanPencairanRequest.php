<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AjukanPencairanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'jumlah_rupiah' => ['required', 'integer', 'min:1'],
            'rekening_tujuan' => ['required', 'string', 'min:5', 'max:160'],
        ];
    }
}
