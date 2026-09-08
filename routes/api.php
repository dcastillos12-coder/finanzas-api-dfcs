<?php

use App\Http\Controllers\Api\EgresoController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\IngresoController;
use App\Http\Controllers\Api\CategoriaController;
use App\Http\Controllers\Api\SubcategoriaController;
use App\Http\Controllers\Api\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('dashboard/resumen', [DashboardController::class, 'resumen']);
    Route::get('dashboard/egresos-por-categoria', [DashboardController::class, 'egresosPorCategoria']);
    Route::get('dashboard/resumen-anual', [DashboardController::class, 'resumenAnual']);
    Route::get('dashboard', DashboardController::class);

    Route::apiResource('egresos', EgresoController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);

    Route::apiResource('ingresos', IngresoController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);

    Route::apiResource('categorias', CategoriaController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);

    Route::get('categorias/{categoria}/subcategorias', [SubcategoriaController::class, 'index']);
    Route::post('categorias/{categoria}/subcategorias', [SubcategoriaController::class, 'store']);
    Route::apiResource('subcategorias', SubcategoriaController::class)
        ->only(['show', 'update', 'destroy']);
});
