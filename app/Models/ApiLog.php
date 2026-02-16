<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'api_key_id',
        'provider',
        'type',
        'status_code',
        'message',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function apiKey()
    {
        return $this->belongsTo(ApiKey::class);
    }
}
