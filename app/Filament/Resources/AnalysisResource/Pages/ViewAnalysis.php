<?php

namespace App\Filament\Resources\AnalysisResource\Pages;

use App\Filament\Resources\AnalysisResource;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewAnalysis extends ViewRecord
{
    protected static string $resource = AnalysisResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Overview')
                ->columns(3)
                ->schema([
                    TextEntry::make('user.email')->label('User')->copyable(),
                    TextEntry::make('name')->label('Test name'),
                    TextEntry::make('status')
                        ->badge()
                        ->color(fn (string $state) => AnalysisResource::statusColor($state))
                        ->formatStateUsing(fn (string $state) => ucfirst($state)),
                    TextEntry::make('audience'),
                    TextEntry::make('photo_count')->label('Photos'),
                    TextEntry::make('best_pct')->label('Top photo %')
                        ->formatStateUsing(fn ($state) => $state !== null ? $state . '%' : '—'),
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('paid_at')->dateTime()->placeholder('—'),
                    TextEntry::make('processed_at')->label('AI finished')->dateTime()->placeholder('—'),
                    TextEntry::make('reveal_at')->label('Reveals at')->dateTime()->placeholder('—')
                        ->helperText(fn ($record) => $record->reveal_at && $record->reveal_at->isFuture()
                            ? 'Held for ' . $record->reveal_at->diffForHumans(null, true) . ' more'
                            : null),
                ]),

            Section::make('AI error')
                ->visible(fn ($record) => filled($record->error))
                ->schema([
                    TextEntry::make('error')->label(false)->color('danger'),
                ]),

            Section::make('Generated report (raw)')
                ->collapsible()
                ->collapsed()
                ->visible(fn ($record) => filled($record->report))
                ->schema([
                    TextEntry::make('report_summary')
                        ->label('Summary')
                        ->state(fn ($record) => $record->report['summary'] ?? '—'),
                    TextEntry::make('report_json')
                        ->label('Full JSON')
                        ->state(fn ($record) => json_encode($record->report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                        ->fontFamily('mono')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
