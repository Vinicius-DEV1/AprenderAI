@extends('layouts.app')

@section('title', 'Radar de Concursos')

@section('content')
    <div class="space-y-6">

        {{-- Header --}}
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">🏛️ Radar de Concursos</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Acompanhe os principais concursos e editais sem sair do AprenderAI.
            </p>
        </div>

        {{-- Filtros --}}
        <form method="GET" action="{{ route('concursos.index') }}"
            class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm p-4 flex flex-col sm:flex-row gap-3">

            {{-- UF --}}
            <div class="flex-1 min-w-0">
                <label for="uf" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Estado
                    (UF)</label>
                <select id="uf" name="uf"
                    class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos os estados</option>
                    @foreach(['AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO'] as $sigla)
                        <option value="{{ $sigla }}" {{ ($filtros['uf'] ?? '') === $sigla ? 'selected' : '' }}>
                            {{ $sigla }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Busca --}}
            <div class="flex-[2] min-w-0">
                <label for="busca" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Órgão ou
                    cargo</label>
                <input id="busca" type="text" name="busca" value="{{ $filtros['busca'] ?? '' }}"
                    placeholder="Ex.: Polícia Federal, Analista..."
                    class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            {{-- Situação --}}
            <div class="flex-1 min-w-0">
                <label for="situacao"
                    class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Situação</label>
                <select id="situacao" name="situacao"
                    class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="" {{ ($filtros['situacao'] ?? '') === '' ? 'selected' : '' }}>Ativos</option>
                    <option value="todos" {{ ($filtros['situacao'] ?? '') === 'todos' ? 'selected' : '' }}>Todos</option>
                    <option value="" disabled>─────</option>
                    <option disabled>Inscrições Abertas</option>
                    <option disabled>Previsto</option>
                    <option disabled>Encerrado</option>
                </select>
            </div>

            {{-- Botão --}}
            <div class="flex items-end">
                <button type="submit"
                    class="w-full sm:w-auto px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500">
                    Filtrar
                </button>
            </div>
        </form>

        {{-- Grid de Concursos --}}
        @if($concursos->isEmpty())
            <div
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm p-10 text-center">
                <svg class="mx-auto w-12 h-12 text-slate-300 dark:text-slate-600 mb-4" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Nenhum concurso encontrado.</p>
                <p class="text-slate-400 dark:text-slate-500 text-xs mt-1">
                    Tente ajustar os filtros ou aguarde a próxima sincronização.
                </p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($concursos as $concurso)
                    @php
                        $sit = $concurso->situacao;
                        if ($sit === 'Inscrições Abertas') {
                            $badgeClass = 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300';
                        } elseif ($sit === 'Previsto') {
                            $badgeClass = 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300';
                        } else {
                            $badgeClass = 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400';
                        }
                    @endphp

                    <div
                        class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm flex flex-col p-5 gap-3 hover:shadow-md transition-shadow">

                        {{-- Header do card --}}
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-slate-800 dark:text-slate-100 leading-snug truncate"
                                    title="{{ $concurso->orgao }}">
                                    {{ $concurso->orgao }}
                                </p>
                                @if($concurso->cargo)
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate"
                                        title="{{ $concurso->cargo }}">
                                        {{ $concurso->cargo }}
                                    </p>
                                @endif
                            </div>
                            <span class="flex-shrink-0 text-xs font-semibold px-2 py-0.5 rounded-full {{ $badgeClass }}">
                                {{ $sit }}
                            </span>
                        </div>

                        {{-- Detalhes --}}
                        <div class="space-y-1 text-xs text-slate-500 dark:text-slate-400">
                            <div class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span>{{ $concurso->uf }}</span>
                            </div>

                            @if($concurso->vagas)
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span>{{ number_format($concurso->vagas) }} vaga{{ $concurso->vagas > 1 ? 's' : '' }}</span>
                                </div>
                            @endif

                            @if($concurso->salario_maximo)
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>Até R$ {{ number_format($concurso->salario_maximo, 2, ',', '.') }}</span>
                                </div>
                            @endif

                            @if($concurso->inscricoes_inicio || $concurso->inscricoes_fim)
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span>
                                        @if($concurso->inscricoes_inicio && $concurso->inscricoes_fim)
                                            {{ $concurso->inscricoes_inicio->format('d/m/Y') }} –
                                            {{ $concurso->inscricoes_fim->format('d/m/Y') }}
                                        @elseif($concurso->inscricoes_fim)
                                            até {{ $concurso->inscricoes_fim->format('d/m/Y') }}
                                        @else
                                            a partir de {{ $concurso->inscricoes_inicio->format('d/m/Y') }}
                                        @endif
                                    </span>
                                </div>
                            @endif
                        </div>

                        {{-- Botão Saiba Mais --}}
                        @if (!empty($concurso->link_oficial))
                            <div class="mt-3">
                                <a href="{{ $concurso->link_oficial }}" target="_blank" rel="noopener noreferrer"
                                    class="text-sm font-medium text-indigo-600 hover:underline">
                                    Saiba mais →
                                </a>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Paginação --}}
            @if($concursos->hasPages())
                <div class="mt-4">
                    {{ $concursos->links() }}
                </div>
            @endif
        @endif

        {{-- Rodapé discreto --}}
        <p class="text-xs text-slate-400 dark:text-slate-500 text-right">
            @if($ultimaAtualizacao)
                Última atualização: {{ \Carbon\Carbon::parse($ultimaAtualizacao)->format('d/m/Y \à\s H:i') }}
            @else
                Dados ainda não sincronizados. Execute <code class="font-mono">php artisan concursos:sync</code> para popular.
            @endif
        </p>

    </div>
@endsection