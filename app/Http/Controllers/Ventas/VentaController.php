<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Concerns\VerificaEmpleadoPorPin;
use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\MetodoPago;
use App\Models\MovimientoInventario;
use App\Models\ProductoVariante;
use App\Models\SesionCaja;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Models\VentaPago;
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

        // Calcular subtotal de items y validar contra el total de pagos
        $subtotalVenta = 0;
        foreach ($data['items'] as $item) {
            $descuentoLinea = $item['descuento_linea'] ?? 0;
            $subtotalVenta += ($item['precio_unitario'] * $item['cantidad']) - $descuentoLinea;
        }

        $descuentoVenta = $data['descuento'] ?? 0;
        $totalVenta = round($subtotalVenta - $descuentoVenta, 2);
        $totalPagos = round(collect($data['pagos'])->sum('monto'), 2);

        if (abs($totalVenta - $totalPagos) > 0.01) {
            return response()->json([
                'message' => "El total de pagos (\${$totalPagos}) no coincide con el total de la venta (\${$totalVenta}).",
            ], 422);
        }

        try {
            $venta = DB::transaction(function () use ($data, $sesion, $empleado, $subtotalVenta, $descuentoVenta, $totalVenta) {
                $siguienteNumero = (Venta::max('numero_ticket') ?? 0) + 1;
                $venta = Venta::create([
                    'sesion_caja_id' => $sesion->id,
                    'usuario_id' => $empleado->id,
                    'cliente_id' => $data['cliente_id'] ?? null,
                    'numero_ticket' => $siguienteNumero,
                    'subtotal' => $subtotalVenta,
                    'descuento' => $descuentoVenta,
                    'total' => $totalVenta,
                    'estado' => 'completada',
                ]);

                foreach ($data['items'] as $item) {
                    $variante = ProductoVariante::with('producto')->findOrFail($item['producto_variante_id']);
                    $descuentoLinea = $item['descuento_linea'] ?? 0;
                    $cantidad = $item['cantidad'];

                    // Precio efectivo por unidad, ya con descuento de línea aplicado.
                    $precioEfectivoUnitario = $item['precio_unitario'] - ($descuentoLinea / $cantidad);

                    // Desglose fiscal sobre lo REALMENTE cobrado, no sobre el
                    // precio de catálogo — así el IVA es correcto aunque haya descuento.
                    $desglose = $variante->calcularDesgloseFiscal($precioEfectivoUnitario);
                    $ivaMontoLinea = round($desglose['iva_monto'] * $cantidad, 2);
                    $iepsMontoLinea = round($desglose['ieps_monto'] * $cantidad, 2);

                    $subtotalLinea = ($item['precio_unitario'] * $cantidad) - $descuentoLinea;

                    VentaItem::create([
                        'venta_id' => $venta->id,
                        'producto_variante_id' => $variante->id,
                        'nombre_snapshot' => "{$variante->producto->nombre} - {$variante->nombre}",
                        'cantidad' => $cantidad,
                        'precio_unitario' => $item['precio_unitario'],
                        'precio_lista' => $item['precio_lista'],
                        'descuento_linea' => $descuentoLinea,
                        'iva_monto' => $ivaMontoLinea,
                        'ieps_monto' => $iepsMontoLinea,
                        'costo_unitario' => $variante->cost_net,
                        'subtotal' => $subtotalLinea,
                    ]);

                    // Descuenta inventario; lanza InvalidArgumentException si no hay stock.
                    MovimientoInventario::registrar([
                        'variante_id' => $variante->id,
                        'sucursal_id' => $sesion->caja->sucursal_id,
                        'type' => 'out',
                        'reason' => 'venta',
                        'cantidad' => $cantidad,
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
                        'referencia' => $pago['referencia'] ?? null,
                    ]);
                }

                return $venta;
            });
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $venta->load(['items', 'pagos.metodoPago', 'usuario']);

        return response()->json($venta, 201);
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
}
