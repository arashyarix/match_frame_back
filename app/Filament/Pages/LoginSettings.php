<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Google sign-in credentials. Client secret is encrypted at rest; leaving it
 * blank keeps the saved one.
 */
class LoginSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Sign-in (Google)';
    protected static ?string $title = 'Google sign-in';
    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.login-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $s = AppSetting::singleton();
        $this->form->fill([
            'google_enabled'       => (bool) $s->google_enabled,
            'google_client_id'     => $s->googleClientId(),
            'google_client_secret' => null, // blank = keep
        ]);
    }

    public function form(Form $form): Form
    {
        $s = AppSetting::singleton();
        $hasSecret = false;
        try {
            $hasSecret = filled($s->google_client_secret);
        } catch (\Throwable $e) {
        }

        return $form
            ->schema([
                Section::make('Google OAuth')
                    ->description('Create credentials at console.cloud.google.com → APIs & Services → Credentials → OAuth client ID (type: Web application).')
                    ->schema([
                        Toggle::make('google_enabled')
                            ->label('Enable "Continue with Google"'),

                        TextInput::make('google_client_id')
                            ->label('Client ID')
                            ->placeholder('xxxxx.apps.googleusercontent.com')
                            ->autocomplete(false),

                        TextInput::make('google_client_secret')
                            ->label('Client secret')
                            ->password()->revealable()->autocomplete(false)
                            ->placeholder($hasSecret ? '•••••••• (saved — leave blank to keep)' : 'GOCSPX-...'),

                        Placeholder::make('redirect_uri')
                            ->label('Authorized redirect URI (add this in Google Console)')
                            ->content(url('/api/auth/google/callback')),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $s = AppSetting::singleton();

        $update = [
            'google_enabled'   => (bool) ($data['google_enabled'] ?? false),
            'google_client_id' => $data['google_client_id'] ?: null,
            'updated_at'       => now(),
        ];
        if (filled($data['google_client_secret'] ?? null)) {
            $update['google_client_secret'] = $data['google_client_secret'];
        }

        $s->update($update);

        $this->form->fill([
            'google_enabled'       => (bool) $s->google_enabled,
            'google_client_id'     => $s->googleClientId(),
            'google_client_secret' => null,
        ]);

        $note = $s->googleReady()
            ? 'Google sign-in is live.'
            : 'Saved. Add a client ID, secret, and enable the toggle to activate it.';

        Notification::make()->title('Sign-in settings saved')->body($note)->success()->send();
    }
}
