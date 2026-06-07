<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $tenantId = $request->header('X-Tenant-ID');

        if (! $tenantId) {
            return response()->json(['message' => 'Negocio no encontrado.'], 404);
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return response()->json(['message' => 'Negocio no encontrado.'], 404);
        }

        tenancy()->initialize($tenant);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            tenancy()->end();

            return response()->json(['message' => 'Credenciales incorrectas.'], 401);
        }

        $token = $user->createToken('ventro-app')->plainTextToken;
        tenancy()->end();

        return response()->json([
            'token' => $token,
            'tenant_id' => $tenant->id,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'role' => $user->role,
                'is_deletable' => $user->is_deletable,
                'employee_number' => $user->employee_number,
            ],
        ]);
    }
}
