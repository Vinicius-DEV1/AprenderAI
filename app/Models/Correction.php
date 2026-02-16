<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Correction extends Model
{
    use HasFactory;

    protected $fillable = [
        'correctable_type',
        'correctable_id',
        'ai_provider',
        'ai_model',
        'tokens_used', // Legacy
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'correction_data',
        'corrected_at',
    ];

    protected $casts = [
        'correction_data' => 'array',
        'corrected_at' => 'datetime',
    ];

    public function correctable()
    {
        return $this->morphTo();
    }

    public function getExplanationForQuestion($questionId)
    {
        $data = $this->correction_data;

        // Estrutura esperada: ['errors_explanation' => [ ... ]]
        if (!isset($data['errors_explanation']) || !is_array($data['errors_explanation'])) {
            return null;
        }

        foreach ($data['errors_explanation'] as $error) {
            // A IA pode retornar ID como string ou int, forçar comparação
            // FIX: Normalizar ambos para string para garantir match
            if (isset($error['question_id']) && (string)$error['question_id'] === (string)$questionId) {
                // Combinar why_wrong e correct_approach para uma explicação completa
                $why = $error['why_wrong'] ?? '';
                $approach = $error['correct_approach'] ?? '';
                
                if ($why && $approach) {
                    return "**Por que errei?**\n$why\n\n**Como resolver:**\n$approach";
                }
                
                return $why ?: $approach;
            }
        }

        return null; // Return null so the frontend knows to keep polling
    }
}
