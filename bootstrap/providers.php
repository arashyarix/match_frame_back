<?php

return [
    App\Providers\AppServiceProvider::class,
    // Registering this is what creates the /admin routes. Without it you get 404.
    App\Providers\Filament\AdminPanelProvider::class,
];
