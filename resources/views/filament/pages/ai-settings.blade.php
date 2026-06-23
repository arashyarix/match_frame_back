<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Save AI settings
            </x-filament::button>
        </div>
    </form>

    <x-filament::section class="mt-8" heading="How it works" icon="heroicon-o-information-circle">
        <ul class="list-disc space-y-1 ps-5 text-sm text-gray-500 dark:text-gray-400">
            <li>After payment, the worker sends the user's photos to the selected engine and stores the returned report (still hidden until the reveal time).</li>
            <li>Keys are encrypted at rest. Leave a key field blank to keep the one already saved.</li>
            <li>If the selected engine errors, that analysis is marked <strong>failed</strong>; re-queue it from the Analyses screen.</li>
            <li>The report text never mentions AI — it reads as audience feedback.</li>
        </ul>
    </x-filament::section>
</x-filament-panels::page>
