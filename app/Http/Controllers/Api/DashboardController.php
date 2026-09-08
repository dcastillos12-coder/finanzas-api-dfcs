<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
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
}
