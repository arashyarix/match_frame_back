<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // We ship our own personal_access_tokens migration with uuidMorphs
        // (our users have UUID keys), so skip Sanctum's default bigint one.
        //Sanctum::ignoreMigrations();
    }

    public function boot(): void
    {
        //
    }
}
