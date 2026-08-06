<?php

namespace App\Http\Controllers\Clientes;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClienteRequest;
use App\Models\Cliente;
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
}
