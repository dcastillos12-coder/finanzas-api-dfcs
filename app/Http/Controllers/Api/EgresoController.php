<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexEgresoRequest;
use App\Http\Requests\StoreEgresoRequest;
use App\Http\Requests\UpdateEgresoRequest;
use App\Models\Egreso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EgresoController extends Controller
{
    /**
     * Display the authenticated user's expenses.
     */
    public function index(IndexEgresoRequest $request): JsonResponse
    {
        $query = Egreso::query()
            ->where('user_id', $request->user()->id)
            ->with(['categoria', 'subcategoria']);

        $anio = $request->integer('anio');
        $mes = $request->integer('mes');

        if ($anio !== 0 && $mes !== 0) {
            $query->delMes($anio, $mes);
        } elseif ($anio !== 0) {
            $query
                ->where('fecha', '>=', sprintf('%d-01-01', $anio))
                ->where('fecha', '<', sprintf('%d-01-01', $anio + 1));
        }

        return response()->json(
            $query->orderByDesc('fecha')->orderByDesc('id')->get(),
        );
    }

    /**
     * Store a newly created expense for the authenticated user.
     */
    public function store(StoreEgresoRequest $request): JsonResponse
    {
        $egreso = Egreso::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return response()->json($egreso->load(['categoria', 'subcategoria']), Response::HTTP_CREATED);
    }

    /**
     * Display an expense owned by the authenticated user.
     */
    public function show(Request $request, int $egreso): JsonResponse
    {
        return response()->json(
            $this->egresoDelUsuario($request, $egreso)->load(['categoria', 'subcategoria']),
        );
    }

    /**
     * Update an expense owned by the authenticated user.
     */
    public function update(UpdateEgresoRequest $request, int $egreso): JsonResponse
    {
        $registro = $this->egresoDelUsuario($request, $egreso);
        $datos = $request->validated();

        if (array_key_exists('categoria_id', $datos) && ! array_key_exists('subcategoria_id', $datos)) {
            $datos['subcategoria_id'] = null;
        }

        $registro->update($datos);

        return response()->json($registro->fresh()->load(['categoria', 'subcategoria']));
    }

    /**
     * Delete an expense owned by the authenticated user.
     */
    public function destroy(Request $request, int $egreso): Response
    {
        $this->egresoDelUsuario($request, $egreso)->delete();

        return response()->noContent();
    }

    /**
     * Find an expense while enforcing ownership.
     */
    private function egresoDelUsuario(Request $request, int $egreso): Egreso
    {
        return Egreso::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($egreso);
    }
}
