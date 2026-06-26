<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\ListaPrecio;
use App\Models\MetodoPago;
use App\Models\Sucursal;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;

class RegisterController extends Controller
{
    private function generarCodigoTicketUnico(): int
    {
        do {
            $codigo = random_int(100000, 999999);
        } while (Tenant::where('codigo_ticket', $codigo)->exists());

        return $codigo;
    }

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'empresa' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9\-]+$/'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $domain = $request->slug.'.ventro.com.mx';

        if (Domain::where('domain', $domain)->exists()) {
            return response()->json([
                'message' => 'Este dominio ya está en uso.',
                'errors' => ['slug' => ['Este nombre ya está en uso.']],
            ], 422);
        }

        // ─── Crear tenant central ─────────────────────────────────────────────
        $tenant = Tenant::create([
            'id' => Str::uuid(),
            'name' => $request->empresa,
            'slug' => $request->slug,
            'email' => $request->email,
            'plan' => 'basic',
            'status' => 'active',
            'codigo_ticket' => $this->generarCodigoTicketUnico(),
        ]);

        // ─── Suscripción ──────────────────────────────────────────────────────
        TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'plan' => 'free_trial',
            'status' => 'trial',
            'base_price' => 349.90,
            'currency' => 'MXN',
            'period' => 'monthly',
            'included_branches' => 1,
            'extra_branch_cost' => 199.90,
            'trial_ends_at' => now()->addDays(15),
            'next_billing_at' => now()->addDays(15),
            'spei_reference' => $request->slug,
        ]);

        // ─── Dominio ──────────────────────────────────────────────────────────
        try {
            $tenant->domains()->create(['domain' => $domain]);
        } catch (QueryException $e) {
            $tenant->delete();
            if ($e->errorInfo[1] === 1062) {
                return response()->json([
                    'message' => 'Este dominio ya está en uso.',
                    'errors' => ['slug' => ['Este nombre ya está en uso.']],
                ], 422);
            }
            throw $e;
        }

        TenantUser::create([
            'email' => $request->email,
            'tenant_id' => $tenant->id,
        ]);

        // ─── Inicializar tenant ───────────────────────────────────────────────
        tenancy()->initialize($tenant);

        try {
            $data = DB::transaction(function () use ($request) {
                $sucursal = Sucursal::create([
                    'nombre' => $request->empresa,
                    'email' => $request->email,
                    'is_main' => true,
                    'is_deletable' => false,
                    'activa' => true,
                ]);

                Caja::create([
                    'nombre' => 'Caja Principal',
                    'sucursal_id' => $sucursal->id,
                    'activa' => true,
                    'is_deletable' => false,
                ]);

                $metodosPago = [
                    ['nombre' => 'Efectivo', 'icono' => 'efectivo', 'color' => '#22C55E', 'is_deletable' => false, 'requiere_referencia' => false],
                    ['nombre' => 'Tarjeta de Crédito', 'icono' => 'tarjeta', 'color' => '#3B82F6', 'is_deletable' => true, 'requiere_referencia' => true],
                    ['nombre' => 'Tarjeta de Débito', 'icono' => 'tarjeta', 'color' => '#6366F1', 'is_deletable' => true, 'requiere_referencia' => true],
                    ['nombre' => 'Transferencia', 'icono' => 'transferencia', 'color' => '#A855F7', 'is_deletable' => true, 'requiere_referencia' => true],
                ];
                foreach ($metodosPago as $metodo) {
                    MetodoPago::create([...$metodo, 'activo' => true]);
                }

                $user = User::create([
                    'name' => $request->empresa,
                    'email' => $request->email,
                    'password' => bcrypt($request->password),
                    'role' => 'admin',
                    'sucursal_id' => $sucursal->id,
                    'is_deletable' => false,
                    'is_seller' => false,
                ]);

                ListaPrecio::create([
                    'nombre' => 'Base',
                    'activo' => true,
                ]);

                $token = $user->createToken('ventro-app')->plainTextToken;

                return ['user' => $user, 'token' => $token];
            });
        } catch (\Throwable $e) {
            tenancy()->end();
            $tenant->domains()->delete();
            TenantUser::where('tenant_id', $tenant->id)->delete();
            TenantSubscription::where('tenant_id', $tenant->id)->delete();
            $tenant->delete();
            throw $e;
        }

        tenancy()->end();

        return response()->json([
            'token' => $data['token'],
            'tenant_id' => $tenant->id,
            'domain' => $domain,
            'user' => [
                'id' => $data['user']->id,
                'name' => $data['user']->name,
                'email' => $data['user']->email,
                'role' => $data['user']->role,
                'is_deletable' => $data['user']->is_deletable,
            ],
            'onboarding_complete' => false,
        ], 201);
    }
}
