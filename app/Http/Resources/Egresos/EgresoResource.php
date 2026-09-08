<?php

namespace App\Http\Resources\Egresos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Egreso
 */
class EgresoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fecha' => $this->fecha->toDateString(),
            'descripcion' => $this->descripcion,
            'monto' => $this->monto,
            'notas' => $this->notas,
            'categoria_id' => $this->categoria_id,
            'subcategoria_id' => $this->subcategoria_id,
            'categoria' => $this->whenLoaded(
                'categoria',
                fn (): CategoriaEgresoResource => new CategoriaEgresoResource($this->categoria),
            ),
            'subcategoria' => $this->whenLoaded(
                'subcategoria',
                fn (): ?SubcategoriaEgresoResource => $this->subcategoria === null
                    ? null
                    : new SubcategoriaEgresoResource($this->subcategoria),
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
