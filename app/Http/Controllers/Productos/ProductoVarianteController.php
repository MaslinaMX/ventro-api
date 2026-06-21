<?php

namespace App\Http\Controllers\Productos;

use App\Http\Controllers\Controller;
use App\Models\ProductoVariante;
use App\Models\ProductoVariantePrecio;
use App\Models\ProductoVarianteStock;
use Illuminate\Http\Request;

class ProductoVarianteController extends Controller
{
    public function show(int $productoId, int $id)
    {
        $variante = ProductoVariante::where('producto_id', $productoId)->findOrFail($id);

        return response()->json(
            $variante->load([
                'stock',
                'imagenes',
                'precios.lista',
                'atributos.atributo',
            ])
        );
    }

    public function update(Request $request, int $productoId, int $id)
    {
        $variante = ProductoVariante::where('producto_id', $productoId)->findOrFail($id);

        $request->validate([
            'nombre' => ['sometimes', 'string'],
            'precio' => ['sometimes', 'numeric', 'min:0'],
            'sku' => ['nullable', 'string'],
            'codigo_barras' => ['nullable', 'string'],
            'cost_net' => ['nullable', 'numeric'],
            'iva' => ['nullable', 'numeric', 'in:0,8,16'],
            'ieps' => ['nullable', 'numeric', 'in:0,6,7,8,9,10,26.5,30,30.4,32,53,160'],
            'impuestos_incluidos' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'allow_online' => ['sometimes', 'boolean'],
            'allow_out_of_stock' => ['sometimes', 'boolean'],
            'sat_key' => ['nullable', 'string'],
            'activo' => ['sometimes', 'boolean'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'cantidad_minima' => ['sometimes', 'integer', 'min:0'],
            'precios' => ['sometimes', 'array'],
            'precios.*.lista_id' => ['required_with:precios', 'exists:listas_precios,id'],
            'precios.*.precio' => ['required_with:precios', 'numeric', 'min:0'],
            'atributos' => ['sometimes', 'array'],
            'atributos.*' => ['integer', 'exists:atributo_valores,id'],
        ]);

        $variante->update($request->only([
            'nombre', 'precio', 'sku', 'codigo_barras', 'cost_net', 'imagenes',
            'iva', 'ieps', 'impuestos_incluidos', 'is_default',
            'allow_online', 'allow_out_of_stock', 'sat_key', 'activo',
        ]));

        // Actualizar stock
        if ($request->has('stock')) {
            $sucursalId = $request->header('X-Sucursal-ID');
            ProductoVarianteStock::updateOrCreate(
                ['variante_id' => $variante->id, 'sucursal_id' => $sucursalId],
                [
                    'cantidad' => $request->stock,
                    'cantidad_minima' => $request->cantidad_minima ?? 0,
                ]
            );
        }

        // Actualizar precios por lista
        if ($request->has('precios')) {
            foreach ($request->precios as $p) {
                ProductoVariantePrecio::updateOrCreate(
                    ['variante_id' => $variante->id, 'lista_id' => $p['lista_id']],
                    ['precio' => $p['precio']]
                );
            }
        }

        // Actualizar atributos
        if ($request->has('atributos')) {
            $variante->atributos()->sync($request->atributos);
        }

        $sucursalId = $request->header('X-Sucursal-ID');

        return response()->json(
            $variante->load([
                'stock' => fn ($q) => $q->where('sucursal_id', $sucursalId),
                'precios.lista',
                'atributos.atributo',
            ])
        );
    }

    public function destroy(int $productoId, int $id)
    {
        $variante = ProductoVariante::where('producto_id', $productoId)->findOrFail($id);
        $variante->update(['activo' => false]);

        return response()->json(['message' => 'Variante desactivada.']);
    }

    // Agregar variante a producto existente
    public function store(Request $request, int $productoId)
    {
        $request->validate([
            'nombre' => ['required', 'string'],
            'precio' => ['required', 'numeric', 'min:0'],
            'sku' => ['nullable', 'string'],
            'codigo_barras' => ['nullable', 'string'],
            'cost_net' => ['nullable', 'numeric'],
            'iva' => ['nullable', 'numeric', 'in:0,8,16'],
            'ieps' => ['nullable', 'numeric', 'in:0,6,7,8,9,10,26.5,30,30.4,32,53,160'],
            'impuestos_incluidos' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'allow_online' => ['nullable', 'boolean'],
            'allow_out_of_stock' => ['nullable', 'boolean'],
            'sat_key' => ['nullable', 'string'],
            'stock' => ['required', 'integer', 'min:0'],
            'cantidad_minima' => ['nullable', 'integer', 'min:0'],
            'precios' => ['nullable', 'array'],
            'precios.*.lista_id' => ['required_with:precios', 'exists:listas_precios,id'],
            'precios.*.precio' => ['required_with:precios', 'numeric', 'min:0'],
            'atributos' => ['nullable', 'array'],
            'atributos.*' => ['integer', 'exists:atributo_valores,id'],
        ]);

        $sucursalId = $request->header('X-Sucursal-ID');

        $variante = ProductoVariante::create([
            'producto_id' => $productoId,
            'nombre' => $request->nombre,
            'precio' => $request->precio,
            'sku' => $request->sku,
            'codigo_barras' => $request->codigo_barras,
            'cost_net' => $request->cost_net,
            'iva' => $request->iva ?? 0,
            'ieps' => $request->ieps ?? 0,
            'impuestos_incluidos' => $request->impuestos_incluidos ?? false,
            'is_default' => $request->is_default ?? false,
            'allow_online' => $request->allow_online ?? false,
            'allow_out_of_stock' => $request->allow_out_of_stock ?? false,
            'sat_key' => $request->sat_key,
            'activo' => true,
        ]);

        ProductoVarianteStock::create([
            'variante_id' => $variante->id,
            'sucursal_id' => $sucursalId,
            'cantidad' => $request->stock,
            'cantidad_minima' => $request->cantidad_minima ?? 0,
        ]);

        if ($request->precios) {
            foreach ($request->precios as $p) {
                ProductoVariantePrecio::create([
                    'variante_id' => $variante->id,
                    'lista_id' => $p['lista_id'],
                    'precio' => $p['precio'],
                ]);
            }
        }

        if ($request->atributos) {
            $variante->atributos()->sync($request->atributos);
        }

        return response()->json(
            $variante->load([
                'stock' => fn ($q) => $q->where('sucursal_id', $sucursalId),
                'precios.lista',
                'imagenes',
                'atributos.atributo',
            ]),
            201
        );
    }
}
