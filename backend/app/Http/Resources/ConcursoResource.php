<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConcursoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uf' => $this->uf,
            'orgao' => $this->orgao,
            'cargo' => $this->cargo,
            'situacao' => $this->situacao,
            'salario_maximo' => (float) $this->salario_maximo,
            'vagas' => (int) $this->vagas,
            'link_oficial' => $this->link_oficial,
            'inscricoes_inicio' => $this->inscricoes_inicio ? $this->inscricoes_inicio->format('Y-m-d') : null,
            'inscricoes_fim' => $this->inscricoes_fim ? $this->inscricoes_fim->format('Y-m-d') : null,
            'ultimo_status_at' => $this->ultimo_status_at ? $this->ultimo_status_at->format('Y-m-d H:i:s') : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
