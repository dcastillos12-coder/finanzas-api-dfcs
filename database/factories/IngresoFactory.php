<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Ingreso;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ingreso>
 */
class IngresoFactory extends Factory
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
        $monto = sprintf(
            '%d.%02d',
            fake()->numberBetween(800, 4500),
            fake()->numberBetween(0, 99),
        );

        return [
            'user_id' => User::factory(),
            'categoria_id' => Categoria::query()
                ->whereNull('user_id')
                ->where('tipo', 'ingreso')
                ->inRandomOrder()
                ->value('id'),
            'fecha' => fake()->dateTimeBetween('2026-01-01', '2026-07-31')->format('Y-m-d'),
            'fuente' => fake()->randomElement(['Trabajo de medio tiempo', 'Proyecto freelance', 'Apoyo familiar']),
            'monto' => $monto,
            'notas' => fake()->optional()->sentence(),
        ];
    }
}
