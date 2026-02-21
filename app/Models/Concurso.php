<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Concurso extends Model
{
    protected $table = 'concursos';

    protected $fillable = [
        'uf',
        'orgao',
        'cargo',
        'situacao',
        'salario_maximo',
        'vagas',
        'link_oficial',
        'fonte',
        'inscricoes_inicio',
        'inscricoes_fim',
        'ultimo_status_at',
    ];

    protected $casts = [
        'inscricoes_inicio' => 'datetime',
        'inscricoes_fim' => 'datetime',
        'ultimo_status_at' => 'datetime',
        'salario_maximo' => 'decimal:2',
    ];

    /**
     * Filtra concursos com situação "Inscrições Abertas" ou "Previsto".
     */
    public function scopeAtivos($query)
    {
        return $query->whereIn('situacao', ['Inscrições Abertas', 'Previsto']);
    }

    /**
     * Filtra concursos por UF.
     */
    public function scopePorUf($query, string $uf)
    {
        return $query->where('uf', strtoupper($uf));
    }

    /**
     * Filtra concursos pelo órgão OU cargo (LIKE).
     */
    public function scopeBusca($query, string $termo)
    {
        return $query->where(function ($q) use ($termo) {
            $q->where('orgao', 'LIKE', "%{$termo}%")
                ->orWhere('cargo', 'LIKE', "%{$termo}%");
        });
    }
}
