<?php

namespace App\Http\Controllers\Gastos;

use App\Http\Controllers\Controller;
use App\Models\Gasto;
use App\Models\GastoHistorial;
use App\Models\User;
use Illuminate\Http\Request;

class GastoController extends Controller
{
    public function index(Request $request)
    {
        $gastos = Gasto::with(['categoria', 'metodoPago', 'sucursal', 'user'])
            ->when($request->filled('sucursal_id'), function ($query) use ($request) {
                $query->where('sucursal_id', $request->integer('sucursal_id'));
            })
            ->latest('fecha')
            ->get();

        return response()->json($gastos);
    }

    public function show($id)
    {
        $gasto = Gasto::with(['categoria', 'metodoPago', 'sucursal', 'user', 'historial.editadoPor'])
            ->findOrFail($id);

        return response()->json($gasto);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sucursal_id' => ['required', 'integer', 'exists:sucursales,id'],
            'categoria_id' => ['required', 'integer', 'exists:categorias_gasto,id'],
            'metodo_pago_id' => ['required', 'integer', 'exists:metodos_pago,id'],
            'concepto' => ['required', 'string'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'fecha' => ['required', 'date'],
            'proveedor' => ['nullable', 'string'],
            'comprobante_url' => ['nullable', 'string'],
            'notas' => ['nullable', 'string'],
        ]);

        $data['user_id'] = $request->user()->id;

        $gasto = Gasto::create($data);

        return response()->json($gasto->load(['categoria', 'metodoPago', 'sucursal', 'user']), 201);
    }

    public function update(Request $request, $id)
    {
        $gasto = Gasto::findOrFail($id);

        $rolesPermitidos = [User::ROLE_ADMIN_EMPRESA, User::ROLE_ADMIN_SUCURSAL];

        if (! in_array($request->user()->role, $rolesPermitidos, true)) {
            return response()->json(['message' => 'No tienes permiso para editar gastos.'], 403);
        }

        $data = $request->validate([
            'sucursal_id' => ['sometimes', 'integer', 'exists:sucursales,id'],
            'categoria_id' => ['sometimes', 'integer', 'exists:categorias_gasto,id'],
            'metodo_pago_id' => ['sometimes', 'integer', 'exists:metodos_pago,id'],
            'concepto' => ['sometimes', 'string'],
            'monto' => ['sometimes', 'numeric', 'min:0.01'],
            'fecha' => ['sometimes', 'date'],
            'proveedor' => ['nullable', 'string'],
            'comprobante_url' => ['nullable', 'string'],
            'notas' => ['nullable', 'string'],
            'motivo' => ['required', 'string', 'min:3'],
        ]);

        $motivo = $data['motivo'];
        unset($data['motivo']);

        GastoHistorial::create([
            'gasto_id' => $gasto->id,
            'editado_por' => $request->user()->id,
            'snapshot_anterior' => $gasto->toArray(),
            'motivo' => $motivo,
        ]);

        $gasto->update($data);
        $gasto->refresh();

        return response()->json($gasto->load(['categoria', 'metodoPago', 'sucursal', 'user']));
    }

    public function destroy($id)
    {
        $gasto = Gasto::findOrFail($id);
        $gasto->delete();

        return response()->json(null, 204);
    }
}
