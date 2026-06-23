<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * The app's end users (the men who upload photos). For now this maps to a local
 * MySQL "users" table; when you switch to Supabase, point $table at 'auth.users'
 * and the pgsql connection.
 *
 * Authenticatable + HasApiTokens so the Next.js frontend can register/login and
 * receive a Sanctum bearer token. Managed by admins via the Users resource.
 */
class AuthUser extends Authenticatable
{
    use HasApiTokens;
    use Notifiable;

    protected $table = 'users';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'created_at'         => 'datetime',
        'last_sign_in_at'    => 'datetime',
        'email_confirmed_at' => 'datetime',
        'raw_user_meta_data' => 'array',
        'password'           => 'hashed',
    ];

    public function analyses(): HasMany
    {
        return $this->hasMany(Analysis::class, 'user_id', 'id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'user_id', 'id');
    }
}
