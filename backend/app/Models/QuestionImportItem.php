<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionImportItem extends Model
{
    protected $fillable = [
        'import_id',
        'question_id',
        'approved_by',
        'approved_at',
        'reverted_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'reverted_at' => 'datetime',
    ];

    /**
     * O lote de importação ao qual este item pertence.
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(QuestionImport::class, 'import_id');
    }

    /**
     * A questão que este item referencia.
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * O admin que aprovou esta questão.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
