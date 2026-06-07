<?php

namespace App\Http\Controllers\Productos;

use App\Http\Controllers\Controller;
use App\Models\ProductoVariante;
use App\Models\ProductoVarianteImagen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductoImagenController extends Controller
{
    public function store(Request $request, int $productoId, int $varianteId)
    {
        $request->validate([
            'imagen' => ['required', 'image', 'max:5120'], // 5MB max
            'is_primary' => ['sometimes', 'boolean'],
        ]);

        $variante = ProductoVariante::where('producto_id', $productoId)->findOrFail($varianteId);

        $file = $request->file('imagen');
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid().'.'.$extension;
        $path = "productos/{$productoId}/variantes/{$varianteId}/{$filename}";

        Storage::disk('r2')->put($path, file_get_contents($file), 'public');

        $url = env('CLOUDFLARE_R2_URL').'/'.$path;

        // Si es primaria, desmarcar las demás
        if ($request->is_primary) {
            $variante->imagenes()->update(['is_primary' => false]);
        }

        $imagen = ProductoVarianteImagen::create([
            'variante_id' => $variante->id,
            'path' => $url,
            'is_primary' => $request->is_primary ?? false,
        ]);

        return response()->json($imagen, 201);
    }

    public function destroy(int $productoId, int $varianteId, int $id)
    {
        $imagen = ProductoVarianteImagen::where('variante_id', $varianteId)->findOrFail($id);

        // Extraer path relativo para eliminar de R2
        $relativePath = str_replace(env('CLOUDFLARE_R2_URL').'/', '', $imagen->path);
        Storage::disk('r2')->delete($relativePath);

        $imagen->delete();

        return response()->json(['message' => 'Imagen eliminada.']);
    }

    public function setPrimaria(int $productoId, int $varianteId, int $id)
    {
        $variante = ProductoVariante::where('producto_id', $productoId)->findOrFail($varianteId);
        $imagen = ProductoVarianteImagen::where('variante_id', $varianteId)->findOrFail($id);

        $variante->imagenes()->update(['is_primary' => false]);
        $imagen->update(['is_primary' => true]);

        return response()->json($imagen);
    }
}
