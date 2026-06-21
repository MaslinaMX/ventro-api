<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ActivationController extends Controller
{
    public function show(Request $request, string $token): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-ID');
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            Log::info('Tenant no encontrado: '.$tenantId);

            return response()->json(['message' => 'Tenant no encontrado.'], 404);
        }

        $user = null;
        $tenant->run(function () use ($token, &$user) {
            Log::info('Buscando token en DB: '.DB::connection()->getDatabaseName());
            $user = User::where('invite_token', $token)->first();
            Log::info('Usuario encontrado: '.($user ? $user->email : 'null'));
            Log::info('activated_at: '.($user?->activated_at ?? 'null'));
            Log::info('invited_at: '.($user?->invited_at ?? 'null'));
        });

        if (! $user) {
            return response()->json(['message' => 'Enlace inválido o expirado.'], 404);
        }

        return response()->json(['name' => $user->name, 'email' => $user->email]);
    }

    public function activate(Request $request): JsonResponse
    {

        $centralConnection = config('tenancy.database.central_connection');

        $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $tenantId = $request->header('X-Tenant-ID');
        $tenant = Tenant::findOrFail($tenantId);

        $tenantUser = null;
        $tenant->run(function () use ($request, &$tenantUser) {
            $user = User::where('invite_token', $request->token)
                ->whereNull('activated_at')
                ->where('invited_at', '>=', now()->subHours(72))
                ->first();

            if (! $user) {
                return;
            }

            $user->update([
                'password' => Hash::make($request->password),
                'activated_at' => now(),
                'invite_token' => null,
                'activo' => true,
            ]);

            $tenantUser = $user;
        });

        if (! $tenantUser) {
            return response()->json(['message' => 'Enlace inválido o expirado.'], 404);
        }

        // Crear en vntr_central para que el lookup funcione
        TenantUser::on($centralConnection)->firstOrCreate([
            'email' => $tenantUser->email,
            'tenant_id' => $tenantId,
        ]);

        return response()->json(['message' => 'Cuenta activada correctamente.']);
    }
}
