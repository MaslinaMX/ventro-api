<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;

class AuthenticateTenant
{
    public function handle(Request $request, Closure $next)
    {
        $bearerToken = $request->bearerToken();

        if (! $bearerToken) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $token = PersonalAccessToken::findToken($bearerToken);

        if (! $token) {
            return response()->json(['message' => 'Token inválido.'], 401);
        }

        // Setear el usuario autenticado
        $user = $token->tokenable;
        auth()->guard('sanctum')->setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
