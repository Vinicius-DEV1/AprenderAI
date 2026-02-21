<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiProcessingBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'model',
        'type',
        'total_count',
        'processed_count',
        'error_count',
        'status',
        'errors_log',
    ];

    protected $casts = [
        'errors_log' => 'array',
        'total_count' => 'integer',
        'processed_count' => 'integer',
        'error_count' => 'integer',
    ];

    /**
     * Scope para buscar por batch_id
     */
    public function scopeByBatchId($query, $batchId)
    {
        return $query->where('batch_id', $batchId);
    }
}
