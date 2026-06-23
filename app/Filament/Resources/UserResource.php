<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\AuthUser;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = AuthUser::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'People';
    protected static ?string $modelLabel = 'user';
    protected static ?string $pluralModelLabel = 'users';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'email';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('analyses');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Profile')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Display name')
                        ->maxLength(120),
                    Forms\Components\TextInput::make('email')
                        ->email()->required()
                        ->unique(ignoreRecord: true),
                    Forms\Components\Toggle::make('email_verified')
                        ->label('Email verified')
                        ->default(false)
                        // Initialise from the timestamp; pages convert it back.
                        ->afterStateHydrated(function (Forms\Components\Toggle $component, ?AuthUser $record) {
                            $component->state((bool) ($record?->email_confirmed_at));
                        }),
                ]),

            Forms\Components\Section::make('Password')
                ->description('Leave blank when editing to keep the current password.')
                ->schema([
                    Forms\Components\TextInput::make('password')
                        ->password()->revealable()
                        ->required(fn (string $operation) => $operation === 'create')
                        ->minLength(8)
                        // Only save when filled; the model's "hashed" cast hashes it.
                        ->dehydrated(fn ($state) => filled($state)),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Name')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable()->copyable()->sortable(),
                Tables\Columns\TextColumn::make('analyses_count')->label('Analyses')->alignCenter()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Joined')->dateTime('M j, Y')->sortable(),
                Tables\Columns\TextColumn::make('last_sign_in_at')->label('Last sign-in')
                    ->dateTime('M j, Y H:i')->placeholder('never')->sortable(),
                Tables\Columns\IconColumn::make('email_confirmed_at')->label('Verified')->boolean()
                    ->getStateUsing(fn (AuthUser $r) => filled($r->email_confirmed_at)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->modalHeading('Delete user and all their data')
                    ->modalDescription('This cascades to every analysis, photo and payment they own. This cannot be undone.'),
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
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
