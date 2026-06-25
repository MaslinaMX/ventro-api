<?php

namespace App\Http\Controllers;

use App\Models\MetodoPago;
use Illuminate\Http\Request;

class MetodoPagoController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->user()->hasPermission('metodos_pago.ver')) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        return response()->json(MetodoPago::all());
    }

    public function show(Request $request, $id)
    {
        if (! $request->user()->hasPermission('metodos_pago.ver')) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        return response()->json(MetodoPago::findOrFail($id));
    }

    public function store(Request $request)
    {
        if (! $request->user()->hasPermission('metodos_pago.crear')) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $data = $request->validate([
            'nombre' => 'required|string',
            'activo' => 'sometimes|boolean',
            'requiere_referencia' => 'sometimes|boolean',
            'icono' => 'nullable|string',
            'color' => 'nullable|string',
        ]);

        $metodo = MetodoPago::create([
            ...$data,
            'is_deletable' => true,
        ]);

        return response()->json($metodo, 201);
    }

    public function update(Request $request, $id)
    {
        if (! $request->user()->hasPermission('metodos_pago.editar')) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $metodo = MetodoPago::findOrFail($id);

        $data = $request->validate([
            'nombre' => 'sometimes|string',
            'activo' => 'sometimes|boolean',
            'requiere_referencia' => 'sometimes|boolean',
            'icono' => 'nullable|string',
            'color' => 'nullable|string',
        ]);

        $metodo->update($data);
        $metodo->refresh();

        return response()->json($metodo);
    }

    public function destroy(Request $request, $id)
    {
        if (! $request->user()->hasPermission('metodos_pago.eliminar')) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $metodo = MetodoPago::findOrFail($id);

        if (! $metodo->is_deletable) {
            return response()->json(['message' => 'Este método de pago no se puede eliminar.'], 403);
        }

        $metodo->delete();

        return response()->json(null, 204);
    }
}
