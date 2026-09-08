<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubcategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'max:80',
                Rule::unique('subcategorias', 'nombre')->where(function ($query): void {
                    $query->where('categoria_id', $this->route('categoria'));
                }),
            ],
        ];
    }
}
