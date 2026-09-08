<?php

namespace App\Http\Resources\Ingresos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Categoria
 */
class CategoriaIngresoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'tipo' => $this->tipo,
        ];
    }
}
