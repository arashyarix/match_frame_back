<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Save sign-in settings
            </x-filament::button>
        </div>
    </form>

    <x-filament::section class="mt-8" heading="Setup" icon="heroicon-o-information-circle">
        <ol class="list-decimal space-y-1 ps-5 text-sm text-gray-500 dark:text-gray-400">
            <li>In Google Cloud Console, create an OAuth client ID (Web application).</li>
            <li>Add the redirect URI shown above to "Authorized redirect URIs".</li>
            <li>Paste the Client ID + secret here, enable the toggle, and save.</li>
            <li>The "Continue with Google" button then appears on the app's sign-in page.</li>
        </ol>
    </x-filament::section>
</x-filament-panels::page>
