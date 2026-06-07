<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

class InitializeTenancyByHeader
{
    public function handle(Request $request, Closure $next)
    {
        $tenantId = $request->header('X-Tenant-ID');

        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant no especificado.',
            ], 400);
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant no encontrado.',
            ], 404);
        }

        tenancy()->initialize($tenant);

        $response = $next($request);

        tenancy()->end();

        return $response;
    }
}
