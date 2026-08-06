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
use App\Http\Controllers\Caja\CorteCajaController;
use App\Http\Controllers\Caja\SesionCajaController;
use App\Http\Controllers\Clientes\ClienteController;
use App\Http\Controllers\ConfiguracionTicketController;
use App\Http\Controllers\Dashboard\DashboardController;
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
use App\Http\Controllers\Publico\CatalogoPublicoController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\Users\UserController;
use App\Http\Controllers\Ventas\VentaController;
use App\Http\Middleware\AuthenticateTenant;
use App\Http\Middleware\CheckTenantAccess;
use App\Http\Middleware\InitializeTenancyByHeader;
use App\Http\Middleware\InitializeTenancyBySlugHeader;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

// Rutas centrales — sin tenant

// Healthcheck. Sin controlador (closure inline).
Route::get('/ping', fn () => response()->json(['status' => 'ok', 'version' => 'v2']));

// Verifica si un dominio corresponde a un tenant existente. Controller: CheckDomainController.
Route::get('/auth/check-domain', CheckDomainController::class);

// Lookup, login y registro de cuenta. Controllers: LookupController, LoginController, RegisterController.
Route::prefix('auth')->group(function () {
    Route::post('/lookup', LookupController::class);
    Route::post('/login', LoginController::class);
    Route::post('/register', RegisterController::class);
});

// Diagnóstico de tenancy. Sin controlador (closure inline).
Route::middleware([InitializeTenancyByHeader::class])->get('/debug-tenant', function () {
    return response()->json([
        'tenant' => tenancy()->initialized ? tenant('id') : 'no tenant',
        'db' => DB::connection()->getDatabaseName(),
    ]);
});

// Activación de cuenta por token. Controller: ActivationController.
Route::get('/auth/activate/{token}', [ActivationController::class, 'show']);
Route::post('/auth/activate', [ActivationController::class, 'activate']);

// Reset de contraseña por token. Sin controlador (closure inline).
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

