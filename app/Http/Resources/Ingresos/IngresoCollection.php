<?php

namespace App\Http\Resources\Ingresos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class IngresoCollection extends ResourceCollection
{
    /** @var class-string<\Illuminate\Http\Resources\Json\JsonResource> */
    public $collects = IngresoResource::class;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }
}
