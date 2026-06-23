<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Choose which engine generates reports and store its API key. Keys are
 * encrypted at rest. Leaving a key field blank keeps the previously saved key.
 */
class AiSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'AI provider';
    protected static ?string $title = 'Report engine';
    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.ai-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $s = AppSetting::singleton();
        $this->form->fill([
            'ai_provider'     => $s->aiProvider(),
            'anthropic_model' => $s->anthropic_model ?? 'claude-sonnet-4-6',
            'openai_model'    => $s->openai_model ?? 'gpt-4o',
            // Don't surface stored secrets; blank means "keep existing".
            'anthropic_api_key' => null,
            'openai_api_key'    => null,
        ]);
    }

    public function form(Form $form): Form
    {
        $s = AppSetting::singleton();
        $hasAnthropic = (bool) $this->safeFilled(fn () => $s->anthropic_api_key);
        $hasOpenai = (bool) $this->safeFilled(fn () => $s->openai_api_key);

        return $form
            ->schema([
                Section::make('Active engine')
                    ->description('Which engine produces the photo report. "Built-in" needs no key and is great for testing.')
                    ->schema([
                        Select::make('ai_provider')
                            ->label('Provider')
                            ->live()
                            ->options([
                                'mock'      => 'Built-in (static, no key)',
                                'anthropic' => 'Claude — Anthropic',
                                'openai'    => 'ChatGPT — OpenAI',
                            ])
                            ->required(),
                    ]),

                Section::make('Claude (Anthropic)')
                    ->visible(fn (Get $get) => $get('ai_provider') === 'anthropic')
                    ->schema([
                        TextInput::make('anthropic_api_key')
                            ->label('Anthropic API key')
                            ->password()->revealable()->autocomplete(false)
                            ->placeholder($hasAnthropic ? '•••••••• (saved — leave blank to keep)' : 'sk-ant-...')
                            ->helperText('Get it from console.anthropic.com → API Keys.'),
                        TextInput::make('anthropic_model')
                            ->label('Model')
                            ->placeholder('claude-sonnet-4-6')
                            ->helperText('A vision-capable Claude model ID, e.g. claude-sonnet-4-6, claude-opus-4-8, or claude-haiku-4-5-20251001. Do NOT use a "-latest" suffix. Copy the exact ID your account has from console.anthropic.com.'),
                    ]),

                Section::make('ChatGPT (OpenAI)')
                    ->visible(fn (Get $get) => $get('ai_provider') === 'openai')
                    ->schema([
                        TextInput::make('openai_api_key')
                            ->label('OpenAI API key')
                            ->password()->revealable()->autocomplete(false)
                            ->placeholder($hasOpenai ? '•••••••• (saved — leave blank to keep)' : 'sk-...')
                            ->helperText('Get it from platform.openai.com → API keys.'),
                        TextInput::make('openai_model')
                            ->label('Model')
                            ->placeholder('gpt-4o')
                            ->helperText('Any vision-capable GPT model (e.g. gpt-4o).'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $s = AppSetting::singleton();

        $update = [
            'ai_provider'     => $data['ai_provider'] ?? 'mock',
            'anthropic_model' => $data['anthropic_model'] ?? null,
            'openai_model'    => $data['openai_model'] ?? null,
            'updated_at'      => now(),
        ];

        // Only overwrite a key when a new value was entered (blank = keep).
        if (filled($data['anthropic_api_key'] ?? null)) {
            $update['anthropic_api_key'] = $data['anthropic_api_key'];
        }
        if (filled($data['openai_api_key'] ?? null)) {
            $update['openai_api_key'] = $data['openai_api_key'];
        }

        $s->update($update);

        // Reset the key fields so they stay blank (= keep) after saving.
        $this->form->fill([
            'ai_provider'       => $s->aiProvider(),
            'anthropic_model'   => $s->anthropic_model ?? 'claude-sonnet-4-6',
            'openai_model'      => $s->openai_model ?? 'gpt-4o',
            'anthropic_api_key' => null,
            'openai_api_key'    => null,
        ]);

        $provider = $update['ai_provider'];
        $note = $provider === 'mock'
            ? 'Reports will use the built-in engine.'
            : ($s->aiReady()
                ? 'Reports will be generated by '.($provider === 'anthropic' ? 'Claude' : 'ChatGPT').'.'
                : 'Provider selected, but no API key is saved yet — add one to activate it.');

        Notification::make()->title('AI settings saved')->body($note)->success()->send();
    }

    /** filled() on an encrypted attribute, guarded against decrypt errors. */
    private function safeFilled(callable $get): bool
    {
        try {
            return filled($get());
        } catch (\Throwable $e) {
            return false;
        }
    }
}
