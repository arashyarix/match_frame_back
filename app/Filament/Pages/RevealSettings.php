<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Edits the runtime delayed-reveal window stored in public.app_settings.
 *
 * The Next.js server + worker read this row to override their env defaults, so
 * changes here take effect on the next payment with no redeploy. Already-paid
 * analyses keep the reveal_at they were assigned at payment time.
 */
class RevealSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Reveal delay';
    protected static ?string $title = 'Delayed result reveal';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.reveal-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $s = AppSetting::singleton();
        $this->form->fill([
            'reveal_min_hours' => (float) $s->reveal_min_hours,
            'reveal_max_hours' => (float) $s->reveal_max_hours,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Reveal window')
                    ->description('After payment, each analysis is assigned a random reveal time between these two bounds. The AI runs immediately, but the report stays hidden until then. Set both to the same value for a fixed delay.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('reveal_min_hours')
                            ->label('Minimum delay (hours)')
                            ->numeric()->required()->minValue(0)->maxValue(168)->step(0.5)
                            ->suffix('h'),
                        TextInput::make('reveal_max_hours')
                            ->label('Maximum delay (hours)')
                            ->numeric()->required()->minValue(0)->maxValue(168)->step(0.5)
                            ->suffix('h')
                            ->rule('gte:reveal_min_hours')
                            ->validationMessages(['gte' => 'Maximum must be greater than or equal to minimum.']),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        AppSetting::singleton()->update([
            'reveal_min_hours' => (float) $data['reveal_min_hours'],
            'reveal_max_hours' => (float) $data['reveal_max_hours'],
            'updated_at'       => now(),
        ]);

        Notification::make()
            ->title('Reveal window updated')
            ->body("New analyses will reveal between {$data['reveal_min_hours']}h and {$data['reveal_max_hours']}h after payment.")
            ->success()
            ->send();
    }
}
