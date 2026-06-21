<?php

namespace App\Http\Controllers;

use App\Models\Sucursal;
use Illuminate\Http\Request;

class SucursalController extends Controller
{
    public function index()
    {
        return response()->json(Sucursal::all());
    }

    public function show($id)
    {
        $sucursal = Sucursal::findOrFail($id);

        return response()->json($sucursal);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string',
            'direccion' => 'nullable|string',
            'direccion_2' => 'nullable|string',
            'ciudad' => 'nullable|string',
            'estado' => 'nullable|string',
            'codigo_postal' => 'nullable|string',
            'pais' => 'nullable|string',
            'telefono' => 'nullable|string',
            'telefono_alternativo' => 'nullable|string',
            'email' => 'nullable|email',
            'sitio_web' => 'nullable|string',
            'rfc' => 'nullable|string',
        ]);

        $sucursal = Sucursal::create($data);

        return response()->json($sucursal, 201);
    }

    public function update(Request $request, $id)
    {
        $sucursal = Sucursal::findOrFail($id);

        $data = $request->validate([
            'nombre' => 'sometimes|string',
            'direccion' => 'nullable|string',
            'direccion_2' => 'nullable|string',
            'ciudad' => 'nullable|string',
            'estado' => 'nullable|string',
            'codigo_postal' => 'nullable|string',
            'pais' => 'nullable|string',
            'telefono' => 'nullable|string',
            'telefono_alternativo' => 'nullable|string',
            'email' => 'nullable|email',
            'sitio_web' => 'nullable|string',
            'rfc' => 'nullable|string',
        ]);

        $sucursal->update($data);
        $sucursal->refresh();

        return response()->json($sucursal);
    }

    public function destroy($id)
    {
        $sucursal = Sucursal::findOrFail($id);
        if (! $sucursal->is_deletable) {
            return response()->json(['message' => 'Esta sucursal no se puede eliminar.'], 403);
        }
        $sucursal->delete();

        return response()->json(null, 204);
    }
}
