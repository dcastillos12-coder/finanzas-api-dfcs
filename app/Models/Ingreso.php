<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'categoria_id',
    'fecha',
    'fuente',
    'monto',
    'notas',
])]
class Ingreso extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto' => 'decimal:2',
        ];
    }

    /**
     * Get the user that owns the income.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the category assigned to the income.
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /**
     * Scope a query to incomes within a calendar month.
     */
    public function scopeDelMes(Builder $query, int $anio, int $mes): Builder
    {
        $inicio = CarbonImmutable::create($anio, $mes, 1)->startOfMonth();
        $fin = $inicio->addMonth();

        return $query
            ->where('fecha', '>=', $inicio->toDateString())
            ->where('fecha', '<', $fin->toDateString());
    }
}
