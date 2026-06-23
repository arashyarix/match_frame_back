<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $table = 'payments';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
        'created_at'   => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'user_id', 'id');
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class, 'analysis_id', 'id');
    }

    /** Human amount, e.g. "$12.00". */
    public function getAmountDisplayAttribute(): string
    {
        $symbol = strtolower($this->currency) === 'usd' ? '$' : '';
        return $symbol . number_format($this->amount_cents / 100, 2) . ($symbol ? '' : ' ' . strtoupper($this->currency));
    }
}
