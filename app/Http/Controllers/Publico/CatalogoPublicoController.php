<?php

// V1

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\Sucursal;
use Illuminate\Http\Request;

/**
 * Controller del catálogo público (mi-negocio.ventro.com.mx/publico/catalogo).
 * Muestra únicamente productos activos con variantes marcadas allow_online = true.
 * No expone stock, costos, ni ningún dato interno del negocio.
 */
class CatalogoPublicoController extends Controller
{
    /**
     * Info del negocio para el header del catálogo público: nombre, logo,
     * y datos de contacto de la primera sucursal registrada (dirección,
     * teléfono, email, sitio web). No expone razón social, RFC, ni plan.
     */
    public function negocio(Request $request)
    {
        $tenant = tenant();
        $sucursal = Sucursal::orderBy('id')->first();

        return response()->json([
            'nombre' => $tenant->name,
            'logo' => $tenant->logo,
            'contacto' => $sucursal ? [
                'direccion' => $sucursal->direccion,
                'ciudad' => $sucursal->ciudad,
                'estado' => $sucursal->estado,
                'telefono' => $sucursal->telefono,
                'email' => $sucursal->email,
                'sitio_web' => $sucursal->sitio_web,
            ] : null,
        ]);
    }

    public function index(Request $request)
    {
        $productos = Producto::with([
            'categoria',
            'variantes' => function ($q) {
                $q->where('activo', true)
                    ->where('allow_online', true)
                    ->with([
                        'imagenes' => fn ($q) => $q->where('is_primary', true),
                        'atributos.atributo',
                    ]);
            },
        ])
            ->where('activo', true)
            ->whereHas('variantes', function ($q) {
                $q->where('activo', true)->where('allow_online', true);
            })
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($q) use ($request) {
                    $q->where('nombre', 'like', "%{$request->search}%")
                        ->orWhereHas('variantes', function ($q) use ($request) {
                            $q->where('nombre', 'like', "%{$request->search}%");
                        });
                });
            })
            ->when($request->categoria_id, fn ($q) => $q->where('categoria_id', $request->categoria_id))
            ->get();

        // Precio calculado (con impuestos) por variante — sin exponer costo ni sku interno
        $productos->each(function ($producto) {
            $producto->variantes->each(function ($variante) {
                $variante->precio_calculado = $variante->calcularPrecio();
            });
        });

        return response()->json($productos);
    }
}
