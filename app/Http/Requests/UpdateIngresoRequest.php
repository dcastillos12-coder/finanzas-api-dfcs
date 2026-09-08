<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIngresoRequest extends FormRequest
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
            'categoria_id' => [
                'sometimes',
                'integer',
                Rule::exists('categorias', 'id')->where(function ($query): void {
                    $query->where('tipo', 'ingreso')
                        ->where(function ($query): void {
                            $query->whereNull('user_id')
                                ->orWhere('user_id', $this->user()->id);
                        });
                }),
            ],
            'fecha' => ['sometimes', 'date'],
            'fuente' => ['sometimes', 'string', 'max:150'],
            'monto' => ['sometimes', 'decimal:0,2', 'gt:0'],
            'notas' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
