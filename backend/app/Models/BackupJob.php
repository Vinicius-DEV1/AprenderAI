<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupJob extends Model
{
    protected $fillable = [
        'triggered_by',
        'status',
        's3_bucket',
        's3_key',
        'file_size_bytes',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'file_size_bytes' => 'integer',
    ];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    /**
     * Duration in seconds (null if not completed yet).
     */
    public function getDurationSecondsAttribute(): ?int
    {
        if ($this->started_at && $this->completed_at) {
            return $this->started_at->diffInSeconds($this->completed_at);
        }
        return null;
    }

    /**
     * Human-readable file size (e.g. "125 MB").
     */
    public function getFileSizeHumanAttribute(): ?string
    {
        if ($this->file_size_bytes === null)
            return null;

        $bytes = $this->file_size_bytes;
        if ($bytes >= 1073741824)
            return round($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576)
            return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)
            return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }

    protected $appends = ['duration_seconds', 'file_size_human'];
}
