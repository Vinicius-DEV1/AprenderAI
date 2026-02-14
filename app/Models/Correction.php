<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Correction extends Model
{
    use HasFactory;

    protected $fillable = [
        'correctable_type',
        'correctable_id',
        'ai_provider',
        'ai_model',
        'tokens_used',
        'correction_data',
        'corrected_at',
    ];

    protected $casts = [
        'correction_data' => 'array',
        'corrected_at' => 'datetime',
    ];

    public function correctable()
    {
        return $this->morphTo();
    }
}
