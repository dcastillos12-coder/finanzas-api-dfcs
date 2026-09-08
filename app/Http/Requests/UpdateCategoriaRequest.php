<?php

namespace App\Http\Requests;

use App\Models\Categoria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoriaRequest extends FormRequest
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
        $tipo = $this->input('tipo') ?? Categoria::query()
            ->whereKey($this->route('categoria'))
            ->value('tipo');

        return [
            'nombre' => [
                'sometimes',
                'string',
                'max:80',
                Rule::unique('categorias', 'nombre')
                    ->where(function ($query) use ($tipo): void {
                        $query->where('user_id', $this->user()->id)
                            ->where('tipo', $tipo);
                    })
                    ->ignore($this->route('categoria')),
            ],
            'tipo' => ['sometimes', 'string', 'in:ingreso,egreso'],
        ];
    }
}
