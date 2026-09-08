<?php

use App\Http\Controllers\Api\EgresoController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('egresos', EgresoController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);
});
