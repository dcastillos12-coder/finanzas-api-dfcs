<?php

namespace App\Http\Resources\Ingresos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Ingreso
 */
class IngresoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fecha' => $this->fecha->toDateString(),
            'fuente' => $this->fuente,
            'monto' => $this->monto,
            'notas' => $this->notas,
            'categoria_id' => $this->categoria_id,
            'categoria' => $this->whenLoaded(
                'categoria',
                fn (): CategoriaIngresoResource => new CategoriaIngresoResource($this->categoria),
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
