<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    public function show(): JsonResponse
    {
        $tenant = tenancy()->tenant;
        $tenant->load('subscription');
        $sub = $tenant->subscription;

        if (! $sub) {
            return response()->json(['message' => 'Sin suscripción'], 404);
        }

        return response()->json([
            // ─── Tenant ───────────────────────────────────────────────────────
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'email' => $tenant->email,
                'razon_social' => $tenant->razon_social,
                'logo' => $tenant->logo,
            ],

            // ─── Titular ──────────────────────────────────────────────────────
            'owner' => [
                'name' => $tenant->owner_name,
                'email' => $tenant->email,
            ],

            // ─── Suscripción ──────────────────────────────────────────────────
            'subscription' => [
                'plan' => $sub->plan,
                'status' => $sub->status,
                'period' => $sub->period,
                'base_price' => $sub->base_price,
                'currency' => $sub->currency,
                'included_branches' => $sub->included_branches,
                'extra_branch_cost' => $sub->extra_branch_cost,
                'trial_ends_at' => $sub->trial_ends_at?->toDateString(),
                'next_billing_at' => $sub->next_billing_at?->toDateString(),
                'days_left' => $sub->daysUntilTrialEnds(),
                'is_trial_expired' => $sub->isTrialExpired(),
                'extra_branches' => $sub->extraBranches(),
                'total_monthly' => $sub->totalMonthly(),
            ],

            // ─── Facturación ──────────────────────────────────────────────────
            'billing' => [
                'status' => $sub->is_blocked
                    ? 'blocked'
                    : ($sub->status === 'past_due' ? 'past_due' : 'current'),
                'is_blocked' => $sub->is_blocked,
                'next_charge' => $sub->status === 'trial'
                    ? 'Se cobrará al finalizar el periodo de prueba'
                    : ($sub->next_billing_at?->toDateString() ?? 'N/A'),
            ],

            // ─── Método de pago ───────────────────────────────────────────────
            'payment_method' => [
                'method' => $sub->payment_method,
                'clabe' => $sub->spei_clabe,
                'bank' => $sub->spei_bank,
                'beneficiary' => $sub->spei_beneficiary,
                'reference' => $sub->spei_reference,
            ],

            // ─── Cancelación ──────────────────────────────────────────────────
            'cancellation' => [
                'cancelled_at' => $sub->cancelled_at?->toDateString(),
                'cancel_reason' => $sub->cancel_reason,
            ],

            // ─── Meta ─────────────────────────────────────────────────────────
            'created_at' => $tenant->created_at?->toDateString(),
        ]);
    }
}
