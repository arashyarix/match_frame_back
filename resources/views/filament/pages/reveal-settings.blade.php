<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Save reveal window
            </x-filament::button>
        </div>
    </form>

    <x-filament::section class="mt-8" heading="How it works" icon="heroicon-o-information-circle">
        <ul class="list-disc space-y-1 ps-5 text-sm text-gray-500 dark:text-gray-400">
            <li>The reveal window is also configurable via the app's env vars
                (<code>REVEAL_MIN_HOURS</code> / <code>REVEAL_MAX_HOURS</code>); this row overrides them when present.</li>
            <li>Each paid analysis gets a random <code>reveal_at</code> within the window, so timing feels organic.</li>
            <li>Changing the window affects <strong>future</strong> payments only — analyses already in the queue keep their assigned reveal time.</li>
            <li>Need to release one early? Use <em>Reveal now</em> on the Analyses screen.</li>
        </ul>
    </x-filament::section>
</x-filament-panels::page>
