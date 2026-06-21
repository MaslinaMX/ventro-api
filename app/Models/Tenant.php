<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    public static function getCustomColumns(): array
    {
        return [
            'id', 'name', 'razon_social', 'logo', 'email',
            'plan', 'status', 'owner_name', 'slug',
        ];
    }

    protected $casts = [
        'data' => 'array',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(TenantSubscription::class, 'tenant_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────
    public function isOnTrial(): bool
    {
        return $this->subscription?->status === 'trial'
            && $this->subscription?->trial_ends_at?->isFuture();
    }

    public function trialDaysLeft(): int
    {
        if (! $this->isOnTrial()) {
            return 0;
        }

        return (int) now()->diffInDays($this->subscription->trial_ends_at);
    }

    public function isBlocked(): bool
    {
        return $this->subscription?->is_blocked ?? false;
    }
}
