<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit log for local backup downloads.
 *
 * Every time an admin downloads a local backup (SQL-only, full, or full+Qdrant),
 * one record is inserted here for security/compliance tracking.
 *
 * @property int         $id
 * @property int|null    $user_id
 * @property string|null $user_name     Snapshot of admin name at time of download
 * @property string|null $user_email    Snapshot of admin email at time of download
 * @property string|null $ip_address
 * @property string      $download_type sql_only | full_mysql_images | full_all
 * @property string|null $filename
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class BackupDownloadLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_name',
        'user_email',
        'ip_address',
        'download_type',
        'filename',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    /** The admin who triggered the download (may be null if user was deleted). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Human-readable label for the download_type field.
     * Used in JSX and API responses.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->download_type) {
            'sql_only'           => 'Somente MySQL (SQL)',
            'full_mysql_images'  => 'MySQL + Imagens',
            'full_all'           => 'MySQL + Imagens + Qdrant',
            default              => $this->download_type,
        };
    }
}
