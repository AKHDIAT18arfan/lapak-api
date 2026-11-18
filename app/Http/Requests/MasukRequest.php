<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MasukRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('surel'))) {
            $this->merge(['surel' => mb_strtolower(trim($this->input('surel')))]);
        }
    }

    /**
     * Aturan di sini sengaja longgar. Syarat panjang dan susunan kata sandi adalah urusan registrasi;
     * menerapkannya saat masuk hanya memberi tahu penyerang bentuk kata sandi yang sah.
     *
     * Surel wajib berbentuk surel sejak percobaan masuk dicatat pada log audit (LP-17). Orang
     * kadang mengetik kata sandinya di kolom surel; isian seperti itu harus gugur di sini,
     * sebelum sempat menjadi rincian sebuah jejak yang tidak dapat dihapus.
     */
    public function rules(): array
    {
        return [
            'surel' => ['required', 'string', 'email', 'max:150'],
            'kata_sandi' => ['required', 'string', 'max:72'],
            'nama_perangkat' => ['sometimes', 'string', 'max:60'],
        ];
    }

    public function namaPerangkat(): string
    {
        return $this->string('nama_perangkat', 'Perangkat tanpa nama')->toString();
    }
}
