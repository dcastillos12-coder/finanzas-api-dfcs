<?php

namespace App\Http\Requests;

use App\Models\Subcategoria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEgresoRequest extends FormRequest
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
                    $query->where('tipo', 'egreso')
                        ->where(function ($query): void {
                            $query->whereNull('user_id')
                                ->orWhere('user_id', $this->user()->id);
                        });
                }),
            ],
            'subcategoria_id' => ['nullable', 'integer', Rule::exists('subcategorias', 'id')],
            'fecha' => ['required', 'date'],
            'descripcion' => ['required', 'string', 'max:150'],
            'monto' => ['required', 'decimal:0,2', 'gt:0'],
            'notas' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->filled('subcategoria_id')) {
                    return;
                }

                $perteneceACategoria = Subcategoria::query()
                    ->whereKey($this->input('subcategoria_id'))
                    ->where('categoria_id', $this->input('categoria_id'))
                    ->exists();

                if (! $perteneceACategoria) {
                    $validator->errors()->add('subcategoria_id', 'La subcategoría no pertenece a la categoría seleccionada.');
                }
            },
        ];
    }
}
