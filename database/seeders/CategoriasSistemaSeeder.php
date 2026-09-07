<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Subcategoria;
use Illuminate\Database\Seeder;

class CategoriasSistemaSeeder extends Seeder
{
    /**
     * Seed the system categories and their subcategories.
     */
    public function run(): void
    {
        $categoriasIngreso = [
            'Empleo',
            'Freelance / Proyecto',
            'Negocio Propio',
            'Inversión / Dividendos',
            'Bono / Extra',
            'Otro Ingreso',
        ];

        foreach ($categoriasIngreso as $nombre) {
            Categoria::query()->firstOrCreate([
                'user_id' => null,
                'nombre' => $nombre,
                'tipo' => 'ingreso',
            ]);
        }

        $categoriasEgreso = [
            'Vivienda' => ['Alquiler', 'Agua', 'Luz', 'Internet'],
            'Educación' => ['Universidad', 'Cursos', 'Libros'],
            'Alimentación' => ['Supermercado', 'Restaurante', 'Almuerzo'],
            'Transporte' => ['Gasolina', 'Bus', 'Taxi / Uber', 'Parqueo'],
            'Salud' => ['Consulta', 'Medicamentos', 'Laboratorio'],
            'Ocio / Entretenimiento' => ['Suscripciones', 'Cine', 'Salidas'],
            'Deporte' => ['Gimnasio', 'Equipo deportivo'],
            'Imprevistos' => ['Emergencias', 'Reparaciones'],
            'Otro Egreso' => [],
        ];

        foreach ($categoriasEgreso as $nombreCategoria => $nombresSubcategorias) {
            $categoria = Categoria::query()->firstOrCreate([
                'user_id' => null,
                'nombre' => $nombreCategoria,
                'tipo' => 'egreso',
            ]);

            foreach ($nombresSubcategorias as $nombreSubcategoria) {
                Subcategoria::query()->firstOrCreate([
                    'categoria_id' => $categoria->id,
                    'nombre' => $nombreSubcategoria,
                ]);
            }
        }
    }
}
