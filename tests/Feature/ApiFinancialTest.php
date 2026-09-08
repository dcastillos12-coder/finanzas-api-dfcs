<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Egreso;
use App\Models\Ingreso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $ingreso = Ingreso::query()->where('fuente', 'Proyecto freelance')->firstOrFail();

        $this->actingAs($usuario, 'sanctum')
            ->patchJson('/api/ingresos/' . $ingreso->id, ['monto' => '300.50'])
            ->assertOk()
            ->assertJsonPath('data.monto', '300.50');

        $this->actingAs($usuario, 'sanctum')
            ->deleteJson('/api/ingresos/' . $ingreso->id)
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

    private function crearCategoriaSistema(string $nombre, string $tipo): Categoria
    {
        return Categoria::query()->create([
            'user_id' => null,
            'nombre' => $nombre,
            'tipo' => $tipo,
        ]);
    }
}
