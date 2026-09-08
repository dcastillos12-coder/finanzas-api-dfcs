<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Egreso;
use App\Models\Ingreso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiFinancialTest extends TestCase
{
    use RefreshDatabase;

    public function test_protected_financial_endpoints_require_authentication(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
        $this->getJson('/api/ingresos')->assertUnauthorized();
        $this->getJson('/api/categorias')->assertUnauthorized();
    }

    public function test_income_api_is_scoped_to_the_authenticated_user_and_supports_crud(): void
    {
        $categoria = $this->crearCategoriaSistema('Salario', 'ingreso');
        $usuario = User::factory()->create();
        $otroUsuario = User::factory()->create();

        Ingreso::factory()->create([
            'user_id' => $usuario->id,
            'categoria_id' => $categoria->id,
            'fecha' => '2026-01-05',
            'monto' => '1000.00',
        ]);
        Ingreso::factory()->create([
            'user_id' => $otroUsuario->id,
            'categoria_id' => $categoria->id,
            'fecha' => '2026-01-05',
            'monto' => '9000.00',
        ]);

        $this->actingAs($usuario, 'sanctum')
            ->getJson('/api/ingresos?anio=2026&mes=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.monto', '1000.00');

        $respuesta = $this->actingAs($usuario, 'sanctum')->postJson('/api/ingresos', [
            'categoria_id' => $categoria->id,
            'fecha' => '2026-02-05',
            'fuente' => 'Proyecto freelance',
            'monto' => '250.50',
        ]);

        $respuesta->assertCreated()->assertJsonPath('data.monto', '250.50');
        $ingresoId = $respuesta->json('data.id');

        $this->actingAs($usuario, 'sanctum')
            ->patchJson('/api/ingresos/' . $ingresoId, ['monto' => '300.50'])
            ->assertOk()
            ->assertJsonPath('data.monto', '300.50');

        $this->actingAs($usuario, 'sanctum')
            ->deleteJson('/api/ingresos/' . $ingresoId)
            ->assertNoContent();
    }

    public function test_category_api_exposes_system_and_own_categories_but_only_own_are_mutable(): void
    {
        $sistema = $this->crearCategoriaSistema('Empleo', 'ingreso');
        $usuario = User::factory()->create();
        $propia = Categoria::query()->create([
            'user_id' => $usuario->id,
            'nombre' => 'Ingresos extras',
            'tipo' => 'ingreso',
        ]);

        $this->actingAs($usuario, 'sanctum')
            ->getJson('/api/categorias?tipo=ingreso')
            ->assertOk()
            ->assertJsonFragment(['id' => $sistema->id, 'nombre' => 'Empleo'])
            ->assertJsonFragment(['id' => $propia->id, 'nombre' => 'Ingresos extras']);

        $this->actingAs($usuario, 'sanctum')
            ->patchJson('/api/categorias/' . $sistema->id, ['nombre' => 'No permitido'])
            ->assertNotFound();

        $respuesta = $this->actingAs($usuario, 'sanctum')->postJson(
            '/api/categorias/' . $propia->id . '/subcategorias',
            ['nombre' => 'Bonificaciones'],
        );

        $respuesta->assertCreated()->assertJsonPath('data.nombre', 'Bonificaciones');
        $this->assertDatabaseHas('subcategorias', [
            'categoria_id' => $propia->id,
            'nombre' => 'Bonificaciones',
        ]);

        $this->actingAs($usuario, 'sanctum')
            ->getJson('/api/categorias/' . $propia->id . '/subcategorias')
            ->assertOk()
            ->assertJsonPath('data.0.nombre', 'Bonificaciones');
    }

    public function test_dashboard_aggregates_only_the_authenticated_user_and_derives_months_from_dates(): void
    {
        $categoriaIngreso = $this->crearCategoriaSistema('Empleo', 'ingreso');
        $categoriaEgreso = $this->crearCategoriaSistema('Vivienda', 'egreso');
        $usuario = User::factory()->create();
        $otroUsuario = User::factory()->create();

        Ingreso::factory()->create([
            'user_id' => $usuario->id,
            'categoria_id' => $categoriaIngreso->id,
            'fecha' => '2026-01-05',
            'monto' => '1000.00',
        ]);
        Egreso::factory()->create([
            'user_id' => $usuario->id,
            'categoria_id' => $categoriaEgreso->id,
            'fecha' => '2026-01-10',
            'monto' => '350.25',
        ]);
        Ingreso::factory()->create([
            'user_id' => $otroUsuario->id,
            'categoria_id' => $categoriaIngreso->id,
            'fecha' => '2026-01-05',
            'monto' => '9999.99',
        ]);

        $this->actingAs($usuario, 'sanctum')
            ->getJson('/api/dashboard?anio=2026&mes=1')
            ->assertOk()
            ->assertJsonPath('data.periodo.anio', 2026)
            ->assertJsonPath('data.periodo.mes', 1)
            ->assertJsonPath('data.totales.ingresos', '1000.00')
            ->assertJsonPath('data.totales.egresos', '350.25')
            ->assertJsonPath('data.totales.balance', '649.75')
            ->assertJsonPath('data.resumen_mensual.0.ingresos', '1000.00')
            ->assertJsonPath('data.resumen_mensual.0.egresos', '350.25')
            ->assertJsonPath('data.egresos_por_categoria.0.nombre', 'Vivienda');
    }

    public function test_dashboard_resumen_uses_one_query_and_excludes_months_after_the_selected_month(): void
    {
        $categoriaIngreso = $this->crearCategoriaSistema('Empleo', 'ingreso');
        $categoriaEgreso = $this->crearCategoriaSistema('Vivienda', 'egreso');
        $usuario = User::factory()->create();

        Ingreso::factory()->create([
            'user_id' => $usuario->id,
            'categoria_id' => $categoriaIngreso->id,
            'fecha' => '2026-01-05',
            'monto' => '1000.00',
        ]);
        Egreso::factory()->create([
            'user_id' => $usuario->id,
            'categoria_id' => $categoriaEgreso->id,
            'fecha' => '2026-01-10',
            'monto' => '250.00',
        ]);
        Ingreso::factory()->create([
            'user_id' => $usuario->id,
            'categoria_id' => $categoriaIngreso->id,
            'fecha' => '2026-03-05',
            'monto' => '9000.00',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($usuario, 'sanctum')
            ->getJson('/api/dashboard/resumen?anio=2026&mes=2')
            ->assertOk()
            ->assertJsonPath('data.ingresos_mes', '0.00')
            ->assertJsonPath('data.egresos_mes', '0.00')
            ->assertJsonPath('data.balance_mes', '0.00')
            ->assertJsonPath('data.ingresos_acumulados', '1000.00')
            ->assertJsonPath('data.egresos_acumulados', '250.00')
            ->assertJsonPath('data.balance_acumulado', '750.00')
            ->assertJsonPath('data.porcentaje_gastado', '0.00');

        $this->assertCount(1, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_dashboard_resumen_returns_zero_percentage_when_monthly_income_is_zero(): void
    {
        $categoriaEgreso = $this->crearCategoriaSistema('Vivienda', 'egreso');
        $usuario = User::factory()->create();

        Egreso::factory()->create([
            'user_id' => $usuario->id,
            'categoria_id' => $categoriaEgreso->id,
            'fecha' => '2026-02-10',
            'monto' => '250.00',
        ]);

        $this->actingAs($usuario, 'sanctum')
            ->getJson('/api/dashboard/resumen?anio=2026&mes=2')
            ->assertOk()
            ->assertJsonPath('data.ingresos_mes', '0.00')
            ->assertJsonPath('data.egresos_mes', '250.00')
            ->assertJsonPath('data.porcentaje_gastado', '0.00');
    }

    public function test_dashboard_groups_monthly_expenses_by_category_in_descending_order_for_the_authenticated_user(): void
    {
        $categoriaMayor = $this->crearCategoriaSistema('Vivienda', 'egreso');
        $categoriaMenor = $this->crearCategoriaSistema('Transporte', 'egreso');
        $categoriaSinEgresos = $this->crearCategoriaSistema('Salud', 'egreso');
        $usuario = User::factory()->create();
        $otroUsuario = User::factory()->create();

        Egreso::factory()->create([
            'user_id' => $usuario->id,
            'categoria_id' => $categoriaMayor->id,
            'fecha' => '2026-02-03',
            'monto' => '700.00',
        ]);
        Egreso::factory()->create([
            'user_id' => $usuario->id,
            'categoria_id' => $categoriaMenor->id,
            'fecha' => '2026-02-04',
            'monto' => '150.25',
        ]);
        Egreso::factory()->create([
            'user_id' => $otroUsuario->id,
            'categoria_id' => $categoriaMayor->id,
            'fecha' => '2026-02-05',
            'monto' => '9000.00',
        ]);

        $this->actingAs($usuario, 'sanctum')
            ->getJson('/api/dashboard/egresos-por-categoria?anio=2026&mes=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.categoria_id', $categoriaMayor->id)
            ->assertJsonPath('data.0.nombre', 'Vivienda')
            ->assertJsonPath('data.0.total', '700.00')
            ->assertJsonPath('data.1.categoria_id', $categoriaMenor->id)
            ->assertJsonPath('data.1.total', '150.25')
            ->assertJsonMissing(['categoria_id' => $categoriaSinEgresos->id]);
    }

    public function test_annual_dashboard_always_returns_all_twelve_months_with_zeroes_for_empty_months(): void
    {
        $categoriaIngreso = $this->crearCategoriaSistema('Empleo', 'ingreso');
        $categoriaEgreso = $this->crearCategoriaSistema('Vivienda', 'egreso');
        $usuario = User::factory()->create();

        Ingreso::factory()->create([
            'user_id' => $usuario->id,
            'categoria_id' => $categoriaIngreso->id,
            'fecha' => '2026-01-05',
            'monto' => '1000.00',
        ]);
        Egreso::factory()->create([
            'user_id' => $usuario->id,
            'categoria_id' => $categoriaEgreso->id,
            'fecha' => '2026-03-10',
            'monto' => '250.00',
        ]);

        $this->actingAs($usuario, 'sanctum')
            ->getJson('/api/dashboard/resumen-anual?anio=2026')
            ->assertOk()
            ->assertJsonCount(12, 'data')
            ->assertJsonPath('data.0.mes', 1)
            ->assertJsonPath('data.0.ingresos', '1000.00')
            ->assertJsonPath('data.0.egresos', '0.00')
            ->assertJsonPath('data.1.mes', 2)
            ->assertJsonPath('data.1.ingresos', '0.00')
            ->assertJsonPath('data.1.egresos', '0.00')
            ->assertJsonPath('data.1.balance', '0.00')
            ->assertJsonPath('data.2.mes', 3)
            ->assertJsonPath('data.2.egresos', '250.00')
            ->assertJsonPath('data.11.mes', 12)
            ->assertJsonPath('data.11.ingresos', '0.00')
            ->assertJsonPath('data.11.egresos', '0.00');
    }

    private function crearCategoriaSistema(string $nombre, string $tipo): Categoria
    {
        return Categoria::query()->create([
            'user_id' => null,
            'nombre' => $nombre,
            'tipo' => $tipo,
        ]);
    }
}
