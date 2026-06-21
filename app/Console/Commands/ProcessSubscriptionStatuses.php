<?php

namespace App\Console\Commands;

use App\Models\TenantSubscription;
use Illuminate\Console\Command;

class ProcessSubscriptionStatuses extends Command
{
    protected $signature = 'subscriptions:process';

    protected $description = 'Procesa y actualiza estados de suscripciones';

    public function handle(): void
    {
        $this->processTrialExpired();
        $this->processBlockedToCancel();
        $this->processPastDue();

        $this->info('Suscripciones procesadas correctamente.');
    }

    // ─── Trial expirado → 7 días de gracia → blocked ─────────────────────────
    private function processTrialExpired(): void
    {
        // Trial vencido hace más de 7 días → blocked
        TenantSubscription::where('status', 'trial')
            ->where('is_blocked', false)
            ->where('trial_ends_at', '<', now()->subDays(7))
            ->each(function ($sub) {
                $sub->update([
                    'is_blocked' => true,
                    'blocked_at' => now(),
                    'blocked_reason' => 'Trial expirado sin pago',
                ]);

                $this->info("Bloqueado: {$sub->tenant_id} — trial expirado");

                // TODO: enviar email de cuenta bloqueada
            });

        // Trial vencido (dentro de los 7 días de gracia) → advertencia
        TenantSubscription::where('status', 'trial')
            ->where('is_blocked', false)
            ->where('trial_ends_at', '<', now())
            ->where('trial_ends_at', '>=', now()->subDays(7))
            ->each(function ($sub) {
                $this->info("En gracia: {$sub->tenant_id} — trial expirado hace ".now()->diffInDays($sub->trial_ends_at).' días');

                // TODO: enviar email de recordatorio de pago
            });
    }

    // ─── Blocked hace más de 30 días → cancelled ──────────────────────────────
    private function processBlockedToCancel(): void
    {
        TenantSubscription::where('is_blocked', true)
            ->where('status', '!=', 'cancelled')
            ->where('blocked_at', '<', now()->subDays(30))
            ->each(function ($sub) {
                $sub->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancel_reason' => 'Cuenta bloqueada sin pago por 30 días',
                ]);

                $this->info("Cancelado: {$sub->tenant_id}");

                // TODO: enviar email de cuenta cancelada
            });
    }

    // ─── Próximo pago vencido → past_due con 7 días de gracia ────────────────
    private function processPastDue(): void
    {
        // Activos con pago vencido → past_due
        TenantSubscription::where('status', 'active')
            ->where('next_billing_at', '<', now())
            ->each(function ($sub) {
                $sub->update(['status' => 'past_due']);

                $this->info("Past due: {$sub->tenant_id}");

                // TODO: enviar email de pago vencido
            });

        // Past due hace más de 7 días → blocked
        TenantSubscription::where('status', 'past_due')
            ->where('is_blocked', false)
            ->where('next_billing_at', '<', now()->subDays(7))
            ->each(function ($sub) {
                $sub->update([
                    'is_blocked' => true,
                    'blocked_at' => now(),
                    'blocked_reason' => 'Pago vencido sin regularizar',
                ]);

                $this->info("Bloqueado por past_due: {$sub->tenant_id}");

                // TODO: enviar email de cuenta bloqueada por falta de pago
            });
    }
}
