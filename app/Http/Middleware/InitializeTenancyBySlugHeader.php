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
        $slug = $this->resolveSlug($request);

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

    protected function resolveSlug(Request $request): ?string
    {
        $slug = $request->header('X-Tenant-Slug');

        if ($slug) {
            return strtolower((string) $slug);
        }

        if (app()->environment(['local', 'testing'])) {
            $slug = $request->query('tenant')
                ?? $request->query('slug')
                ?? $this->resolveSlugFromQuery($request->query())
                ?? $this->resolveSlugFromHost($request->getHost());

            return $slug ? strtolower((string) $slug) : null;
        }

        return null;
    }

    protected function resolveSlugFromQuery(array $query): ?string
    {
        if ($query === []) {
            return null;
        }

        foreach ($query as $key => $value) {
            if ($value === null || $value === '') {
                return strtolower((string) $key);
            }
        }

        return null;
    }

    protected function resolveSlugFromHost(string $host): ?string
    {
        $host = strtolower($host);
        $suffixes = ['.localhost', '.test', '.local', '.lan'];

        foreach ($suffixes as $suffix) {
            if (! str_ends_with($host, $suffix)) {
                continue;
            }

            $slug = substr($host, 0, -strlen($suffix));

            if ($slug !== '' && ! in_array($slug, ['api', 'app', 'www'], true)) {
                return $slug;
            }
        }

        return null;
    }
}
