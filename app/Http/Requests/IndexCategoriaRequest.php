<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'tipo' => ['nullable', 'string', 'in:ingreso,egreso'],
        ];
    }
}
