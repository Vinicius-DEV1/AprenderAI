<?php

namespace App\Http\Controllers;

use App\Models\Concurso;
use Illuminate\Http\Request;

class ConcursoController extends Controller
{
    public function index(Request $request)
    {
        $uf = $request->input('uf');
        $busca = $request->input('busca');
        $situacao = $request->input('situacao', 'Ativo'); // Default para Ativo

        $query = Concurso::query();

        // Mapeamento interno cirúrgico
        if ($situacao === 'Ativo') {
            $query->whereIn('situacao', ['Inscrições Abertas', 'aberto', 'andamento', 'ativo', 'Ativo']);
        } elseif ($situacao === 'Previsto') {
            $query->whereIn('situacao', ['Previsto', 'previsto']);
        } elseif ($situacao === 'Encerrado') {
            $query->whereIn('situacao', ['Encerrado', 'encerrado', 'finalizado']);
        } else {
            // Em caso de valor inesperado, reverte para o comportamento padrão (Ativo)
            $query->whereIn('situacao', ['Inscrições Abertas', 'aberto', 'andamento', 'ativo', 'Ativo']);
        }

        if (!empty($uf)) {
            $query->porUf($uf);
        }

        if (!empty($busca)) {
            $query->busca($busca);
        }

        $query->orderByDesc('ultimo_status_at');

        $concursos = $query->paginate(12)->withQueryString();

        $ultimaAtualizacao = Concurso::max('ultimo_status_at');

        $filtros = compact('uf', 'busca', 'situacao');

        return view('concursos.index', compact('concursos', 'filtros', 'ultimaAtualizacao'));
    }
}
