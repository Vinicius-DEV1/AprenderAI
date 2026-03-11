<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConceptRelation extends Model
{
    protected $fillable = [
        'concept_id',
        'related_id',
        'relation_type',
        'weight',
    ];

    protected $casts = [
        'weight' => 'float',
    ];

    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class, 'concept_id');
    }

    public function related(): BelongsTo
    {
        return $this->belongsTo(Concept::class, 'related_id');
    }

    /**
     * Alias for 'related' — named explicitly for readability in eager loads.
     * Used by IndexConceptVectorJob to fetch related concept names.
     */
    public function relatedConcept(): BelongsTo
    {
        return $this->belongsTo(Concept::class, 'related_id');
    }
}
