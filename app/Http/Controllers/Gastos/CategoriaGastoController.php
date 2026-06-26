<?php

namespace App\Http\Controllers\Gastos;

use App\Http\Controllers\Controller;
use App\Models\CategoriaGasto;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoriaGastoController extends Controller
{
    public function index()
    {
        return response()->json(
            CategoriaGasto::where('activo', true)->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => ['required', 'string'],
            'icono' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
        ]);

        $slug = Str::slug($request->nombre);
        $existente = CategoriaGasto::where('slug', $slug)->first();

        if ($existente) {
            return response()->json($existente, 200);
        }

        $categoria = CategoriaGasto::create([
            'nombre' => $request->nombre,
            'slug' => $slug,
            'icono' => $request->icono,
            'color' => $request->color,
            'activo' => true,
        ]);

        return response()->json($categoria, 201);
    }

    public function show($id)
    {
        $categoria = CategoriaGasto::findOrFail($id);

        return response()->json($categoria);
    }

    public function update(Request $request, $id)
    {
        $categoria = CategoriaGasto::findOrFail($id);

        $request->validate([
            'nombre' => ['sometimes', 'string'],
            'icono' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        if ($request->has('nombre')) {
            $request->merge(['slug' => Str::slug($request->nombre)]);
        }

        $categoria->update($request->only(['nombre', 'slug', 'icono', 'color', 'activo']));

        return response()->json($categoria);
    }

    public function destroy($id)
    {
        $categoria = CategoriaGasto::findOrFail($id);
        $categoria->update(['activo' => false]);

        return response()->json(['message' => 'Categoría de gasto desactivada.']);
    }
}
