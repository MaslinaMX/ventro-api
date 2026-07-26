<?php

// V1

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

/**
 * Resuelve el tenant a partir del subdominio del request (ej. mi-negocio.ventro.com.mx)
 * usando el campo `slug` de la tabla tenants, en vez del sistema de `domains` de Stancl.
 * Pensado para rutas públicas (catálogo compartible) donde no hay X-Tenant-ID ni sesión.
 */
class InitializeTenancyBySubdomain
{
    /**
     * Dominio base de la app. Todo lo que venga antes de esto se toma como slug.
     */
    protected string $baseDomain = 'ventro.com.mx';

    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost();

        $slug = $this->extraerSlug($host);

        if (! $slug) {
            return response()->json([
                'message' => 'No se pudo determinar el negocio a partir del dominio.',
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

    /**
     * Extrae el slug del subdominio. Regresa null si el host es el dominio central
     * (ej. ventro.com.mx, app.ventro.com.mx) o no tiene el formato esperado.
     */
    protected function extraerSlug(string $host): ?string
    {
        $sufijo = '.'.$this->baseDomain;

        if (! str_ends_with($host, $sufijo)) {
            return null;
        }

        $slug = substr($host, 0, -strlen($sufijo));

        // Evita que subdominios reservados (api, app, www) se traten como negocios
        $reservados = ['api', 'app', 'www'];

        if ($slug === '' || in_array($slug, $reservados, true)) {
            return null;
        }

        return $slug;
    }
}
