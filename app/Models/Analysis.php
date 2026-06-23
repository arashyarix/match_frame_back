<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Maps to the MatchFrame "analyses" table.
 *
 * Uses the app's default connection (MySQL for now — see config/database.php).
 * When you move to Supabase/Postgres later, just change DB_CONNECTION; no model
 * changes are needed.
 */
class Analysis extends Model
{
    protected $table = 'analyses';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false; // table has created_at but no updated_at

    protected $guarded = [];

    protected $casts = [
        'report'        => 'array',
        'photo_count'   => 'integer',
        'attempts'      => 'integer',
        'created_at'    => 'datetime',
        'paid_at'       => 'datetime',
        'processed_at'  => 'datetime',
        'reveal_at'     => 'datetime',
        'last_tried_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'user_id', 'id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class, 'analysis_id', 'id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'analysis_id', 'id');
    }

    /** Vote % of the top-ranked photo, if the report exists. */
    public function getBestPctAttribute(): ?int
    {
        return $this->report['results'][0]['pct'] ?? null;
    }

    /**
     * What the *end user* is allowed to see — mirrors the Next.js
     * userFacingStatus(). Internally "ready" means the AI finished but the
     * report is still held behind reveal_at.
     */
    public function getUserFacingAttribute(): string
    {
        if ($this->status === 'failed') {
            return 'Needs retry';
        }
        if ($this->status === 'draft') {
            return 'Not paid';
        }
        if ($this->status === 'revealed'
            || ($this->status === 'ready' && $this->reveal_at && $this->reveal_at->isPast())) {
            return 'Completed';
        }
        return 'Processing';
    }
}
