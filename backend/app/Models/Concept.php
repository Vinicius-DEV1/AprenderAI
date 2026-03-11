<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Concept extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'subject_id',
        'topic_id',
        'description',
        'aliases',
        'auto_detected',
        'qdrant_indexed_at',
    ];

    protected $casts = [
        'aliases'            => 'array',
        'auto_detected'      => 'boolean',
        'qdrant_indexed_at'  => 'datetime',
    ];

    // ─── Relations ──────────────────────────────────────────────────────────

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'question_concepts', 'concept_id', 'question_id')
            ->withPivot('confidence')
            ->withTimestamps();
    }

    public function relatedConcepts(): HasMany
    {
        return $this->hasMany(ConceptRelation::class, 'concept_id');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Returns all related concept IDs (for query expansion), ordered by weight DESC.
     */
    public function getRelatedConceptIds(int $limit = 5): array
    {
        return ConceptRelation::where('concept_id', $this->id)
            ->orderByDesc('weight')
            ->limit($limit)
            ->pluck('related_id')
            ->toArray();
    }

    /**
     * Returns all aliases as a flat array, including the concept name itself.
     */
    public function getAllTerms(): array
    {
        $terms = [$this->name];
        if (!empty($this->aliases)) {
            $terms = array_merge($terms, $this->aliases);
        }
        return array_unique($terms);
    }
}
