<?php

namespace App\Http\Requests;

use App\Models\Subcategoria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubcategoriaRequest extends FormRequest
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
        $categoriaId = Subcategoria::query()
            ->whereKey($this->route('subcategoria'))
            ->value('categoria_id');

        return [
            'nombre' => [
                'required',
                'string',
                'max:80',
                Rule::unique('subcategorias', 'nombre')->where(function ($query) use ($categoriaId): void {
                    $query->where('categoria_id', $categoriaId);
                })->ignore($this->route('subcategoria')),
            ],
        ];
    }
}
