<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'nombre', 'tipo'])]
class Categoria extends Model
{
    /**
     * Get the user that owns the category, when it is not a system category.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subcategories that belong to the category.
     */
    public function subcategorias(): HasMany
    {
        return $this->hasMany(Subcategoria::class);
    }

    /**
     * Get the incomes assigned to the category.
     */
    public function ingresos(): HasMany
    {
        return $this->hasMany(Ingreso::class);
    }

    /**
     * Get the expenses assigned to the category.
     */
    public function egresos(): HasMany
    {
        return $this->hasMany(Egreso::class);
    }
}
