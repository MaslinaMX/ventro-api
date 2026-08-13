<?php

namespace App\Http\Controllers\Clientes;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClienteRequest;
use App\Models\Cliente;
use App\Models\Venta;
use App\Models\VentaItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    /**
     * Lista de clientes, con búsqueda por nombre/RFC/teléfono/email y
     * paginación. Mismo patrón que el resto de listados de Ventro.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Cliente::query();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('rfc', 'like', "%{$search}%")
                    ->orWhere('telefono', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        $clientes = $query->orderBy('nombre')->paginate($request->integer('per_page', 20));

        return response()->json($clientes);
    }

    public function store(ClienteRequest $request): JsonResponse
    {
        $cliente = Cliente::create($request->validated());

        return response()->json($cliente, 201);
    }

    /**
     * Nota: recibimos $cliente como int (NO tipado como el modelo Cliente)
     * a propósito, para desactivar el binding implícito de Laravel. Ese
     * binding se resuelve vía SubstituteBindings, que puede correr antes
     * de que InitializeTenancyByHeader termine de cambiar la conexión al
     * tenant — resultando en queries contra la base central. Buscar el
     * modelo manualmente con findOrFail() aquí garantiza que la conexión
     * tenant ya esté activa. El nombre del parámetro debe seguir siendo
     * $cliente (no $id) porque apiResource genera la URI como
     * /clientes/{cliente}, y Laravel resuelve parámetros escalares por
     * nombre.
     */
    public function show(int $cliente): JsonResponse
    {
        $cliente = Cliente::findOrFail($cliente);

        return response()->json($cliente);
    }

    public function update(ClienteRequest $request, int $cliente): JsonResponse
    {
        $modelo = Cliente::findOrFail($cliente);
        $modelo->update($request->validated());

        return response()->json($modelo);
    }

    /**
     * Soft delete. Si el cliente ya tiene ventas asociadas, en vez de
     * borrar se recomienda desactivar (activo = false) desde el frontend
     * para conservar el historial sin perder la referencia visual.
     */
    public function destroy(int $cliente): JsonResponse
    {
        $modelo = Cliente::findOrFail($cliente);

        if ($modelo->ventas()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: el cliente tiene ventas asociadas. Puedes desactivarlo en su lugar.',
            ], 422);
        }

        $modelo->delete();

        return response()->json(null, 204);
    }

    public function estadisticas(int $cliente): JsonResponse
    {
        $modelo = Cliente::findOrFail($cliente);

        $ventasCompletadas = Venta::where('cliente_id', $cliente)
            ->where('estado', 'completada')
            ->get();

        $numeroCompras = $ventasCompletadas->count();
        $totalGastado = round((float) $ventasCompletadas->sum('total'), 2);
        $promedioCompra = $numeroCompras > 0 ? round($totalGastado / $numeroCompras, 2) : 0;
        $ultimaCompra = $ventasCompletadas->max('created_at');

        // Producto favorito: el nombre_snapshot con más unidades vendidas
        // entre los items de las ventas completadas de este cliente.
        $productoFavorito = VentaItem::whereIn('venta_id', $ventasCompletadas->pluck('id'))
            ->selectRaw('nombre_snapshot, SUM(cantidad) as total_cantidad')
            ->groupBy('nombre_snapshot')
            ->orderByDesc('total_cantidad')
            ->first();

        // Lista completa de compras (incluye canceladas, para historial fiel)
        $todasLasVentas = Venta::where('cliente_id', $cliente)
            ->orderByDesc('created_at')
            ->get();

        $tenant = tenant();

        return response()->json([
            'cliente' => [
                'id' => $modelo->id,
                'nombre' => $modelo->nombre,
            ],
            'total_gastado' => $totalGastado,
            'numero_compras' => $numeroCompras,
            'promedio_compra' => $promedioCompra,
            'ultima_compra' => $ultimaCompra?->format('d/m/Y H:i'),
            'producto_favorito' => $productoFavorito?->nombre_snapshot,
            'compras' => $todasLasVentas->map(fn ($v) => [
                'id' => $v->id,
                'numero_ticket_completo' => $tenant->codigo_ticket.str_pad((string) $v->numero_ticket, 4, '0', STR_PAD_LEFT),
                'fecha' => $v->created_at->format('d/m/Y H:i'),
                'total' => (float) $v->total,
                'estado' => $v->estado,
            ]),
        ]);
    }
}
