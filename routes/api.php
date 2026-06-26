<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\CheckDomainController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\LookupController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Caja\CajaController;
use App\Http\Controllers\Caja\SesionCajaController;
use App\Http\Controllers\ConfiguracionTicketController;
use App\Http\Controllers\Gastos\CategoriaGastoController;
use App\Http\Controllers\Gastos\GastoController;
use App\Http\Controllers\Inventario\InventarioController;
use App\Http\Controllers\MetodoPagoController;
use App\Http\Controllers\Productos\AtributoController;
use App\Http\Controllers\Productos\CategoriaController;
use App\Http\Controllers\Productos\ListaPrecioController;
use App\Http\Controllers\Productos\ProductoController;
use App\Http\Controllers\Productos\ProductoImagenController;
use App\Http\Controllers\Productos\ProductoVarianteController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\Users\UserController;
use App\Http\Controllers\Ventas\VentaController;
use App\Http\Middleware\AuthenticateTenant;
use App\Http\Middleware\CheckTenantAccess;
use App\Http\Middleware\InitializeTenancyByHeader;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

// Activación — rutas públicas
Route::get('/auth/activate/{token}', [ActivationController::class, 'show']);
Route::post('/auth/activate', [ActivationController::class, 'activate']);

Route::middleware([InitializeTenancyByHeader::class])->post('/reset-password', function (Request $request) {
    $request->validate([
        'token' => ['required'],
        'email' => ['required', 'email'],
        'password' => ['required', 'min:8', 'confirmed'],
    ]);

    $record = DB::connection('tenant')->table('password_reset_tokens')
        ->where('email', $request->email)
        ->first();

    if (! $record || $record->token !== $request->token) {
        return response()->json(['message' => 'El link es inválido o ya fue usado.'], 422);
    }

    if (now()->diffInMinutes($record->created_at) > 60) {
        return response()->json(['message' => 'El link ha expirado.'], 422);
    }

    $user = User::where('email', $request->email)->firstOrFail();
    $user->update(['password' => Hash::make($request->password)]);

    DB::connection('tenant')->table('password_reset_tokens')
        ->where('email', $request->email)
        ->delete();

    return response()->json(['message' => 'Contraseña restablecida correctamente.']);
});

// Rutas tenant protegidas
Route::middleware([InitializeTenancyByHeader::class, AuthenticateTenant::class, CheckTenantAccess::class])->group(function () {
    Route::get('/auth/me', [MeController::class, 'show']);
    Route::patch('/auth/me', [MeController::class, 'update']);
    Route::post('/auth/logout', LogoutController::class);

    Route::get('/productos/variantes/inactivas', [ProductoVarianteController::class, 'inactivas']);

    Route::apiResource('productos', ProductoController::class);
    Route::apiResource('categorias', CategoriaController::class);

    Route::get('tenant', [TenantController::class, 'show']);
    Route::patch('tenant', [TenantController::class, 'update']);
    Route::post('tenant/logo', [TenantController::class, 'uploadLogo']);

    Route::apiResource('cajas', CajaController::class);
    Route::get('cajas-abiertas', [CajaController::class, 'abiertas']);
    Route::get('configuracion-tickets', [ConfiguracionTicketController::class, 'show']);
    Route::patch('configuracion-tickets', [ConfiguracionTicketController::class, 'update']);

    Route::post('ventas', [VentaController::class, 'store']);
    Route::post('ventas/verificar-empleado', [VentaController::class, 'verificarEmpleado']);

    Route::apiResource('metodos-pago', MetodoPagoController::class);
    Route::apiResource('sucursales', SucursalController::class);

    Route::get('cajas/{cajaId}/sesion-activa', [SesionCajaController::class, 'activa']);
    Route::post('cajas/{cajaId}/abrir', [SesionCajaController::class, 'abrir']);
    Route::post('sesiones-caja/{id}/cerrar', [SesionCajaController::class, 'cerrar']);
    Route::post('sesiones-caja/{id}/corte-x', [SesionCajaController::class, 'corteX']);

    Route::get('/account', [AccountController::class, 'show']);

    Route::apiResource('listas-precios', ListaPrecioController::class);
    Route::apiResource('atributos', AtributoController::class);
    Route::post('atributos/{id}/valores', [AtributoController::class, 'agregarValor']);
    Route::delete('atributos-valores/{id}', [AtributoController::class, 'eliminarValor']);

    Route::patch('/usuarios/me/pin', [UserController::class, 'updatePin']);
    Route::patch('/usuarios/me/password', [UserController::class, 'updatePassword']);
    Route::post('usuarios/{id}/enviar-reset-password', [UserController::class, 'enviarResetPassword']);
    Route::patch('usuarios/{id}/toggle-activo', [UserController::class, 'toggleActivo']);

    Route::apiResource('usuarios', UserController::class);

    Route::prefix('productos/{productoId}/variantes')->group(function () {
        Route::get('/{id}', [ProductoVarianteController::class, 'show']);
        Route::post('/', [ProductoVarianteController::class, 'store']);
        Route::put('/{id}', [ProductoVarianteController::class, 'update']);
        Route::delete('/{id}', [ProductoVarianteController::class, 'destroy']);
        Route::patch('/{id}/reactivar', [ProductoVarianteController::class, 'reactivar']);
    });

    Route::prefix('productos/{productoId}/variantes/{varianteId}/imagenes')->group(function () {
        Route::post('/', [ProductoImagenController::class, 'store']);
        Route::delete('/{id}', [ProductoImagenController::class, 'destroy']);
        Route::patch('/{id}/primaria', [ProductoImagenController::class, 'setPrimaria']);
    });

    Route::prefix('inventario')->group(function () {
        Route::get('sucursales/{sucursal}/stock', [InventarioController::class, 'stockPorSucursal']);
        Route::get('sucursales/{sucursal}/movimientos', [InventarioController::class, 'movimientosPorSucursal']);
        Route::get('variantes/{variante}/movimientos', [InventarioController::class, 'movimientosPorVariante']);
        Route::post('movimientos', [InventarioController::class, 'registrarMovimiento']);
        Route::post('transferencias', [InventarioController::class, 'transferir']);
        Route::get('configuracion/stock-minimo', [InventarioController::class, 'obtenerStockMinimoGlobal']);
        Route::post('configuracion/stock-minimo', [InventarioController::class, 'actualizarStockMinimoGlobal']);
    });

    Route::apiResource('gastos', GastoController::class);
    Route::apiResource('categorias-gasto', CategoriaGastoController::class);

});
