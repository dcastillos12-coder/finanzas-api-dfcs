<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexIngresoRequest;
use App\Http\Requests\StoreIngresoRequest;
use App\Http\Requests\UpdateIngresoRequest;
use App\Http\Resources\Ingresos\IngresoCollection;
use App\Http\Resources\Ingresos\IngresoResource;
use App\Models\Ingreso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IngresoController extends Controller
{
    public function index(IndexIngresoRequest $request): JsonResponse
    {
        $query = Ingreso::query()
            ->where('user_id', $request->user()->id)
            ->with('categoria');

        $anio = $request->integer('anio');
        $mes = $request->integer('mes');

        if ($anio !== 0 && $mes !== 0) {
            $query->delMes($anio, $mes);
        } elseif ($anio !== 0) {
            $query
                ->where('fecha', '>=', sprintf('%d-01-01', $anio))
                ->where('fecha', '<', sprintf('%d-01-01', $anio + 1));
        }

        return (new IngresoCollection(
            $query->orderByDesc('fecha')->orderByDesc('id')->get(),
        ))->response();
    }

    public function store(StoreIngresoRequest $request): JsonResponse
    {
        $ingreso = Ingreso::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return (new IngresoResource($ingreso->load('categoria')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, int $ingreso): JsonResponse
    {
        return (new IngresoResource(
            $this->ingresoDelUsuario($request, $ingreso)->load('categoria'),
        ))->response();
    }

    public function update(UpdateIngresoRequest $request, int $ingreso): JsonResponse
    {
        $registro = $this->ingresoDelUsuario($request, $ingreso);
        $registro->update($request->validated());

        return (new IngresoResource($registro->fresh()->load('categoria')))->response();
    }

    public function destroy(Request $request, int $ingreso): Response
    {
        $this->ingresoDelUsuario($request, $ingreso)->delete();

        return response()->noContent();
    }

    private function ingresoDelUsuario(Request $request, int $ingreso): Ingreso
    {
        return Ingreso::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($ingreso);
    }
}
