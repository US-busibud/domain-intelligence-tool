<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scan extends Model
{
    protected $fillable = [
        'original_input',
        'normalized_domain',
        'brand_name',
    ];

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }
}