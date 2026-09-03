<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UsersByTierOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $counts = User::query()
            ->selectRaw('tier, count(*) as total')
            ->groupBy('tier')
            ->pluck('total', 'tier');

        return [
            Stat::make('Start', $counts->get('start', 0))
                ->description('Plano de entrada')
                ->color('gray'),
            Stat::make('CLUB', $counts->get('club', 0))
                ->description('Plano pago')
                ->color('success'),
            Stat::make('Mentor', $counts->get('mentor', 0))
                ->description('Equipe de mentoria')
                ->color('info'),
        ];
    }
}
