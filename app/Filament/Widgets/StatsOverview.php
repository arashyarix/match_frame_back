<?php

namespace App\Filament\Widgets;

use App\Models\Analysis;
use App\Models\AppSetting;
use App\Models\AuthUser;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $users     = AuthUser::count();
        $analyses  = Analysis::count();
        $revealed  = Analysis::where('status', 'revealed')->count();
        $inFlight  = Analysis::whereIn('status', ['queued', 'processing', 'ready'])->count();
        $failed    = Analysis::where('status', 'failed')->count();
        $revenue   = (int) Payment::where('status', 'paid')->sum('amount_cents');

        $s = AppSetting::singleton();
        $window = rtrim(rtrim((string) $s->reveal_min_hours, '0'), '.')
            . '–' . rtrim(rtrim((string) $s->reveal_max_hours, '0'), '.') . 'h';

        return [
            Stat::make('Users', number_format($users))
                ->description('Registered accounts')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Analyses', number_format($analyses))
                ->description($revealed . ' revealed · ' . $inFlight . ' in progress')
                ->descriptionIcon('heroicon-m-photo')
                ->color('primary'),

            Stat::make('Revenue', '$' . number_format($revenue / 100, 2))
                ->description('All paid analyses')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('AI errors', number_format($failed))
                ->description($failed > 0 ? 'Need a re-queue' : 'All clear')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($failed > 0 ? 'danger' : 'gray'),

            Stat::make('Reveal window', $window)
                ->description('Delay before results show')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
        ];
    }
}
