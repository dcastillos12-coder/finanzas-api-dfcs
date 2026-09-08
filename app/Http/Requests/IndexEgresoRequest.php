<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexEgresoRequest extends FormRequest
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
            'anio' => ['nullable', 'integer', 'between:2000,2100', 'required_with:mes'],
            'mes' => ['nullable', 'integer', 'between:1,12'],
        ];
    }
}
