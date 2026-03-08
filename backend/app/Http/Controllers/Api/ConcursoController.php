<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConcursoResource;
use App\Models\Concurso;
use Illuminate\Http\Request;

class ConcursoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $uf = $request->input('uf');
        $busca = $request->input('busca');
        $situacao = $request->input('situacao', 'Ativo');

        $query = Concurso::query();

        if ($situacao === 'Ativo') {
            $query->whereIn('situacao', ['Inscrições Abertas', 'aberto', 'andamento', 'ativo', 'Ativo']);
        } elseif ($situacao === 'Previsto') {
            $query->whereIn('situacao', ['Previsto', 'previsto']);
        } elseif ($situacao === 'Encerrado') {
            $query->whereIn('situacao', ['Encerrado', 'encerrado', 'finalizado']);
        } else {
            $query->whereIn('situacao', ['Inscrições Abertas', 'aberto', 'andamento', 'ativo', 'Ativo']);
        }

        if (!empty($uf)) {
            $query->porUf($uf);
        }

        if (!empty($busca)) {
            $query->busca($busca);
        }

        $query->orderByDesc('ultimo_status_at');

        $concursos = $query->paginate(12);
        $ultimaAtualizacao = Concurso::max('ultimo_status_at');

        // Formatação robusta para evitar "Call to a member function toIso8601String() on string"
        $dataIso = null;
        if ($ultimaAtualizacao) {
            try {
                // max() retorna string, precisamos converter para Carbon
                $dataIso = \Carbon\Carbon::parse($ultimaAtualizacao)->toIso8601String();
            } catch (\Exception $e) {
                // Fallback caso o parse falhe
                $dataIso = (string) $ultimaAtualizacao;
            }
        }

        return ConcursoResource::collection($concursos)->additional([
            'meta' => [
                'ultima_atualizacao' => $dataIso,
                'filtros' => [
                    'uf' => $uf,
                    'busca' => $busca,
                    'situacao' => $situacao,
                ],
            ]
        ]);
    }
}
