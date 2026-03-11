<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionVector extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'question_id';
    public $incrementing = false;

    protected $fillable = [
        'question_id',
        'qdrant_id',
        'embedding_hash',
        'index_version',
        'pipeline_version',
        'indexed_at',
    ];

    protected $casts = [
        'index_version' => 'integer',
        'indexed_at'    => 'datetime',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * Returns true if the given text hash differs from the stored one.
     * If they match, re-indexing can be safely skipped (zero API cost).
     */
    public function hasContentChanged(string $newHash): bool
    {
        return $this->embedding_hash !== $newHash;
    }
}
