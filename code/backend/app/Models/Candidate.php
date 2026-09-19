<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Candidate extends Model
{
    protected $fillable = [
        'scan_id',
        'variation_domain',
        'ownership_score',
        'ownership_classification',
        'ownership_reasons',
    ];

    protected $casts = [
        'ownership_score' => 'integer',
        'ownership_reasons' => 'array',
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }
}