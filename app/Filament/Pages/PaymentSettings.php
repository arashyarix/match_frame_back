<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Manage Stripe keys + pricing. Stored in app_settings (secrets encrypted at
 * rest). The API reads these for checkout + webhook verification, and exposes
 * the publishable key + price to the frontend via GET /api/config.
 */
class PaymentSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Stripe & pricing';
    protected static ?string $title = 'Stripe & pricing';
    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.payment-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $s = AppSetting::singleton();
        $this->form->fill([
            'stripe_enabled'         => (bool) $s->stripe_enabled,
            'stripe_publishable_key' => $s->stripe_publishable_key,
            'stripe_secret_key'      => $s->stripe_secret_key,
            'stripe_webhook_secret'  => $s->stripe_webhook_secret,
            'price_dollars'          => number_format(((int) $s->price_cents) / 100, 2, '.', ''),
            'currency'               => $s->currency ?: 'usd',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Stripe keys')
                    ->description('Get these from your Stripe dashboard → Developers → API keys. Secrets are encrypted at rest and never sent to the browser.')
                    ->schema([
                        Toggle::make('stripe_enabled')
                            ->label('Enable Stripe payments')
                            ->helperText('When off, the API falls back to dev mode (marks paid without charging).'),
                        TextInput::make('stripe_publishable_key')
                            ->label('Publishable key')
                            ->placeholder('pk_live_… or pk_test_…')
                            ->prefixIcon('heroicon-m-key'),
                        TextInput::make('stripe_secret_key')
                            ->label('Secret key')
                            ->placeholder('sk_live_… or sk_test_…')
                            ->password()->revealable()
                            ->prefixIcon('heroicon-m-lock-closed'),
                        TextInput::make('stripe_webhook_secret')
                            ->label('Webhook signing secret')
                            ->placeholder('whsec_…')
                            ->password()->revealable()
                            ->prefixIcon('heroicon-m-lock-closed')
                            ->helperText('Endpoint: POST {your-api-domain}/api/stripe/webhook · Event: checkout.session.completed'),
                    ]),

                Section::make('Pricing')
                    ->columns(2)
                    ->schema([
                        TextInput::make('price_dollars')
                            ->label('Price per analysis')
                            ->numeric()->required()->minValue(0)->step(0.01)
                            ->prefix('$'),
                        TextInput::make('currency')
                            ->label('Currency')
                            ->required()->maxLength(8)
                            ->helperText('ISO code, e.g. usd, eur.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        AppSetting::singleton()->update([
            'stripe_enabled'         => (bool) $data['stripe_enabled'],
            'stripe_publishable_key' => $data['stripe_publishable_key'] ?: null,
            'stripe_secret_key'      => $data['stripe_secret_key'] ?: null,
            'stripe_webhook_secret'  => $data['stripe_webhook_secret'] ?: null,
            'price_cents'            => (int) round(((float) $data['price_dollars']) * 100),
            'currency'               => strtolower(trim($data['currency'])),
            'updated_at'             => now(),
        ]);

        Notification::make()->title('Payment settings saved')->success()->send();
    }
}
