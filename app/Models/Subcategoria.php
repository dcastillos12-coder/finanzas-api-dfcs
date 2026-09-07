<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['categoria_id', 'nombre'])]
class Subcategoria extends Model
{
    /**
     * Get the category that owns the subcategory.
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /**
     * Get the expenses assigned to the subcategory.
     */
    public function egresos(): HasMany
    {
        return $this->hasMany(Egreso::class);
    }
}
