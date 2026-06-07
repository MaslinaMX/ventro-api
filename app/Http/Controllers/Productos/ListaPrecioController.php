<?php

namespace App\Http\Controllers\Productos;

use App\Http\Controllers\Controller;
use App\Models\ListaPrecio;
use Illuminate\Http\Request;

class ListaPrecioController extends Controller
{
    public function index()
    {
        return response()->json(ListaPrecio::where('activo', true)->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => ['required', 'string'],
            'descripcion' => ['nullable', 'string'],
        ]);

        $lista = ListaPrecio::create([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'activo' => true,
        ]);

        return response()->json($lista, 201);
    }

    public function show(int $id)
    {
        $lista = ListaPrecio::findOrFail($id);

        return response()->json($lista->load('precios.variante'));
    }

    public function update(Request $request, int $id)
    {
        $lista = ListaPrecio::findOrFail($id);

        $request->validate([
            'nombre' => ['sometimes', 'string'],
            'descripcion' => ['nullable', 'string'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $lista->update($request->only(['nombre', 'descripcion', 'activo']));

        return response()->json($lista);
    }

    public function destroy(int $id)
    {
        $lista = ListaPrecio::findOrFail($id);
        $lista->update(['activo' => false]);

        return response()->json(['message' => 'Lista de precios desactivada.']);
    }
}
