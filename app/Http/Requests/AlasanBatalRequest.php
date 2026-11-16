<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alasan membatalkan pesanan atau checkout. Dipakai pintu pembeli dan pintu penjual.
 * Alasan satu huruf bukan alasan: ditolak 422 sebelum menyentuh layanan.
 */
final class AlasanBatalRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Kepemilikan pesanan diperiksa pengendali (404 untuk milik orang lain), bukan di sini.
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('alasan'))) {
            $this->merge(['alasan' => trim($this->input('alasan'))]);
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'alasan' => ['required', 'string', 'min:3', 'max:200'],
        ];
    }

    public function alasan(): string
    {
        return (string) $this->validated('alasan');
    }
}
