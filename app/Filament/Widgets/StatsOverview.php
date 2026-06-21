<?php

namespace App\Filament\Widgets;

use App\Models\Tenant;
use App\Models\TenantSubscription;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $total = Tenant::count();
        $trial = TenantSubscription::where('status', 'trial')->count();
        $active = TenantSubscription::where('status', 'active')->count();
        $blocked = TenantSubscription::where('is_blocked', true)->count();
        $mrr = TenantSubscription::whereIn('status', ['trial', 'active'])
            ->get()
            ->sum(fn ($sub) => $sub->totalMonthly());

        return [
            Stat::make('Total Tenants', $total)
                ->icon('heroicon-o-building-storefront')
                ->color('gray'),
            Stat::make('En Trial', $trial)
                ->icon('heroicon-o-clock')
                ->color('warning'),
            Stat::make('Activos', $active)
                ->icon('heroicon-o-check-circle')
                ->color('success'),
            Stat::make('Bloqueados', $blocked)
                ->icon('heroicon-o-lock-closed')
                ->color('danger'),
            Stat::make('MRR Estimado', '$'.number_format($mrr, 2).' MXN')
                ->icon('heroicon-o-currency-dollar')
                ->color('info'),
        ];
    }
}