// Rutas tenant protegidas (requieren tenancy + auth + acceso vigente al tenant)
Route::middleware([InitializeTenancyByHeader::class, AuthenticateTenant::class, CheckTenantAccess::class])->group(function () {

    // Sesión del usuario autenticado. Controller: MeController (logout: LogoutController).
    Route::get('/auth/me', [MeController::class, 'show']);
    Route::patch('/auth/me', [MeController::class, 'update']);
    Route::post('/auth/logout', LogoutController::class);

    // Productos y categorías. Controllers: ProductoVarianteController, ProductoController, CategoriaController.
    Route::get('/productos/variantes/inactivas', [ProductoVarianteController::class, 'inactivas']);
    Route::apiResource('productos', ProductoController::class);
    Route::apiResource('categorias', CategoriaController::class);

    // Datos del tenant (negocio, logo). Controller: TenantController.
    Route::get('tenant', [TenantController::class, 'show']);
    Route::patch('tenant', [TenantController::class, 'update']);
    Route::post('tenant/logo', [TenantController::class, 'uploadLogo']);

    // Cajas. Controller: CajaController.
    Route::apiResource('cajas', CajaController::class);
    Route::get('cajas-abiertas', [CajaController::class, 'abiertas']);

    // Configuración de tickets. Controller: ConfiguracionTicketController.
    Route::get('configuracion-tickets', [ConfiguracionTicketController::class, 'show']);
    Route::patch('configuracion-tickets', [ConfiguracionTicketController::class, 'update']);

    // Ventas y tickets. Controllers: VentaController, TicketController.
    Route::post('ventas', [VentaController::class, 'store']);
    Route::get('ventas/del-dia', [VentaController::class, 'delDia']);
    Route::get('ventas/de-la-sesion', [VentaController::class, 'deLaSesion']);
    Route::post('ventas/verificar-empleado', [VentaController::class, 'verificarEmpleado']);
    Route::post('ventas/autorizar-descuento', [VentaController::class, 'autorizarDescuento']);
    Route::get('ventas/todas', [VentaController::class, 'todas']);
    Route::get('ventas/{id}', [VentaController::class, 'show']);
    Route::get('ventas/{id}/ticket', [TicketController::class, 'generar']);
    Route::post('ventas/{id}/ticket/email', [TicketController::class, 'enviarPorEmail']);
    Route::post('ventas/{id}/cancelar', [VentaController::class, 'cancelar']);
    Route::get('ventas/{id}/ticket-cancelacion', [TicketController::class, 'cancelacion']);

    // Métodos de pago y sucursales. Controllers: MetodoPagoController, SucursalController.
    Route::apiResource('metodos-pago', MetodoPagoController::class);
    Route::apiResource('sucursales', SucursalController::class);

    // Sesiones de caja y cortes. Controllers: SesionCajaController, CorteCajaController.
    Route::get('cajas/{cajaId}/sesion-activa', [SesionCajaController::class, 'activa']);
    Route::post('cajas/{cajaId}/abrir', [SesionCajaController::class, 'abrir']);
    Route::post('sesiones-caja/{id}/cerrar', [SesionCajaController::class, 'cerrar']);
    Route::post('sesiones-caja/{id}/corte-x', [SesionCajaController::class, 'corteX']);
    Route::post('sesiones-caja/{id}/corte-z', [SesionCajaController::class, 'corteZ']);
    Route::get('sesiones-caja/{id}/preview-cierre', [SesionCajaController::class, 'previewCierre']);
    Route::get('cortes-caja/{id}/pdf', [CorteCajaController::class, 'pdf']);

    // Cuenta. Controller: AccountController.
    Route::get('/account', [AccountController::class, 'show']);

    // Listas de precios. Controller: ListaPrecioController.
    Route::apiResource('listas-precios', ListaPrecioController::class);

    // Atributos de producto y sus valores. Controller: AtributoController.
    Route::apiResource('atributos', AtributoController::class);
    Route::post('atributos/{id}/valores', [AtributoController::class, 'agregarValor']);
    Route::delete('atributos-valores/{id}', [AtributoController::class, 'eliminarValor']);

    // Usuarios. Controller: UserController.
    Route::patch('/usuarios/me/pin', [UserController::class, 'updatePin']);
    Route::patch('/usuarios/me/password', [UserController::class, 'updatePassword']);
    Route::post('usuarios/{id}/enviar-reset-password', [UserController::class, 'enviarResetPassword']);
    Route::patch('usuarios/{id}/toggle-activo', [UserController::class, 'toggleActivo']);
    Route::apiResource('usuarios', UserController::class);

    // Dashboard. Fast acctions y stats básicos
    Route::get('dashboard/resumen-mes', [DashboardController::class, 'resumenMes']);

    // Variantes de producto (anidadas bajo producto). Controller: ProductoVarianteController.
    Route::prefix('productos/{productoId}/variantes')->group(function () {
        Route::get('/{id}', [ProductoVarianteController::class, 'show']);
        Route::post('/', [ProductoVarianteController::class, 'store']);
        Route::put('/{id}', [ProductoVarianteController::class, 'update']);
        Route::delete('/{id}', [ProductoVarianteController::class, 'destroy']);
        Route::patch('/{id}/reactivar', [ProductoVarianteController::class, 'reactivar']);
    });

    // Imágenes por variante (anidadas bajo producto + variante). Controller: ProductoImagenController.
    Route::prefix('productos/{productoId}/variantes/{varianteId}/imagenes')->group(function () {
        Route::post('/', [ProductoImagenController::class, 'store']);
        Route::delete('/{id}', [ProductoImagenController::class, 'destroy']);
        Route::patch('/{id}/primaria', [ProductoImagenController::class, 'setPrimaria']);
    });

    // Inventario: stock y movimientos (kardex). Controller: InventarioController.
    Route::prefix('inventario')->group(function () {
        Route::get('sucursales/{sucursal}/stock', [InventarioController::class, 'stockPorSucursal']);
        Route::get('sucursales/{sucursal}/movimientos', [InventarioController::class, 'movimientosPorSucursal']);
        Route::get('variantes/{variante}/movimientos', [InventarioController::class, 'movimientosPorVariante']);
        Route::post('movimientos', [InventarioController::class, 'registrarMovimiento']);
        Route::post('transferencias', [InventarioController::class, 'transferir']);
        Route::get('configuracion/stock-minimo', [InventarioController::class, 'obtenerStockMinimoGlobal']);
        Route::post('configuracion/stock-minimo', [InventarioController::class, 'actualizarStockMinimoGlobal']);
        Route::get('movimientos', [InventarioController::class, 'movimientos']);
    });

    // Gastos. Controllers: GastoController, CategoriaGastoController.
    Route::apiResource('gastos', GastoController::class);
    Route::apiResource('categorias-gasto', CategoriaGastoController::class);

    // Clinetes. Controller: ClienteController.
    Route::apiResource('clientes', ClienteController::class);
});

// Catálogo público — resuelto por header X-Tenant-Slug (Flutter web o app nativa), sin auth
Route::middleware([InitializeTenancyBySlugHeader::class])
    ->group(function () {
        Route::get('catalogo', [CatalogoPublicoController::class, 'index']);
        Route::get('catalogo/negocio', [CatalogoPublicoController::class, 'negocio']);
    });
