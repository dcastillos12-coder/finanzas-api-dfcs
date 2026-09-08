<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Egreso;
use App\Models\Ingreso;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class DatosPruebaSeeder extends Seeder
{
    /**
     * Seed dashboard data for two test users.
     */
    public function run(): void
    {
        $this->call(CategoriasSistemaSeeder::class);

        $usuarios = [
            [
                'name' => 'Ana López',
                'email' => 'ana.prueba@example.com',
                'ingreso_categoria' => 'Empleo',
                'fuente' => 'Trabajo de medio tiempo',
                'ingresos' => [1 => '3600.00', 2 => '3600.00', 3 => '3700.00', 4 => '3700.00', 5 => '3800.00', 7 => '3900.00'],
                'gastos' => [
                    ['Vivienda', 'Internet', 'Pago de internet', 280],
                    ['Alimentación', 'Supermercado', 'Compra de supermercado', 650],
                    ['Alimentación', 'Restaurante', 'Almuerzo en la universidad', 110],
                    ['Transporte', 'Bus', 'Recargas de transporte', 180],
                    ['Educación', 'Universidad', 'Cuota universitaria', 900],
                    ['Ocio / Entretenimiento', 'Suscripciones', 'Suscripción de música', 55],
                    ['Deporte', 'Gimnasio', 'Mensualidad de gimnasio', 175],
                    ['Salud', 'Medicamentos', 'Medicamentos básicos', 90],
                    ['Otro Egreso', null, 'Compra no clasificada', 100],
                ],
            ],
            [
                'name' => 'Carlos Méndez',
                'email' => 'carlos.prueba@example.com',
                'ingreso_categoria' => 'Freelance / Proyecto',
                'fuente' => 'Diseño de proyectos freelance',
                'ingresos' => [1 => '3000.00', 2 => '3200.00', 3 => '2900.00', 4 => '3400.00', 5 => '3100.00', 7 => '3500.00'],
                'gastos' => [
                    ['Vivienda', 'Agua', 'Pago de agua', 95],
                    ['Vivienda', 'Luz', 'Pago de energía eléctrica', 210],
                    ['Alimentación', 'Supermercado', 'Compra de supermercado', 580],
                    ['Transporte', 'Gasolina', 'Gasolina para motocicleta', 300],
                    ['Educación', 'Cursos', 'Curso de programación', 250],
                    ['Ocio / Entretenimiento', 'Cine', 'Salida al cine', 85],
                    ['Deporte', 'Equipo deportivo', 'Accesorio deportivo', 120],
                    ['Imprevistos', 'Reparaciones', 'Reparación de teléfono', 160],
                    ['Salud', 'Consulta', 'Consulta médica', 150],
                ],
            ],
        ];

        $categoriasIngreso = Categoria::query()
            ->whereNull('user_id')
            ->where('tipo', 'ingreso')
            ->get()
            ->keyBy('nombre');

        $categoriasEgreso = Categoria::query()
            ->whereNull('user_id')
            ->where('tipo', 'egreso')
            ->with('subcategorias')
            ->get()
            ->keyBy('nombre');

        foreach ($usuarios as $datosUsuario) {
            $usuario = User::query()->firstOrCreate(
                ['email' => $datosUsuario['email']],
                [
                    'name' => $datosUsuario['name'],
                    'email_verified_at' => now(),
                    'password' => Hash::make('prueba2026'),
                ],
            );

            $this->crearIngresos($usuario, $datosUsuario, $categoriasIngreso);
            $this->crearEgresos($usuario, $datosUsuario['gastos'], $categoriasEgreso);
        }
    }

    /**
     * Create or update one income in every month except June.
     *
     * @param array<string, mixed> $datosUsuario
     * @param Collection<string, Categoria> $categoriasIngreso
     */
    private function crearIngresos(User $usuario, array $datosUsuario, Collection $categoriasIngreso): void
    {
        $categoria = $categoriasIngreso->get($datosUsuario['ingreso_categoria']);

        foreach ($datosUsuario['ingresos'] as $mes => $monto) {
            $fecha = sprintf('2026-%02d-05', $mes);
            $ingreso = Ingreso::factory()->make([
                'user_id' => $usuario->id,
                'categoria_id' => $categoria->id,
                'fecha' => $fecha,
                'fuente' => $datosUsuario['fuente'],
                'monto' => $monto,
                'notas' => 'Dato de prueba para el dashboard.',
            ]);

            Ingreso::query()->updateOrCreate(
                ['user_id' => $usuario->id, 'fecha' => $fecha, 'fuente' => $datosUsuario['fuente']],
                $ingreso->getAttributes(),
            );
        }
    }

    /**
     * Create or update eight expenses per populated month, skipping June.
     *
     * @param array<int, array{0: string, 1: ?string, 2: string, 3: int}> $gastos
     * @param Collection<string, Categoria> $categoriasEgreso
     */
    private function crearEgresos(User $usuario, array $gastos, Collection $categoriasEgreso): void
    {
        foreach ([1, 2, 3, 4, 5, 7] as $mes) {
            foreach ($gastos as $indice => [$nombreCategoria, $nombreSubcategoria, $descripcion, $montoBase]) {
                $categoria = $categoriasEgreso->get($nombreCategoria);
                $subcategoria = $categoria->subcategorias->firstWhere('nombre', $nombreSubcategoria);
                $fecha = sprintf('2026-%02d-%02d', $mes, 7 + $indice);
                $monto = number_format($montoBase + (($mes - 1) * 5), 2, '.', '');
                $egreso = Egreso::factory()->make([
                    'user_id' => $usuario->id,
                    'categoria_id' => $categoria->id,
                    'subcategoria_id' => $subcategoria?->id,
                    'fecha' => $fecha,
                    'descripcion' => $descripcion,
                    'monto' => $monto,
                    'notas' => 'Dato de prueba para el dashboard.',
                ]);

                Egreso::query()->updateOrCreate(
                    ['user_id' => $usuario->id, 'fecha' => $fecha, 'descripcion' => $descripcion],
                    $egreso->getAttributes(),
                );
            }
        }
    }
}
