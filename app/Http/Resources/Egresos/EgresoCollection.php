<?php

namespace App\Http\Resources\Egresos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class EgresoCollection extends ResourceCollection
{
    /**
     * The resource that this collection collects.
     *
     * @var class-string<\Illuminate\Http\Resources\Json\JsonResource>
     */
    public $collects = EgresoResource::class;

    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }
}
