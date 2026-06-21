<?php

namespace App\Http\Controllers\Productos;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\ProductoVariantePrecio;
use App\Models\ProductoVarianteStock;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        $sucursalId = $request->header('X-Sucursal-ID');
        $listaId = $request->lista_id;

        $productos = Producto::with([
            'categoria',
            'variantes' => function ($q) use ($sucursalId, $listaId) {
                $q->with([
                    'stock' => fn ($q) => $q->where('sucursal_id', $sucursalId),
                    'imagenes' => fn ($q) => $q->where('is_primary', true),
                    'precios' => fn ($q) => $listaId ? $q->where('lista_id', $listaId) : $q,
                ]);
            },
        ])
            ->where('activo', true)
            ->when($request->search, function ($q) use ($request) {
                $q->where('nombre', 'like', "%{$request->search}%")
                    ->orWhereHas('variantes', function ($q) use ($request) {
                        $q->where('sku', 'like', "%{$request->search}%")
                            ->orWhere('codigo_barras', 'like', "%{$request->search}%")
                            ->orWhere('nombre', 'like', "%{$request->search}%");
                    });
            })
            ->when($request->categoria_id, fn ($q) => $q->where('categoria_id', $request->categoria_id))
            ->when($request->activo !== null, fn ($q) => $q->where('activo', $request->activo))
            ->paginate(20);

        // Agregar precio calculado a cada variante
        $productos->each(function ($producto) {
            $producto->variantes->each(function ($variante) {
                $variante->precio_calculado = $variante->calcularPrecio();
            });
        });

        return response()->json($productos);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => ['required', 'string'],
            'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
            'descripcion' => ['nullable', 'string'],
            'tiene_variantes' => ['required', 'boolean'],
            'variantes' => ['required', 'array', 'min:1'],
            'variantes.*.nombre' => ['required', 'string'],
            'variantes.*.precio' => ['required', 'numeric', 'min:0'],
            'variantes.*.sku' => ['nullable', 'string'],
            'variantes.*.codigo_barras' => ['nullable', 'string'],
            'variantes.*.stocks' => ['required', 'array', 'min:1'],
            'variantes.*.stocks.*.sucursal_id' => ['required', 'integer', 'exists:sucursales,id'],
            'variantes.*.stocks.*.cantidad' => ['required', 'integer', 'min:0'],
            'variantes.*.stocks.*.cantidad_minima' => ['nullable', 'integer', 'min:0'],
            'variantes.*.iva' => ['nullable', 'numeric', 'in:0,8,16'],
            'variantes.*.ieps' => ['nullable', 'numeric', 'in:0,6,7,8,9,10,26.5,30,30.4,32,53,160'],
            'variantes.*.impuestos_incluidos' => ['nullable', 'boolean'],
            'variantes.*.is_default' => ['nullable', 'boolean'],
            'variantes.*.allow_online' => ['nullable', 'boolean'],
            'variantes.*.allow_out_of_stock' => ['nullable', 'boolean'],
            'variantes.*.sat_key' => ['nullable', 'string'],
            'variantes.*.cost_net' => ['nullable', 'numeric'],
            'variantes.*.precios' => ['nullable', 'array'],
            'variantes.*.precios.*.lista_id' => ['required_with:variantes.*.precios', 'exists:listas_precios,id'],
            'variantes.*.precios.*.precio' => ['required_with:variantes.*.precios', 'numeric', 'min:0'],
            'variantes.*.atributos' => ['nullable', 'array'],
            'variantes.*.atributos.*' => ['integer', 'exists:atributo_valores,id'],
        ]);

        $producto = Producto::create([
            'nombre' => $request->nombre,
            'categoria_id' => $request->categoria_id,
            'descripcion' => $request->descripcion,
            'tiene_variantes' => $request->tiene_variantes,
            'activo' => true,
        ]);

        foreach ($request->variantes as $v) {
            $variante = ProductoVariante::create([
                'producto_id' => $producto->id,
                'nombre' => $v['nombre'],
                'precio' => $v['precio'],
                'sku' => $v['sku'] ?? null,
                'codigo_barras' => $v['codigo_barras'] ?? null,
                'cost_net' => $v['cost_net'] ?? null,
                'iva' => $v['iva'] ?? 0,
                'ieps' => $v['ieps'] ?? 0,
                'impuestos_incluidos' => $v['impuestos_incluidos'] ?? false,
                'is_default' => $v['is_default'] ?? false,
                'allow_online' => $v['allow_online'] ?? false,
                'allow_out_of_stock' => $v['allow_out_of_stock'] ?? false,
                'sat_key' => $v['sat_key'] ?? null,
                'activo' => true,
            ]);

            // Stock por sucursal — una fila por cada sucursal que mande el form
            foreach ($v['stocks'] as $stockEntry) {
                ProductoVarianteStock::create([
                    'variante_id' => $variante->id,
                    'sucursal_id' => $stockEntry['sucursal_id'],
                    'cantidad' => $stockEntry['cantidad'],
                    'cantidad_minima' => $stockEntry['cantidad_minima'] ?? 0,
                ]);
            }

            if (! empty($v['precios'])) {
                foreach ($v['precios'] as $p) {
                    ProductoVariantePrecio::create([
                        'variante_id' => $variante->id,
                        'lista_id' => $p['lista_id'],
                        'precio' => $p['precio'],
                    ]);
                }
            }

            if (! empty($v['atributos'])) {
                $variante->atributos()->sync($v['atributos']);
            }
        }

        return response()->json(
            $producto->load(['categoria', 'variantes.stock', 'variantes.precios', 'variantes.atributos.atributo']),
            201
        );
    }

    public function show(Request $request, int $id)
    {
        $sucursalId = $request->header('X-Sucursal-ID');

        $producto = Producto::findOrFail($id);

        $producto->load([
            'categoria',
            'variantes' => function ($q) use ($sucursalId) {
                $q->with([
                    'stock' => fn ($q) => $q->where('sucursal_id', $sucursalId),
                    'precios.lista',
                    'imagenes',
                    'atributos.atributo',
                ]);
            },
        ]);

        $producto->stock_total = $producto->stockTotal($sucursalId);

        $producto->variantes->each(function ($variante) {
            $variante->precio_calculado = $variante->calcularPrecio();
        });

        return response()->json($producto);
    }

    public function update(Request $request, int $id)
    {
        $producto = Producto::findOrFail($id);

        $request->validate([
            'nombre' => ['sometimes', 'string'],
            'categoria_id' => ['sometimes', 'integer', 'exists:categorias,id'],
            'descripcion' => ['nullable', 'string'],
            'tiene_variantes' => ['sometimes', 'boolean'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $producto->update($request->only(['nombre', 'categoria_id', 'descripcion', 'tiene_variantes', 'activo']));

        return response()->json($producto->load(['categoria', 'variantes']));
    }

    public function destroy(int $id)
    {
        $producto = Producto::findOrFail($id);
        $producto->update(['activo' => false]);

        return response()->json(['message' => 'Producto desactivado.']);
    }
}
