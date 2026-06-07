<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $record = TenantUser::with('tenant')
            ->where('email', $request->email)
            ->first();

        if (! $record) {
            return response()->json([
                'message' => 'No encontramos una cuenta con ese correo.',
            ], 404);
        }

        return response()->json([
            'tenant_id' => $record->tenant_id,
            'empresa' => $record->tenant->name,
        ]);
    }
}
