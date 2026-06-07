<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\ListaPrecio;
use App\Models\Sucursal;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;

class RegisterController extends Controller
{
    public function __invoke(Request $request)
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

        $tenant = Tenant::create([
            'id' => Str::uuid(),
            'name' => $request->empresa,
            'email' => $request->email,
            'plan' => 'basic',
            'status' => 'active',
        ]);

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

        tenancy()->initialize($tenant);

        try {
            $data = DB::transaction(function () use ($request) {
                $sucursal = Sucursal::create([
                    'nombre' => $request->empresa,
                    'activa' => true,
                ]);

                Caja::create([
                    'nombre' => 'Caja 1',
                    'sucursal_id' => $sucursal->id,
                    'activa' => true,
                ]);

                $user = User::create([
                    'name' => $request->empresa, // temporal, se actualiza en /me
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
