<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class UserGrowthChart extends ChartWidget
{
    protected ?string $heading = 'Crescimento de usuários';

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $months = collect(range(5, 0))
            ->map(fn (int $monthsAgo) => now()->subMonths($monthsAgo)->startOfMonth());

        $createdAtByMonth = User::query()
            ->pluck('created_at')
            ->map(fn (Carbon $createdAt) => $createdAt->format('Y-m'))
            ->countBy();

        $usersBeforeWindow = User::query()
            ->where('created_at', '<', $months->first())
            ->count();

        $cumulative = $usersBeforeWindow;

        $counts = $months->map(function (Carbon $month) use ($createdAtByMonth, &$cumulative) {
            $cumulative += $createdAtByMonth->get($month->format('Y-m'), 0);

            return $cumulative;
        });

        return [
            'datasets' => [
                [
                    'label' => 'Total de usuários',
                    'data' => $counts->values()->all(),
                    'fill' => true,
                ],
            ],
            'labels' => $months->map(fn (Carbon $month) => $month->translatedFormat('M/y'))->all(),
        ];
    }
}
