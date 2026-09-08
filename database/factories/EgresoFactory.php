<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Egreso;
use App\Models\Subcategoria;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Egreso>
 */
class EgresoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * System categories must be seeded before using this factory directly.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categoriaId = Categoria::query()
            ->whereNull('user_id')
            ->where('tipo', 'egreso')
            ->inRandomOrder()
            ->value('id');
        $monto = sprintf(
            '%d.%02d',
            fake()->numberBetween(20, 1200),
            fake()->numberBetween(0, 99),
        );

        return [
            'user_id' => User::factory(),
            'categoria_id' => $categoriaId,
            'subcategoria_id' => $categoriaId === null
                ? null
                : Subcategoria::query()->where('categoria_id', $categoriaId)->inRandomOrder()->value('id'),
            'fecha' => fake()->dateTimeBetween('2026-01-01', '2026-07-31')->format('Y-m-d'),
            'descripcion' => fake()->sentence(3),
            'monto' => $monto,
            'notas' => fake()->optional()->sentence(),
        ];
    }
}
