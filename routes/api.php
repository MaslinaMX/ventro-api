<?php

use App\Http\Controllers\Auth\CheckDomainController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\LookupController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Productos\AtributoController;
use App\Http\Controllers\Productos\CategoriaController;
use App\Http\Controllers\Productos\ListaPrecioController;
use App\Http\Controllers\Productos\ProductoController;
use App\Http\Controllers\Productos\ProductoImagenController;
use App\Http\Controllers\Productos\ProductoVarianteController;
use App\Http\Middleware\AuthenticateTenant;
use App\Http\Middleware\InitializeTenancyByHeader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Rutas centrales — sin tenant
Route::get('/ping', fn () => response()->json(['status' => 'ok', 'version' => 'v2']));
Route::get('/auth/check-domain', CheckDomainController::class);

Route::prefix('auth')->group(function () {
    Route::post('/lookup', LookupController::class);
    Route::post('/login', LoginController::class);
    Route::post('/register', RegisterController::class);
});

Route::middleware([InitializeTenancyByHeader::class])->get('/debug-tenant', function () {
    return response()->json([
        'tenant' => tenancy()->initialized ? tenant('id') : 'no tenant',
        'db' => DB::connection()->getDatabaseName(),
    ]);
});

// Rutas tenant protegidas
Route::middleware([InitializeTenancyByHeader::class, AuthenticateTenant::class])->group(function () {
    Route::get('/auth/me', [MeController::class, 'show']);
    Route::patch('/auth/me', [MeController::class, 'update']);
    Route::post('/auth/logout', LogoutController::class);
    Route::apiResource('productos', ProductoController::class);
    Route::apiResource('categorias', CategoriaController::class);

    Route::apiResource('listas-precios', ListaPrecioController::class);
    Route::apiResource('atributos', AtributoController::class);
    Route::post('atributos/{id}/valores', [AtributoController::class, 'agregarValor']);
    Route::delete('atributos-valores/{id}', [AtributoController::class, 'eliminarValor']);

    Route::prefix('productos/{productoId}/variantes')->group(function () {
        Route::post('/', [ProductoVarianteController::class, 'store']);
        Route::put('/{id}', [ProductoVarianteController::class, 'update']);
        Route::delete('/{id}', [ProductoVarianteController::class, 'destroy']);
    });

    Route::prefix('productos/{productoId}/variantes/{varianteId}/imagenes')->group(function () {
        Route::post('/', [ProductoImagenController::class, 'store']);
        Route::delete('/{id}', [ProductoImagenController::class, 'destroy']);
        Route::patch('/{id}/primaria', [ProductoImagenController::class, 'setPrimaria']);
    });

});
