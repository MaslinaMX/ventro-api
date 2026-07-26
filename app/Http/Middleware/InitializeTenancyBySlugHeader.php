<?php

// V1

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

/**
 * Resuelve el tenant a partir de un header explícito (X-Tenant-Slug), buscando
 * por el campo `slug` de la tabla tenants. A diferencia de InitializeTenancyBySubdomain,
 * este middleware NO depende del Host de la petición — funciona igual si la
 * petición viene de Flutter web (leyendo window.location.hostname) o de la
 * app nativa (leyendo el slug de un deep link), ya que ambos mandan el mismo header.
 * Pensado para rutas públicas (catálogo compartible) sin sesión.
 */
class InitializeTenancyBySlugHeader
{
    public function handle(Request $request, Closure $next)
    {
        $slug = $request->header('X-Tenant-Slug');

        if (! $slug) {
            return response()->json([
                'message' => 'No se especificó el negocio (X-Tenant-Slug).',
            ], 400);
        }

        $tenant = Tenant::where('slug', $slug)->first();

        if (! $tenant) {
            return response()->json([
                'message' => 'Negocio no encontrado.',
            ], 404);
        }

        tenancy()->initialize($tenant);

        $response = $next($request);

        tenancy()->end();

        return $response;
    }
}
