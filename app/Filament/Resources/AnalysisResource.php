<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AnalysisResource\Pages;
use App\Models\Analysis;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AnalysisResource extends Resource
{
    protected static ?string $model = Analysis::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'name';

    /** Status -> Filament badge color. */
    public static function statusColor(string $status): string
    {
        return match ($status) {
            'revealed'   => 'success',
            'ready'      => 'warning',   // done, but still held behind reveal_at
            'processing' => 'info',
            'queued'     => 'gray',
            'failed'     => 'danger',
            default      => 'gray',      // draft
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.email')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->limit(28),

                Tables\Columns\TextColumn::make('name')
                    ->label('Test')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => static::statusColor($state))
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('user_facing')
                    ->label('User sees')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Completed'   => 'success',
                        'Processing'  => 'warning',
                        'Needs retry' => 'danger',
                        default       => 'gray',
                    }),

                Tables\Columns\TextColumn::make('audience')
                    ->label('Audience')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('photo_count')
                    ->label('Photos')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('attempts')
                    ->label('Tries')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (int $state) => $state >= (int) env('AI_MAX_ATTEMPTS', 3) ? 'danger' : 'gray')
                    ->formatStateUsing(fn (int $state) => $state.'/'.(int) env('AI_MAX_ATTEMPTS', 3))
                    ->tooltip('Processing attempts. At the cap, auto-retry stops and it waits for a manual Re-queue.')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('best_pct')
                    ->label('Top %')
                    ->formatStateUsing(fn ($state) => $state !== null ? $state . '%' : '—')
                    ->alignEnd()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reveal_at')
                    ->label('Reveals')
                    ->dateTime('M j, Y H:i')
                    ->description(fn (Analysis $r) => $r->reveal_at && $r->reveal_at->isFuture()
                        ? 'in ' . $r->reveal_at->diffForHumans(null, true)
                        : null)
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft'      => 'Draft (unpaid)',
                        'queued'     => 'Queued',
                        'processing' => 'Processing',
                        'ready'      => 'Ready (held)',
                        'revealed'   => 'Revealed',
                        'failed'     => 'Failed (AI error)',
                    ]),
                Tables\Filters\Filter::make('held_back')
                    ->label('Held: AI done, not yet revealed')
                    ->query(fn (Builder $q) => $q->where('status', 'ready')->where('reveal_at', '>', now())),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                // Force-reveal: skip the remaining delay for support cases.
                Tables\Actions\Action::make('reveal')
                    ->label('Reveal now')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('This makes the report immediately visible to the user, skipping the remaining delay.')
                    ->visible(fn (Analysis $r) => in_array($r->status, ['queued', 'processing', 'ready']))
                    ->action(function (Analysis $r) {
                        $r->update(['status' => 'revealed', 'reveal_at' => now()]);
                        Notification::make()->title('Report revealed')->success()->send();
                    }),

                // Re-queue a failed analysis so the worker picks it up again.
                Tables\Actions\Action::make('requeue')
                    ->label('Re-queue')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Analysis $r) => $r->status === 'failed')
                    ->action(function (Analysis $r) {
                        // Reset the counter so auto-retry gets a fresh set of tries.
                        $r->update(['status' => 'queued', 'error' => null, 'attempts' => 0, 'last_tried_at' => null]);
                        Notification::make()->title('Re-queued for processing')->success()->send();
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnalyses::route('/'),
            'view'  => Pages\ViewAnalysis::route('/{record}'),
        ];
    }
}
