<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexCategoriaRequest;
use App\Http\Requests\StoreCategoriaRequest;
use App\Http\Requests\UpdateCategoriaRequest;
use App\Http\Resources\Categorias\CategoriaResource;
use App\Models\Categoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CategoriaController extends Controller
{
    public function index(IndexCategoriaRequest $request): JsonResponse
    {
        $query = $this->categoriasVisibles($request)
            ->with('subcategorias')
            ->orderBy('tipo')
            ->orderBy('nombre');

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->string('tipo')->toString());
        }

        return CategoriaResource::collection($query->get())->response();
    }

    public function store(StoreCategoriaRequest $request): JsonResponse
    {
        $categoria = Categoria::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return (new CategoriaResource($categoria->load('subcategorias')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, int $categoria): JsonResponse
    {
        $registro = $this->categoriasVisibles($request)
            ->with('subcategorias')
            ->findOrFail($categoria);

        return (new CategoriaResource($registro))->response();
    }

    public function update(UpdateCategoriaRequest $request, int $categoria): JsonResponse
    {
        $registro = $this->categoriaDelUsuario($request, $categoria);
        $registro->update($request->validated());

        return (new CategoriaResource($registro->fresh()->load('subcategorias')))->response();
    }

    public function destroy(Request $request, int $categoria): Response|JsonResponse
    {
        $registro = $this->categoriaDelUsuario($request, $categoria);

        if ($registro->ingresos()->exists() || $registro->egresos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar una categoría que tiene movimientos asociados.',
            ], Response::HTTP_CONFLICT);
        }

        $registro->delete();

        return response()->noContent();
    }

    private function categoriasVisibles(Request $request)
    {
        return Categoria::query()->where(function ($query) use ($request): void {
            $query->whereNull('user_id')
                ->orWhere('user_id', $request->user()->id);
        });
    }

    private function categoriaDelUsuario(Request $request, int $categoria): Categoria
    {
        return Categoria::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($categoria);
    }
}
