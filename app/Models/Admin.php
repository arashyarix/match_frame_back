<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Panel administrators. Kept in the app's OWN default connection (SQLite by
 * default — see config/database.php), completely separate from the Supabase
 * end users in auth.users. This keeps admin access independent and avoids
 * cluttering the Supabase database with framework tables.
 */
class Admin extends Authenticatable implements FilamentUser
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Every row in this table is an admin. Tighten here if you add roles.
        return true;
    }
}
