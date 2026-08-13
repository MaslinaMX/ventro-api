<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Concerns\VerificaEmpleadoPorPin;
use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\Devolucion;
use App\Models\MetodoPago;
use App\Models\MovimientoInventario;
use App\Models\ProductoVariante;
use App\Models\SesionCaja;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Models\VentaPago;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VentaController extends Controller
{
    use VerificaEmpleadoPorPin;

    public function store(Request $request)
    {
        $empleado = $this->verificarEmpleadoPin($request);

        if (! $empleado->hasPermission('ventas.crear')) {
            return response()->json(['message' => 'Este empleado no tiene permiso para vender.'], 403);
        }

        $data = $request->validate([
            'caja_id' => 'required|integer|exists:cajas,id',
            'cliente_id' => 'nullable|integer',
            'descuento' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.producto_variante_id' => 'required|integer|exists:producto_variantes,id',
            'items.*.cantidad' => 'required|integer|min:1',
            'items.*.precio_unitario' => 'required|numeric|min:0',
            'items.*.precio_lista' => 'required|numeric|min:0',
            'items.*.descuento_linea' => 'nullable|numeric|min:0',
            'pagos' => 'required|array|min:1',
            'pagos.*.metodo_pago_id' => 'required|integer|exists:metodos_pago,id',
            'pagos.*.monto' => 'required|numeric|min:0.01',
            'pagos.*.recibido' => 'nullable|numeric|min:0',
            'pagos.*.referencia' => 'nullable|string',
        ]);

        $caja = Caja::findOrFail($data['caja_id']);

        $sesion = SesionCaja::where('caja_id', $caja->id)
            ->where('estado', 'abierta')
            ->first();

        if (! $sesion) {
            return response()->json(['message' => 'Esta caja no tiene una sesión abierta.'], 422);
        }

        // Validar métodos de pago que requieren referencia
        $metodosPago = MetodoPago::whereIn('id', collect($data['pagos'])->pluck('metodo_pago_id'))
            ->get()
            ->keyBy('id');

        foreach ($data['pagos'] as $pago) {
            $metodo = $metodosPago->get($pago['metodo_pago_id']);
            if ($metodo && $metodo->requiere_referencia && empty($pago['referencia'])) {
                return response()->json([
                    'message' => "El método \"{$metodo->nombre}\" requiere una referencia.",
                ], 422);
            }
        }

        // ─── Pre-calcular desglose fiscal de cada línea, UNA sola vez ──────────────
        $lineas = [];
        $subtotalVenta = 0;
        $ivaVentaTotal = 0;
        $iepsVentaTotal = 0;

        foreach ($data['items'] as $item) {
            $variante = ProductoVariante::with('producto')->findOrFail($item['producto_variante_id']);
            $descuentoLinea = $item['descuento_linea'] ?? 0;
            $cantidad = $item['cantidad'];

            $precioEfectivoUnitario = $item['precio_unitario'] - ($descuentoLinea / $cantidad);
            $desglose = $variante->calcularDesgloseFiscal($precioEfectivoUnitario);

            $ivaLinea = round($desglose['iva_monto'] * $cantidad, 2);
            $iepsLinea = round($desglose['ieps_monto'] * $cantidad, 2);
            $totalLinea = ($item['precio_unitario'] * $cantidad) - $descuentoLinea;
            $subtotalNetoLinea = round($totalLinea - $ivaLinea - $iepsLinea, 2);

            $lineas[] = [
                'variante' => $variante,
                'cantidad' => $cantidad,
                'precio_unitario' => $item['precio_unitario'],
                'precio_lista' => $item['precio_lista'],
                'descuento_linea' => $descuentoLinea,
                'iva_monto' => $ivaLinea,
                'ieps_monto' => $iepsLinea,
                'subtotal' => $totalLinea, // se mantiene el total de línea como "subtotal" del item, ya con impuestos
            ];

            $subtotalVenta += $subtotalNetoLinea;
            $ivaVentaTotal += $ivaLinea;
            $iepsVentaTotal += $iepsLinea;
        }

        $descuentoVenta = $data['descuento'] ?? 0;

        // El descuento se aplica sobre el TOTAL final (precio ya con
        // impuestos, que es lo que paga el cliente), no sobre el subtotal.
        // Escalamos subtotal e IEPS por el mismo factor, y el IVA se saca
        // por diferencia para que todo cuadre exacto sin inyectarle
        // redondeo a un IEPS que en la mayoría de las ventas es $0.
        $totalBruto = round($subtotalVenta + $ivaVentaTotal + $iepsVentaTotal, 2);
        $totalVenta = round($totalBruto - $descuentoVenta, 2);
        $factorDescuento = $totalBruto > 0 ? $totalVenta / $totalBruto : 1;

        $baseGravable = round($subtotalVenta * $factorDescuento, 2);
        $iepsVentaFinal = round($iepsVentaTotal * $factorDescuento, 2);
        $ivaVentaFinal = round($totalVenta - $baseGravable - $iepsVentaFinal, 2);

        $totalPagos = round(collect($data['pagos'])->sum('monto'), 2);

        if (abs($totalVenta - $totalPagos) > 0.01) {
            return response()->json([
                'message' => "El total de pagos (\${$totalPagos}) no coincide con el total de la venta (\${$totalVenta}).",
            ], 422);
        }

        try {
            $venta = DB::transaction(function () use ($data, $sesion, $empleado, $lineas, $subtotalVenta, $descuentoVenta, $baseGravable, $ivaVentaFinal, $iepsVentaFinal, $totalVenta) {
                $siguienteNumero = (Venta::max('numero_ticket') ?? 0) + 1;

                $venta = Venta::create([
                    'sesion_caja_id' => $sesion->id,
                    'usuario_id' => $empleado->id,
                    'cliente_id' => $data['cliente_id'] ?? null,
                    'numero_ticket' => $siguienteNumero,
                    'subtotal' => $subtotalVenta,
                    'descuento' => $descuentoVenta,
                    'base_gravable' => $baseGravable,
                    'iva_total' => $ivaVentaFinal,
                    'ieps_total' => $iepsVentaFinal,
                    'total' => $totalVenta,
                    'estado' => 'completada',
                ]);

                foreach ($lineas as $linea) {
                    $variante = $linea['variante'];

                    VentaItem::create([
                        'venta_id' => $venta->id,
                        'producto_variante_id' => $variante->id,
                        'nombre_snapshot' => "{$variante->producto->nombre} - {$variante->nombre}",
                        'cantidad' => $linea['cantidad'],
                        'precio_unitario' => $linea['precio_unitario'],
                        'precio_lista' => $linea['precio_lista'],
                        'descuento_linea' => $linea['descuento_linea'],
                        'iva_monto' => $linea['iva_monto'],
                        'ieps_monto' => $linea['ieps_monto'],
                        'costo_unitario' => $variante->cost_net,
                        'subtotal' => $linea['subtotal'],
                    ]);

                    MovimientoInventario::registrar([
                        'variante_id' => $variante->id,
                        'sucursal_id' => $sesion->caja->sucursal_id,
                        'type' => 'out',
                        'reason' => 'venta',
                        'cantidad' => $linea['cantidad'],
                        'user_id' => $empleado->id,
                        'reference_id' => $venta->id,
                        'reference_type' => Venta::class,
                    ]);
                }

                foreach ($data['pagos'] as $pago) {
                    VentaPago::create([
                        'venta_id' => $venta->id,
                        'metodo_pago_id' => $pago['metodo_pago_id'],
                        'monto' => $pago['monto'],
                        'recibido' => $pago['recibido'] ?? null,
                        'referencia' => $pago['referencia'] ?? null,
                    ]);
                }

                return $venta;
            });
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $venta->load(['items', 'pagos.metodoPago', 'usuario', 'cliente']);

        $tenant = tenant();
        $numeroTicketCompleto = $tenant->codigo_ticket
            .str_pad((string) $venta->numero_ticket, 4, '0', STR_PAD_LEFT);

        $ventaArray = $venta->toArray();
        $ventaArray['numero_ticket_completo'] = $numeroTicketCompleto;

        return response()->json($ventaArray, 201);
    }

    public function verificarEmpleado(Request $request)
    {
        $empleado = $this->verificarEmpleadoPin($request);

        if (! $empleado->hasPermission('ventas.crear')) {
            return response()->json(['message' => 'Este empleado no tiene permiso para vender.'], 403);
        }

        return response()->json([
            'id' => $empleado->id,
            'name' => $empleado->name,
        ]);
    }

    public function autorizarDescuento(Request $request)
    {
        $empleado = $this->verificarEmpleadoPin($request);

        $esAdmin = $empleado->role === User::ROLE_ADMIN_EMPRESA
            || $empleado->role === User::ROLE_ADMIN_SUCURSAL;

        if (! $esAdmin) {
            return response()->json(['message' => 'Solo un administrador puede autorizar descuentos.'], 403);
        }

        return response()->json([
            'id' => $empleado->id,
            'name' => $empleado->name,
        ]);
    }

    public function delDia(Request $request)
    {
        $user = $request->user();

        $query = Caja::query()->whereHas('sesionActiva');

        if ($user->isScopedToSucursal()) {
            $query->where('sucursal_id', $user->sucursal_id);
        }

        $cajas = $query->with(['sesionActiva.usuario'])->get();

        // Vendedor: solo su propia sesión, no todas las de su sucursal.
        if ($user->role === User::ROLE_VENDEDOR) {
            $cajas = $cajas->filter(fn ($caja) => $caja->sesionActiva?->usuario_id === $user->id);
        }

        $resultado = $cajas->map(function ($caja) {
            $ventas = Venta::where('sesion_caja_id', $caja->sesionActiva->id)
                ->where('estado', 'completada')
                ->whereDate('created_at', now()->toDateString())
                ->with('usuario', 'pagos.metodoPago', 'cliente')
                ->orderByDesc('created_at')
                ->get();

            return [
                'caja_id' => $caja->id,
                'caja_nombre' => $caja->nombre,
                'abierta_por' => $caja->sesionActiva->usuario->name,
                'ventas' => $ventas->map(fn ($v) => [
                    'id' => $v->id,
                    'numero_ticket' => $v->numero_ticket,
                    'numero_ticket_completo' => tenant()->codigo_ticket.str_pad((string) $v->numero_ticket, 4, '0', STR_PAD_LEFT),
                    'total' => (float) $v->total,
                    'cajero' => $v->usuario->name,
                    'cliente' => $v->cliente?->nombre ?? 'Público en general',
                    'metodos_pago' => $v->pagos->map(fn ($p) => [
                        'id' => $p->metodoPago->id,
                        'nombre' => $p->metodoPago->nombre,
                        'icono' => $p->metodoPago->icono,
                        'color' => $p->metodoPago->color,
                    ])->unique('nombre')->values(),
                    'hora' => $v->created_at->format('H:i'),
                ]),
                'total_dia' => $ventas->sum('total'),
            ];
        });

        return response()->json($resultado->values());
    }

    public function deLaSesion(Request $request)
    {
        $user = $request->user();
        $query = Caja::query()->whereHas('sesionActiva');
        if ($user->isScopedToSucursal()) {
            $query->where('sucursal_id', $user->sucursal_id);
        }
        $cajas = $query->with(['sesionActiva.usuario'])->get();
        if ($user->role === User::ROLE_VENDEDOR) {
            $cajas = $cajas->filter(fn ($caja) => $caja->sesionActiva?->usuario_id === $user->id);
        }
        $resultado = $cajas->map(function ($caja) {
            $ventas = Venta::where('sesion_caja_id', $caja->sesionActiva->id)
                ->whereIn('estado', ['completada', 'cancelada'])
                ->with('usuario', 'pagos.metodoPago', 'devolucion.metodoDevolucion', 'cliente')
                ->orderByDesc('created_at')
                ->get();

            // Desglose por método de pago, sumando todos los pagos de las ventas.
            $totalesPorMetodo = [];
            foreach ($ventas as $venta) {
                foreach ($venta->pagos as $pago) {
                    $metodo = $pago->metodoPago;
                    $key = $metodo->id;
                    if (! isset($totalesPorMetodo[$key])) {
                        $totalesPorMetodo[$key] = [
                            'id' => $metodo->id,
                            'metodo' => $metodo->nombre,
                            'icono' => $metodo->icono,
                            'color' => $metodo->color,
                            'total' => 0,
                        ];
                    }
                    $totalesPorMetodo[$key]['total'] += (float) $pago->monto;
                }
            }

            // Bruto de efectivo cobrado (incluye ventas luego canceladas), capturado
            // ANTES de restar devoluciones. Es el mismo componente que usa
            // calcularEfectivoEsperado() y alimenta la línea "Cobrado en efectivo"
            // del desglose en Flutter — no debe confundirse con el neto por método.
            $efectivoCobradoBruto = 0;
            foreach ($totalesPorMetodo as $t) {
                if (strtolower($t['metodo']) === 'efectivo') {
                    $efectivoCobradoBruto = $t['total'];
                    break;
                }
            }

            // Segundo paso: restar devoluciones prorrateadas contra el método
            // ORIGINAL de cobro (no el método de la devolución), respetando
            // pagos divididos. Esto asegura que el neto por método refleje de
            // dónde salió la venta, sin importar por dónde se hizo el reembolso.
            foreach ($ventas as $venta) {
                if ($venta->estado !== 'cancelada' || ! $venta->devolucion) {
                    continue;
                }

                $montoDevuelto = (float) $venta->devolucion->monto_devuelto;
                $totalVenta = (float) $venta->total;

                if ($totalVenta <= 0) {
                    continue;
                }

                foreach ($venta->pagos as $pago) {
                    $metodoId = $pago->metodoPago->id;
                    $proporcion = (float) $pago->monto / $totalVenta;
                    $aRestar = round($montoDevuelto * $proporcion, 2);

                    if (isset($totalesPorMetodo[$metodoId])) {
                        $totalesPorMetodo[$metodoId]['total'] -= $aRestar;
                    }
                }
            }

            return [
                'caja_id' => $caja->id,
                'caja_nombre' => $caja->nombre,
                'abierta_por' => $caja->sesionActiva->usuario->name,
                'ventas' => $ventas->map(fn ($v) => [
                    'id' => $v->id,
                    'numero_ticket' => $v->numero_ticket,
                    'numero_ticket_completo' => tenant()->codigo_ticket.str_pad((string) $v->numero_ticket, 4, '0', STR_PAD_LEFT),
                    'total' => (float) $v->total,
                    'estado' => $v->estado,
                    'cajero' => $v->usuario->name,
                    'cliente' => $v->cliente?->nombre ?? 'Público en general',
                    'metodos_pago' => $v->pagos->map(fn ($p) => [
                        'id' => $p->metodoPago->id,
                        'nombre' => $p->metodoPago->nombre,
                        'icono' => $p->metodoPago->icono,
                        'color' => $p->metodoPago->color,
                    ])->unique('nombre')->values(),
                    'hora' => $v->created_at->format('H:i'),
                    'devolucion' => $v->devolucion ? [
                        'monto_devuelto' => (float) $v->devolucion->monto_devuelto,
                        'metodo' => $v->devolucion->metodoDevolucion->nombre,
                        'motivo' => $v->devolucion->motivo,
                    ] : null,
                ]),
                'total_sesion' => round($ventas->sum('total'), 2),
                'totales_por_metodo' => collect($totalesPorMetodo)->map(fn ($t) => [
                    'id' => $t['id'],
                    'metodo' => $t['metodo'],
                    'icono' => $t['icono'],
                    'color' => $t['color'],
                    'total' => round($t['total'], 2),
                ])->values(),
                'efectivo_esperado' => $this->calcularEfectivoEsperado($caja->sesionActiva),
                'efectivo_cobrado_bruto' => round($efectivoCobradoBruto, 2),
            ];
        });

        return response()->json($resultado->values());
    }

    private function calcularEfectivoEsperado(SesionCaja $sesion): float
    {
        $ventasCanceladas = Venta::where('sesion_caja_id', $sesion->id)
            ->where('estado', 'cancelada')
            ->with('devolucion.metodoDevolucion', 'pagos.metodoPago')
            ->get();

        $ventasCompletadas = Venta::where('sesion_caja_id', $sesion->id)
            ->where('estado', 'completada')
            ->with('pagos.metodoPago')
            ->get();

        $efectivoCompletadas = 0;
        foreach ($ventasCompletadas as $venta) {
            foreach ($venta->pagos as $pago) {
                if (strtolower($pago->metodoPago->nombre) === 'efectivo') {
                    $efectivoCompletadas += (float) $pago->monto;
                }
            }
        }

        $efectivoCanceladas = 0;
        $efectivoDevuelto = 0;
        foreach ($ventasCanceladas as $venta) {
            foreach ($venta->pagos as $pago) {
                if (strtolower($pago->metodoPago->nombre) === 'efectivo') {
                    $efectivoCanceladas += (float) $pago->monto;
                }
            }
            if ($venta->devolucion && strtolower($venta->devolucion->metodoDevolucion->nombre) === 'efectivo') {
                $efectivoDevuelto += (float) $venta->devolucion->monto_devuelto;
            }
        }

        return round((float) $sesion->monto_inicial + $efectivoCompletadas + $efectivoCanceladas - $efectivoDevuelto, 2);
    }

    public function show(Request $request, int $id)
    {
        $venta = Venta::with([
            'items',
            'pagos.metodoPago',
            'usuario',
            'sesionCaja.caja.sucursal',
            'cliente',
        ])->findOrFail($id);

        $tenant = tenant();
        $numeroTicketCompleto = $tenant->codigo_ticket
            .str_pad((string) $venta->numero_ticket, 4, '0', STR_PAD_LEFT);

        return response()->json([
            'id' => $venta->id,
            'numero_ticket' => $venta->numero_ticket,
            'numero_ticket_completo' => $numeroTicketCompleto,
            'fecha' => $venta->created_at->format('d/m/Y H:i:s'),
            'cajero' => $venta->usuario->name,
            'caja' => $venta->sesionCaja->caja->nombre,
            'sucursal' => $venta->sesionCaja->caja->sucursal->nombre,
            'cliente' => $venta->cliente?->nombre ?? 'Público en general',
            'estado' => $venta->estado,
            'subtotal' => (float) $venta->subtotal,
            'descuento' => (float) $venta->descuento,
            'base_gravable' => (float) $venta->base_gravable,
            'iva_total' => (float) $venta->iva_total,
            'ieps_total' => (float) $venta->ieps_total,
            'total' => (float) $venta->total,
            'items' => $venta->items->map(fn ($item) => [
                'id' => $item->id,
                'nombre_snapshot' => $item->nombre_snapshot,
                'cantidad' => $item->cantidad,
                'precio_unitario' => (float) $item->precio_unitario,
                'descuento_linea' => (float) $item->descuento_linea,
                'iva_monto' => (float) $item->iva_monto,
                'ieps_monto' => (float) $item->ieps_monto,
                'subtotal' => (float) $item->subtotal,
            ]),
            'pagos' => $venta->pagos->map(fn ($pago) => [
                'id' => $pago->id,
                'metodo' => $pago->metodoPago->nombre,
                'icono' => $pago->metodoPago->icono,
                'color' => $pago->metodoPago->color,
                'monto' => (float) $pago->monto,
                'recibido' => $pago->recibido ? (float) $pago->recibido : null,
                'cambio' => $pago->recibido ? round($pago->recibido - $pago->monto, 2) : null,
                'referencia' => $pago->referencia,
            ]),
        ]);
    }

    public function cancelar(Request $request, int $id)
    {
        $empleado = $this->verificarEmpleadoPin($request);

        $esAdmin = $empleado->role === User::ROLE_ADMIN_EMPRESA
            || $empleado->role === User::ROLE_ADMIN_SUCURSAL;

        if (! $esAdmin) {
            return response()->json(['message' => 'Solo un administrador puede cancelar ventas.'], 403);
        }

        $venta = Venta::with(['items', 'sesionCaja.caja'])->findOrFail($id);

        if ($venta->estado !== 'completada') {
            return response()->json(['message' => 'Solo se pueden cancelar ventas completadas.'], 422);
        }

        $data = $request->validate([
            'metodo_devolucion_id' => 'required|integer|exists:metodos_pago,id',
            'monto_devuelto' => 'required|numeric|min:0',
            'motivo' => 'nullable|string|max:500',
            'items_devueltos' => 'required|array',
            'items_devueltos.*.venta_item_id' => 'required|integer',
            'items_devueltos.*.devuelto_a_inventario' => 'required|boolean',
        ]);

        DB::transaction(function () use ($venta, $empleado, $data) {
            // Actualizar estado de la venta
            $venta->update([
                'estado' => 'cancelada',
                'cancelada_en' => now(),
                'cancelada_por_id' => $empleado->id,
            ]);

            // Devolver inventario por ítem si aplica
            foreach ($data['items_devueltos'] as $itemData) {
                if (! $itemData['devuelto_a_inventario']) {
                    continue;
                }

                $item = $venta->items->firstWhere('id', $itemData['venta_item_id']);
                if (! $item) {
                    continue;
                }

                MovimientoInventario::registrar([
                    'variante_id' => $item->producto_variante_id,
                    'sucursal_id' => $venta->sesionCaja->caja->sucursal_id,
                    'type' => 'in',
                    'reason' => 'devolucion',
                    'cantidad' => $item->cantidad,
                    'user_id' => $empleado->id,
                    'reference_id' => $venta->id,
                    'reference_type' => Venta::class,
                ]);
            }

            // Crear registro de devolución
            Devolucion::create([
                'venta_id' => $venta->id,
                'cancelada_por_id' => $empleado->id,
                'metodo_devolucion_id' => $data['metodo_devolucion_id'],
                'monto_devuelto' => $data['monto_devuelto'],
                'motivo' => $data['motivo'] ?? null,
                'items_devueltos' => $data['items_devueltos'],
                'devuelta_en' => now(),
            ]);
        });

        return response()->json(['message' => 'Venta cancelada correctamente.']);
    }

    /**
     * GET /ventas/todas
     * Listado completo de ventas para administradores. Admin de empresa ve
     * todas las sucursales; admin de sucursal solo ve la suya.
     */
    public function todas(Request $request)
    {
        $user = $request->user();

        $rolesPermitidos = [User::ROLE_ADMIN_EMPRESA, User::ROLE_ADMIN_SUCURSAL];
        if (! in_array($user->role, $rolesPermitidos, true)) {
            return response()->json(['message' => 'No tienes permiso para ver este listado.'], 403);
        }

        $query = Venta::with('usuario', 'pagos.metodoPago', 'sesionCaja.caja.sucursal', 'cliente')
            ->orderByDesc('created_at');

        if ($user->isScopedToSucursal()) {
            $query->whereHas(
                'sesionCaja.caja',
                fn ($q) => $q->where('sucursal_id', $user->sucursal_id)
            );
        } elseif ($request->filled('sucursal_id')) {
            $query->whereHas(
                'sesionCaja.caja',
                fn ($q) => $q->where('sucursal_id', $request->integer('sucursal_id'))
            );
        }

        // Filtro de mes: llega como 'YYYY-MM'. Si no viene, se muestran
        // todas las fechas sin restricción.
        if ($request->filled('mes')) {
            $fecha = Carbon::createFromFormat('Y-m', $request->string('mes'));
            $query->whereBetween('created_at', [
                $fecha->copy()->startOfMonth(),
                $fecha->copy()->endOfMonth(),
            ]);
        }

        $ventas = $query->get();
        $tenant = tenant();

        $resultado = $ventas->map(fn ($v) => [
            'id' => $v->id,
            'numero_ticket' => $v->numero_ticket,
            'numero_ticket_completo' => $tenant->codigo_ticket.str_pad((string) $v->numero_ticket, 4, '0', STR_PAD_LEFT),
            'fecha' => $v->created_at->format('d/m/Y H:i'),
            'total' => (float) $v->total,
            'estado' => $v->estado,
            'cajero' => $v->usuario->name,
            'cliente' => $v->cliente?->nombre ?? 'Público en general',
            'sucursal' => $v->sesionCaja->caja->sucursal->nombre,
        ]);

        // El resumen se calcula ANTES de aplicar la búsqueda de ticket, para
        // que refleje el periodo/sucursal filtrado y no cambie solo porque
        // el usuario está tecleando en la lupa.
        $completadas = $resultado->where('estado', 'completada');
        $canceladas = $resultado->where('estado', 'cancelada');

        $resumen = [
            'ventas_count' => $completadas->count(),
            'ventas_total' => round((float) $completadas->sum('total'), 2),
            'canceladas_count' => $canceladas->count(),
            'canceladas_total' => round((float) $canceladas->sum('total'), 2),
        ];

        if ($request->filled('buscar')) {
            $termino = mb_strtolower($request->string('buscar'));
            $resultado = $resultado->filter(
                fn ($v) => str_contains(mb_strtolower($v['numero_ticket_completo']), $termino)
            )->values();
        }

        return response()->json([
            'resumen' => $resumen,
            'ventas' => $resultado->values(),
        ]);
    }
}
