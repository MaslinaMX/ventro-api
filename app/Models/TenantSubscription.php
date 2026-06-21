<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSubscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'plan', 'status', 'base_price', 'currency', 'period',
        'included_branches', 'extra_branch_cost',
        'trial_ends_at', 'next_billing_at',
        'payment_method', 'spei_clabe', 'spei_bank',
        'spei_beneficiary', 'spei_reference',
        'is_blocked', 'blocked_reason', 'blocked_at',
        'cancelled_at', 'cancel_reason',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'next_billing_at' => 'datetime',
        'blocked_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'is_blocked' => 'boolean',
        'base_price' => 'decimal:2',
        'extra_branch_cost' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────
    public function daysUntilTrialEnds(): int
    {
        if (! $this->trial_ends_at) {
            return 0;
        }

        return max(0, (int) now()->diffInDays($this->trial_ends_at, false));
    }

    public function isTrialExpired(): bool
    {
        return $this->status === 'trial'
            && $this->trial_ends_at?->isPast();
    }

    public function activeBranchCount(): int
    {
        return $this->tenant->run(fn () => Sucursal::where('activa', true)->count());
    }

    public function extraBranches(): int
    {
        return max(0, $this->activeBranchCount() - $this->included_branches);
    }

    public function totalMonthly(): float
    {
        return $this->base_price + ($this->extraBranches() * $this->extra_branch_cost);
    }
}
