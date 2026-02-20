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
        $situacao = $request->input('situacao');

        // Por padrão, exibe apenas concursos ativos (Inscrições Abertas ou Previsto).
        // Se situacao = 'todos', remove o filtro.
        if ($situacao === 'todos') {
            $query = Concurso::query();
        } else {
            $query = Concurso::query()->ativos();
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
