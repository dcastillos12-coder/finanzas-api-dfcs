<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubcategoriaRequest;
use App\Http\Requests\UpdateSubcategoriaRequest;
use App\Http\Resources\Categorias\SubcategoriaResource;
use App\Models\Categoria;
use App\Models\Subcategoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SubcategoriaController extends Controller
{
    public function index(Request $request, int $categoria): JsonResponse
    {
        $this->categoriaVisible($request, $categoria);

        return SubcategoriaResource::collection(
            Subcategoria::query()->where('categoria_id', $categoria)->orderBy('nombre')->get(),
        )->response();
    }

    public function store(StoreSubcategoriaRequest $request, int $categoria): JsonResponse
    {
        $this->categoriaDelUsuario($request, $categoria);

        $subcategoria = Subcategoria::query()->create([
            ...$request->validated(),
            'categoria_id' => $categoria,
        ]);

        return (new SubcategoriaResource($subcategoria))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, int $subcategoria): JsonResponse
    {
        $registro = Subcategoria::query()
            ->whereHas('categoria', function ($query) use ($request): void {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $request->user()->id);
            })
            ->findOrFail($subcategoria);

        return (new SubcategoriaResource($registro))->response();
    }

    public function update(UpdateSubcategoriaRequest $request, int $subcategoria): JsonResponse
    {
        $registro = $this->subcategoriaDelUsuario($request, $subcategoria);
        $registro->update($request->validated());

        return (new SubcategoriaResource($registro->fresh()))->response();
    }

    public function destroy(Request $request, int $subcategoria): Response
    {
        $this->subcategoriaDelUsuario($request, $subcategoria)->delete();

        return response()->noContent();
    }

    private function categoriaVisible(Request $request, int $categoria): Categoria
    {
        return Categoria::query()->where(function ($query) use ($request): void {
            $query->whereNull('user_id')
                ->orWhere('user_id', $request->user()->id);
        })->findOrFail($categoria);
    }

    private function categoriaDelUsuario(Request $request, int $categoria): Categoria
    {
        return Categoria::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($categoria);
    }

    private function subcategoriaDelUsuario(Request $request, int $subcategoria): Subcategoria
    {
        return Subcategoria::query()
            ->whereHas('categoria', function ($query) use ($request): void {
                $query->where('user_id', $request->user()->id);
            })
            ->findOrFail($subcategoria);
    }
}
