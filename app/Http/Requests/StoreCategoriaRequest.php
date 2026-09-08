<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoriaRequest extends FormRequest
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
                Rule::unique('categorias', 'nombre')->where(function ($query): void {
                    $query->where('user_id', $this->user()->id)
                        ->where('tipo', $this->input('tipo'));
                }),
            ],
            'tipo' => ['required', 'string', 'in:ingreso,egreso'],
        ];
    }
}
