<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the first admin from env vars:
 *   ADMIN_NAME, ADMIN_EMAIL, ADMIN_PASSWORD
 * Run with:  php artisan db:seed --class=AdminSeeder
 * (Or just use:  php artisan make:filament-user  — but that targets the default
 *  User model; this seeder targets our Admin model + guard.)
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@matchframe.app');

        Admin::updateOrCreate(
            ['email' => $email],
            [
                'name'     => env('ADMIN_NAME', 'MatchFrame Admin'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'change-me-now')),
            ],
        );

        $this->command?->info("Admin ready: {$email}");
    }
}
