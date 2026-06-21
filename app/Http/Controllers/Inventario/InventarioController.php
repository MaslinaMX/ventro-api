<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\MovimientoInventario;
use App\Models\ProductoVariante;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class InventarioController extends Controller
{
    /**
     * GET /api/inventario/sucursales/{sucursal}/stock
     *
     * Lista el stock actual de todas las variantes activas en una sucursal,
     * incluyendo variantes sin registro en producto_variante_stock (stock = 0).
     */
    public function stockPorSucursal(Request $request, int $sucursal)
    {
        $search = $request->query('search');

        $query = ProductoVariante::query()
            ->select([
                'producto_variantes.id as variante_id',
                'producto_variantes.nombre as variante_nombre',
                'producto_variantes.sku',
                'producto_variantes.codigo_barras',
                'producto_variantes.imagen',
                'productos.id as producto_id',
                'productos.nombre as producto_nombre',
                'producto_variante_stock.cantidad',
                'producto_variante_stock.cantidad_minima',
            ])
            ->join('productos', 'productos.id', '=', 'producto_variantes.producto_id')
            ->leftJoin('producto_variante_stock', function ($join) use ($sucursal) {
                $join->on('producto_variante_stock.variante_id', '=', 'producto_variantes.id')
                    ->where('producto_variante_stock.sucursal_id', '=', $sucursal);
            })
            ->where('producto_variantes.activo', true);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('producto_variantes.nombre', 'like', "%{$search}%")
                    ->orWhere('producto_variantes.sku', 'like', "%{$search}%")
                    ->orWhere('producto_variantes.codigo_barras', 'like', "%{$search}%")
                    ->orWhere('productos.nombre', 'like', "%{$search}%");
            });
        }

        $rows = $query
            ->orderBy('productos.nombre')
            ->orderBy('producto_variantes.nombre')
            ->get()
            ->map(function ($row) {
                $row->cantidad = $row->cantidad ?? 0;
                $row->cantidad_minima = $row->cantidad_minima ?? 0;
                $row->bajo_stock = (float) $row->cantidad <= (float) $row->cantidad_minima;

                return $row;
            });

        return response()->json($rows);
    }

    /**
     * GET /api/inventario/variantes/{variante}/movimientos
     *
     * Kardex: historial de movimientos de una variante, opcionalmente filtrado por sucursal.
     */
    public function movimientosPorVariante(Request $request, int $variante)
    {
        $query = MovimientoInventario::query()
            ->with(['sucursal:id,nombre', 'user:id,name'])
            ->where('variante_id', $variante);

        if ($sucursalId = $request->query('sucursal_id')) {
            $query->where('sucursal_id', $sucursalId);
        }

        $movimientos = $query
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json($movimientos);
    }

    /**
     * GET /api/inventario/sucursales/{sucursal}/movimientos
     *
     * Historial general de movimientos de una sucursal.
     */
    public function movimientosPorSucursal(Request $request, int $sucursal)
    {
        $query = MovimientoInventario::query()
            ->with(['variante:id,nombre,sku,producto_id', 'variante.producto:id,nombre', 'user:id,name', 'sucursal:id,nombre'])
            ->where('sucursal_id', $sucursal);

        if ($reason = $request->query('reason')) {
            $query->where('reason', $reason);
        }

        $movimientos = $query
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json($movimientos);
    }

    /**
     * POST /api/inventario/movimientos
     *
     * Registra un movimiento de inventario (ajuste, compra, venta, merma, devolución, transferencia)
     * y actualiza el stock correspondiente de forma atómica.
     */
    public function registrarMovimiento(Request $request)
    {
        $validated = $request->validate([
            'variante_id' => ['required', 'integer', 'exists:producto_variantes,id'],
            'sucursal_id' => ['required', 'integer', 'exists:sucursales,id'],
            'type' => ['required', Rule::in(MovimientoInventario::TYPES)],
            'reason' => ['required', Rule::in(MovimientoInventario::REASONS)],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['user_id'] = $request->user()?->id;

        try {
            /** @var MovimientoInventario $movimiento */
            $movimiento = MovimientoInventario::registrar($validated);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $movimiento->load(['sucursal:id,nombre', 'user:id,name', 'variante:id,nombre,sku']);

        return response()->json($movimiento, 201);
    }

    /**
     * POST /api/inventario/transferencias
     *
     * Transferencia de stock entre dos sucursales: genera un 'out' en origen
     * y un 'in' en destino, ambos con reason 'transferencia', en una sola transacción.
     */
    public function transferir(Request $request)
    {
        $validated = $request->validate([
            'variante_id' => ['required', 'integer', 'exists:producto_variantes,id'],
            'sucursal_origen_id' => ['required', 'integer', 'exists:sucursales,id', 'different:sucursal_destino_id'],
            'sucursal_destino_id' => ['required', 'integer', 'exists:sucursales,id'],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        $userId = $request->user()?->id;

        try {
            /** @var MovimientoInventario $salida */
            $salida = MovimientoInventario::registrar([
                'variante_id' => $validated['variante_id'],
                'sucursal_id' => $validated['sucursal_origen_id'],
                'type' => 'out',
                'reason' => 'transferencia',
                'cantidad' => $validated['cantidad'],
                'user_id' => $userId,
                'notas' => $validated['notas'] ?? null,
            ]);

            /** @var MovimientoInventario $entrada */
            $entrada = MovimientoInventario::registrar([
                'variante_id' => $validated['variante_id'],
                'sucursal_id' => $validated['sucursal_destino_id'],
                'type' => 'in',
                'reason' => 'transferencia',
                'cantidad' => $validated['cantidad'],
                'user_id' => $userId,
                'notas' => $validated['notas'] ?? null,
                'reference_id' => $salida->id,
                'reference_type' => MovimientoInventario::class,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'salida' => $salida,
            'entrada' => $entrada,
        ], 201);
    }
}
