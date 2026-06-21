<?php

namespace App\Http\Controllers\Productos;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoriaController extends Controller
{
    public function index()
    {
        return response()->json(
            Categoria::with('subcategorias')->whereNull('parent_id')->where('activo', true)->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => ['required', 'string'],
            'descripcion' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'exists:categorias,id'],
            'icono' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
        ]);

        $slug = Str::slug($request->nombre);
        $existente = Categoria::where('slug', $slug)->first();
        if ($existente) {
            return response()->json($existente, 200); // ya existe, regrésala tal cual
        }

        $categoria = Categoria::create([
            'nombre' => $request->nombre,
            'slug' => $slug,
            'descripcion' => $request->descripcion,
            'parent_id' => $request->parent_id,
            'icono' => $request->icono,
            'color' => $request->color,
            'activo' => true,
        ]);

        return response()->json($categoria, 201);
    }

    public function show(int $id)
    {
        $categoria = Categoria::findOrFail($id);

        return response()->json($categoria->load(['subcategorias', 'productos']));
    }

    public function update(Request $request, int $id)
    {
        $categoria = Categoria::findOrFail($id);

        $request->validate([
            'nombre' => ['sometimes', 'string'],
            'descripcion' => ['nullable', 'string'],
            'icono' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        if ($request->has('nombre')) {
            $request->merge(['slug' => Str::slug($request->nombre)]);
        }

        $categoria->update($request->only(['nombre', 'slug', 'descripcion', 'icono', 'color', 'activo']));

        return response()->json($categoria);
    }

    public function destroy(int $id)
    {
        $categoria = Categoria::findOrFail($id);
        $categoria->update(['activo' => false]);

        return response()->json(['message' => 'Categoría desactivada.']);
    }
}
