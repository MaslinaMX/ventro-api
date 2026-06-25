<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use Illuminate\Http\Request;

class CajaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Caja::query();

        if ($user->isScopedToSucursal()) {
            $query->where('sucursal_id', $user->sucursal_id);
        }

        $cajas = $query->with([
            'sucursal',
            'sesionActiva.usuario',
        ])->get();

        return response()->json($cajas);
    }

    public function show($id)
    {
        $caja = Caja::with('sucursal')->findOrFail($id);

        return response()->json($caja);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string',
            'sucursal_id' => 'required|exists:sucursales,id',
            'activa' => 'sometimes|boolean',
        ]);

        $caja = Caja::create([
            ...$data,
            'is_deletable' => true,
        ]);

        return response()->json($caja->load('sucursal'), 201);
    }

    public function update(Request $request, $id)
    {
        $caja = Caja::findOrFail($id);

        $data = $request->validate([
            'nombre' => 'sometimes|string',
            'sucursal_id' => 'sometimes|exists:sucursales,id',
            'activa' => 'sometimes|boolean',
        ]);

        $caja->update($data);
        $caja->refresh();

        return response()->json($caja->load('sucursal'));
    }

    public function destroy($id)
    {
        $caja = Caja::findOrFail($id);

        if (! $caja->is_deletable) {
            return response()->json(['message' => 'Esta caja no se puede eliminar.'], 403);
        }

        $tieneSesionAbierta = $caja->sesionActiva()->exists();

        if ($tieneSesionAbierta) {
            return response()->json(['message' => 'No se puede eliminar una caja con una sesión abierta.'], 422);
        }

        $caja->delete();

        return response()->json(null, 204);
    }

    /**
     * GET /cajas/abiertas
     * Solo cajas con sesión activa, para la pantalla de Ventas.
     */
    public function abiertas(Request $request)
    {
        $user = $request->user();
        $query = Caja::query()->whereHas('sesionActiva');

        if ($user->isScopedToSucursal()) {
            $query->where('sucursal_id', $user->sucursal_id);
        }

        $cajas = $query->with(['sucursal', 'sesionActiva.usuario'])->get();

        return response()->json($cajas);
    }
}
