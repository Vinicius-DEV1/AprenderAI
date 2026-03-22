<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'base_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the full tracking URL.
     */
    public function getTrackingUrlAttribute(): string
    {
        $baseUrl = $this->base_url;
        if (!str_starts_with($baseUrl, 'http')) {
            $baseUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5174'), '/') . '/' . ltrim($baseUrl, '/');
        }

        $params = [
            'utm_source' => $this->utm_source,
            'utm_medium' => $this->utm_medium,
            'utm_campaign' => $this->utm_campaign,
            'utm_term' => $this->utm_term,
            'utm_content' => $this->utm_content,
        ];

        $queryString = http_build_query(array_filter($params));

        return $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . $queryString;
    }

    protected $appends = ['tracking_url'];
}
