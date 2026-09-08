<?php

namespace App\Http\Resources\Categorias;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Categoria
 */
class CategoriaResource extends JsonResource
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
            'es_del_usuario' => $this->user_id === $request->user()?->id,
            'subcategorias' => SubcategoriaResource::collection($this->whenLoaded('subcategorias')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
