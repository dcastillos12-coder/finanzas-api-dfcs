<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardRequest;
use App\Http\Requests\DashboardResumenRequest;
use App\Http\Requests\EgresosPorCategoriaRequest;
use App\Http\Requests\ResumenAnualRequest;
use App\Models\Egreso;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Return twelve monthly points for the authenticated user's selected year.
     */
    public function resumenAnual(ResumenAnualRequest $request): JsonResponse
    {
        $resumen = $this->resumenMensual($request->user()->id, $request->integer('anio'));

        return response()->json([
            'data' => array_map(
                fn (array $mes): array => [
                    'mes' => $mes['mes'],
                    'ingresos' => $mes['ingresos'],
                    'egresos' => $mes['egresos'],
                    'balance' => $mes['balance'],
                ],
                $resumen,
            ),
        ]);
    }

    /**
     * Return the authenticated user's expenses grouped by category for a month.
     */
    public function egresosPorCategoria(EgresosPorCategoriaRequest $request): JsonResponse
    {
        $inicioMes = CarbonImmutable::create(
            $request->integer('anio'),
            $request->integer('mes'),
            1,
        )->startOfMonth();
        $finMes = $inicioMes->addMonth();
        $usuarioId = $request->user()->id;

        $datos = Egreso::query()
            ->join('categorias', 'categorias.id', '=', 'egresos.categoria_id')
            ->select([
                'categorias.id as categoria_id',
                'categorias.nombre',
            ])
            ->selectRaw('SUM(egresos.monto) as total')
            ->where('egresos.user_id', $usuarioId)
            ->where(function ($query) use ($usuarioId): void {
                $query->whereNull('categorias.user_id')
                    ->orWhere('categorias.user_id', $usuarioId);
            })
            ->where('egresos.fecha', '>=', $inicioMes->toDateString())
            ->where('egresos.fecha', '<', $finMes->toDateString())
            ->groupBy('categorias.id', 'categorias.nombre')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($fila): array => [
                'categoria_id' => (int) $fila->categoria_id,
                'nombre' => $fila->nombre,
                'total' => $this->normalizaMonto($fila->total),
            ])
            ->values()
            ->all();

        return response()->json(['data' => $datos]);
    }

    /**
     * Return the authenticated user's monthly and year-to-date summary.
     */
    public function resumen(DashboardResumenRequest $request): JsonResponse
    {
        $anio = $request->integer('anio');
        $mes = $request->integer('mes');
        $inicioMes = CarbonImmutable::create($anio, $mes, 1)->startOfMonth();
        $finMes = $inicioMes->addMonth();
        $inicioAnio = CarbonImmutable::create($anio, 1, 1)->startOfYear();
        $finAcumulado = $finMes;
        $usuarioId = $request->user()->id;

        $movimientosIngresos = DB::table('ingresos')
            ->selectRaw("fecha, monto, 'ingreso' as tipo")
            ->where('user_id', $usuarioId)
            ->where('fecha', '>=', $inicioAnio->toDateString())
            ->where('fecha', '<', $finAcumulado->toDateString());

        $movimientos = DB::table('egresos')
            ->selectRaw("fecha, monto, 'egreso' as tipo")
            ->where('user_id', $usuarioId)
            ->where('fecha', '>=', $inicioAnio->toDateString())
            ->where('fecha', '<', $finAcumulado->toDateString())
            ->unionAll($movimientosIngresos);

        $resultado = DB::query()
            ->fromSub($movimientos, 'movimientos')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN tipo = ? AND fecha >= ? AND fecha < ? THEN monto ELSE 0 END), 0) as ingresos_mes,
                COALESCE(SUM(CASE WHEN tipo = ? AND fecha >= ? AND fecha < ? THEN monto ELSE 0 END), 0) as egresos_mes,
                COALESCE(SUM(CASE WHEN tipo = ? THEN monto ELSE 0 END), 0) as ingresos_acumulados,
                COALESCE(SUM(CASE WHEN tipo = ? THEN monto ELSE 0 END), 0) as egresos_acumulados',
                [
                    'ingreso', $inicioMes->toDateString(), $finMes->toDateString(),
                    'egreso', $inicioMes->toDateString(), $finMes->toDateString(),
                    'ingreso', 'egreso',
                ],
            )
            ->first();

        $ingresosMes = $this->normalizaMonto($resultado?->ingresos_mes);
        $egresosMes = $this->normalizaMonto($resultado?->egresos_mes);
        $ingresosAcumulados = $this->normalizaMonto($resultado?->ingresos_acumulados);
        $egresosAcumulados = $this->normalizaMonto($resultado?->egresos_acumulados);

        return response()->json([
            'data' => [
                'ingresos_mes' => $ingresosMes,
                'egresos_mes' => $egresosMes,
                'balance_mes' => $this->restaMontos($ingresosMes, $egresosMes),
                'ingresos_acumulados' => $ingresosAcumulados,
                'egresos_acumulados' => $egresosAcumulados,
                'balance_acumulado' => $this->restaMontos($ingresosAcumulados, $egresosAcumulados),
                'porcentaje_gastado' => $this->porcentajeGastado($ingresosMes, $egresosMes),
            ],
        ]);
    }

    /**
     * Return the authenticated user's financial summary for a year or month.
     */
    public function __invoke(DashboardRequest $request): JsonResponse
    {
        $anio = $request->integer('anio') ?: now()->year;
        $mes = $request->integer('mes') ?: null;
        $usuarioId = $request->user()->id;

        $inicioAnio = CarbonImmutable::create($anio, 1, 1)->startOfYear();
        $finAnio = $inicioAnio->addYear();
        $inicioPeriodo = $mes === null
            ? $inicioAnio
            : CarbonImmutable::create($anio, $mes, 1)->startOfMonth();
        $finPeriodo = $mes === null ? $finAnio : $inicioPeriodo->addMonth();

        $totalIngresos = $this->sumaPorPeriodo('ingresos', $usuarioId, $inicioPeriodo, $finPeriodo);
        $totalEgresos = $this->sumaPorPeriodo('egresos', $usuarioId, $inicioPeriodo, $finPeriodo);

        return response()->json([
            'data' => [
                'periodo' => [
                    'anio' => $anio,
                    'mes' => $mes,
                ],
                'totales' => [
                    'ingresos' => $totalIngresos,
                    'egresos' => $totalEgresos,
                    'balance' => $this->restaMontos($totalIngresos, $totalEgresos),
                ],
                'resumen_mensual' => $this->resumenMensual($usuarioId, $anio),
                'ingresos_por_categoria' => $this->totalesPorCategoria(
                    'ingresos',
                    $usuarioId,
                    $inicioPeriodo,
                    $finPeriodo,
                ),
                'egresos_por_categoria' => $this->totalesPorCategoria(
                    'egresos',
                    $usuarioId,
                    $inicioPeriodo,
                    $finPeriodo,
                ),
            ],
        ]);
    }

    /**
     * @return string
     */
    private function sumaPorPeriodo(
        string $tabla,
        int $usuarioId,
        CarbonImmutable $inicio,
        CarbonImmutable $fin,
    ): string {
        $suma = DB::table($tabla)
            ->where('user_id', $usuarioId)
            ->where('fecha', '>=', $inicio->toDateString())
            ->where('fecha', '<', $fin->toDateString())
            ->sum('monto');

        return $this->normalizaMonto($suma);
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function resumenMensual(int $usuarioId, int $anio): array
    {
        $nombresMeses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        $expresionMes = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%m', fecha) AS INTEGER)"
            : 'MONTH(fecha)';

        $ingresos = DB::table('ingresos')
            ->selectRaw($expresionMes . ' as numero_mes, SUM(monto) as total')
            ->where('user_id', $usuarioId)
            ->whereYear('fecha', $anio)
            ->groupByRaw($expresionMes)
            ->pluck('total', 'numero_mes');

        $egresos = DB::table('egresos')
            ->selectRaw($expresionMes . ' as numero_mes, SUM(monto) as total')
            ->where('user_id', $usuarioId)
            ->whereYear('fecha', $anio)
            ->groupByRaw($expresionMes)
            ->pluck('total', 'numero_mes');

        return collect(range(1, 12))->map(function (int $numeroMes) use ($nombresMeses, $ingresos, $egresos): array {
            $totalIngresos = $this->normalizaMonto($ingresos->get($numeroMes, '0'));
            $totalEgresos = $this->normalizaMonto($egresos->get($numeroMes, '0'));

            return [
                'mes' => $numeroMes,
                'nombre' => $nombresMeses[$numeroMes],
                'ingresos' => $totalIngresos,
                'egresos' => $totalEgresos,
                'balance' => $this->restaMontos($totalIngresos, $totalEgresos),
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function totalesPorCategoria(
        string $tabla,
        int $usuarioId,
        CarbonImmutable $inicio,
        CarbonImmutable $fin,
    ): array {
        return DB::table($tabla)
            ->join('categorias', 'categorias.id', '=', $tabla . '.categoria_id')
            ->select([
                'categorias.id as categoria_id',
                'categorias.nombre',
                DB::raw('SUM(' . $tabla . '.monto) as total'),
            ])
            ->where($tabla . '.user_id', $usuarioId)
            ->where($tabla . '.fecha', '>=', $inicio->toDateString())
            ->where($tabla . '.fecha', '<', $fin->toDateString())
            ->groupBy('categorias.id', 'categorias.nombre')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($fila): array => [
                'categoria_id' => (int) $fila->categoria_id,
                'nombre' => $fila->nombre,
                'monto' => $this->normalizaMonto($fila->total),
            ])
            ->all();
    }

    private function normalizaMonto(mixed $monto): string
    {
        $valor = trim((string) ($monto ?? '0'));
        $negativo = str_starts_with($valor, '-');
        $valor = ltrim($valor, '+-');
        [$entero, $decimal] = array_pad(explode('.', $valor, 2), 2, '0');
        $decimal = str_pad(substr($decimal, 0, 2), 2, '0');
        $resultado = ltrim($entero, '0') ?: '0';

        return ($negativo && $resultado !== '0') ? '-' . $resultado . '.' . $decimal : $resultado . '.' . $decimal;
    }

    private function restaMontos(string $minuendo, string $sustraendo): string
    {
        if (function_exists('bcsub')) {
            return bcsub($minuendo, $sustraendo, 2);
        }

        $centavos = $this->aCentavos($minuendo) - $this->aCentavos($sustraendo);
        $signo = $centavos < 0 ? '-' : '';
        $centavos = abs($centavos);

        return $signo . intdiv($centavos, 100) . '.' . str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }

    private function aCentavos(string $monto): int
    {
        $negativo = str_starts_with($monto, '-');
        $monto = ltrim($monto, '+-');
        [$entero, $decimal] = array_pad(explode('.', $monto, 2), 2, '0');
        $centavos = ((int) $entero * 100) + (int) str_pad(substr($decimal, 0, 2), 2, '0');

        return $negativo ? -$centavos : $centavos;
    }

    private function porcentajeGastado(string $ingresos, string $egresos): string
    {
        if ($this->aCentavos($ingresos) === 0) {
            return '0.00';
        }

        if (function_exists('bcdiv') && function_exists('bcmul')) {
            return bcdiv(bcmul($egresos, '100', 4), $ingresos, 2);
        }

        $numerador = $this->aCentavos($egresos) * 10000;
        $denominador = $this->aCentavos($ingresos);
        $resultado = intdiv($numerador + intdiv($denominador, 2), $denominador);

        return intdiv($resultado, 100) . '.' . str_pad((string) ($resultado % 100), 2, '0', STR_PAD_LEFT);
    }
}
