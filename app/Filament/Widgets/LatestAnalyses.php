<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AnalysisResource;
use App\Models\Analysis;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestAnalyses extends BaseWidget
{
    protected static ?string $heading = 'Latest activity';
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(Analysis::query()->latest('created_at')->limit(8))
            ->columns([
                Tables\Columns\TextColumn::make('user.email')->label('User')->limit(28),
                Tables\Columns\TextColumn::make('name')->label('Test'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => AnalysisResource::statusColor($state))
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),
                Tables\Columns\TextColumn::make('photo_count')->label('Photos')->alignCenter(),
                Tables\Columns\TextColumn::make('created_at')->label('Created')->since(),
            ])
            ->paginated(false);
    }
}
