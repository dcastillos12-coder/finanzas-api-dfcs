<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIngresoRequest extends FormRequest
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
                'required',
                'integer',
                Rule::exists('categorias', 'id')->where(function ($query): void {
                    $query->where('tipo', 'ingreso')
                        ->where(function ($query): void {
                            $query->whereNull('user_id')
                                ->orWhere('user_id', $this->user()->id);
                        });
                }),
            ],
            'fecha' => ['required', 'date'],
            'fuente' => ['required', 'string', 'max:150'],
            'monto' => ['required', 'decimal:0,2', 'gt:0'],
            'notas' => ['nullable', 'string'],
        ];
    }
}
