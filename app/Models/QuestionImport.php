<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionImport extends Model
{
    protected $fillable = [
        'batch_name',
        'original_filename',
        'uploaded_by',
        'total_questions',
        'pending_count',
        'approved_count',
        'status',
        'error_message',
    ];

    /**
     * Admin que realizou o upload do .zip.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Todos os itens (questões individuais) deste lote de importação.
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuestionImportItem::class, 'import_id');
    }
}
