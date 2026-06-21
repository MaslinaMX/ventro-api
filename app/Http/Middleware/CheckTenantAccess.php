<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckTenantAccess
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = tenancy()->tenant;
        if (! $tenant) {
            return $next($request);
        }

        $sub = $tenant->subscription;

        if (! $sub) {
            return $next($request);
        }

        // Bloqueado manualmente o por sistema
        if ($sub->is_blocked) {
            return response()->json([
                'message' => 'Tu cuenta está bloqueada.',
                'code' => 'ACCOUNT_BLOCKED',
                'reason' => $sub->blocked_reason,
            ], 403);
        }

        // Cancelado
        if ($sub->status === 'cancelled') {
            return response()->json([
                'message' => 'Tu suscripción ha sido cancelada.',
                'code' => 'ACCOUNT_CANCELLED',
            ], 403);
        }

        // Trial expirado — 7 días de gracia
        if ($sub->status === 'trial' && $sub->isTrialExpired()) {
            $daysOverdue = now()->diffInDays($sub->trial_ends_at);
            if ($daysOverdue > 7) {
                return response()->json([
                    'message' => 'Tu periodo de prueba ha expirado.',
                    'code' => 'TRIAL_EXPIRED',
                    'days_overdue' => $daysOverdue,
                ], 403);
            }
        }

        return $next($request);
    }
}
