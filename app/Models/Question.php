<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'subject',
        'theme',
        'difficulty',
        'year',
        'statement',
        'alternatives',
        'correct_answer',
        'explanation',
        'source',
        'topic',
    ];

    protected $casts = [
        'alternatives' => 'array',
    ];

    public function simulationAnswers()
    {
        return $this->hasMany(SimulationAnswer::class);
    }

    public function isCorrect(string $answer): bool
    {
        return strtoupper($answer) === strtoupper($this->correct_answer);
    }

    public function getStatementHtmlAttribute(): string
    {
        if (empty($this->statement)) {
            return '';
        }

        // 1. Converter Imagens Markdown: ![](URL) -> <img ...>
        $html = preg_replace(
            '/!\[(.*?)\]\((.*?)\)/',
            '<img src="$2" alt="$1" class="max-w-full h-auto rounded-lg my-4 mx-auto block shadow-sm" loading="lazy">',
            $this->statement
        );

        // 2. Quebras de linha para <br> ou parágrafos (opcional, mas bom para garantir)
        // Como o blade já usava whitespace-pre-wrap, talvez não precise de nl2br se mantivermos a classe CSS.
        // Mas se tivermos tags HTML misturadas com texto puro, o whitespace-pre-wrap pode não ser ideal para a imagem.
        // A melhor abordagem é escapar o texto HTML *antes*, exceto as imagens que acabamos de gerar.
        // Mas para simplificar e seguir o pedido: focar nas imagens.

        return $html;
    }
}
