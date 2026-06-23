<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Photo extends Model
{
    protected $table = 'photos';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'position'   => 'integer',
        'created_at' => 'datetime',
    ];

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class, 'analysis_id', 'id');
    }
}
