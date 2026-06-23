<?php

namespace App\Filament\Widgets;

use App\Models\Analysis;
use Filament\Widgets\ChartWidget;

class AnalysesStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Analyses by status';
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $statuses = ['draft', 'queued', 'processing', 'ready', 'revealed', 'failed'];
        $counts = Analysis::query()
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return [
            'datasets' => [[
                'label' => 'Analyses',
                'data'  => array_map(fn ($s) => (int) ($counts[$s] ?? 0), $statuses),
                'backgroundColor' => [
                    '#8A8C92', // draft
                    '#A6A8AF', // queued
                    '#4A40A8', // processing
                    '#E0A23C', // ready (held)
                    '#3F8F6B', // revealed
                    '#C45B52', // failed
                ],
            ]],
            'labels' => ['Draft', 'Queued', 'Processing', 'Ready (held)', 'Revealed', 'Failed'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
