<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.email')
                    ->label('User')->searchable()->limit(28)->copyable(),

                Tables\Columns\TextColumn::make('amount_display')
                    ->label('Amount')->alignEnd(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'paid'     => 'success',
                        'refunded' => 'warning',
                        'failed'   => 'danger',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),

                Tables\Columns\TextColumn::make('stripe_session_id')
                    ->label('Stripe session')
                    ->limit(20)->copyable()->placeholder('— (dev)')->toggleable(),

                Tables\Columns\TextColumn::make('analysis_id')
                    ->label('Analysis')->limit(12)->copyable()->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')->dateTime('M j, Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'paid'     => 'Paid',
                    'refunded' => 'Refunded',
                    'failed'   => 'Failed',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
        ];
    }

    // Read-only resource.
    public static function canCreate(): bool
    {
        return false;
    }
}
