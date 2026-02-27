<x-layouts.admin>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Analytics: Comportamento</h1>
                <p class="text-gray-500 text-sm mt-1">Análise de páginas mais acessadas e engajamento dos usuários.</p>
            </div>
        </div>
    </x-slot>

    <!-- Menu Interno de Analytics -->
    <div class="flex overflow-x-auto gap-2 pb-4 mb-6 border-b border-gray-100">
        <a href="{{ route('admin.analytics.index') }}"
            class="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Visão
            Geral</a>
        <a href="{{ route('admin.analytics.behavior') }}"
            class="whitespace-nowrap px-4 py-2 font-medium text-sm rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-100">Comportamento</a>
        <a href="{{ route('admin.analytics.acquisition') }}"
            class="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Aquisição</a>
        <a href="{{ route('admin.analytics.conversion') }}"
            class="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Conversão</a>
        <a href="{{ route('admin.analytics.monetization') }}"
            class="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Monetização</a>
    </div>

    <!-- Tabela de Páginas Mais Acessadas -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-8">
        <div class="p-6 border-b border-gray-100 bg-gray-50">
            <h2 class="text-lg font-bold text-gray-800">Páginas Mais Acessadas (Últimos 7 dias)</h2>
            <p class="text-sm text-gray-500 mt-1">Descubra quais conteúdos geram mais tráfego e atenção.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white border-b border-gray-100">
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm uppercase tracking-wider">Página (Path)
                        </th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm uppercase tracking-wider text-right">
                            Visualizações</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm uppercase tracking-wider text-right">
                            Tempo Médio</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm uppercase tracking-wider text-right">
                            Taxa de Saída</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm uppercase tracking-wider w-32">
                            Engajamento</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php
                        $maxViews = $pages->max('total_views') ?: 1;
                    @endphp
                    @forelse($pages as $page)
                        <tr class="hover:bg-gray-50 transition-colors group">
                            <td class="py-4 px-6">
                                <div class="font-medium text-gray-800 text-sm mb-0.5 truncate max-w-xs"
                                    title="{{ $page->page_title }}">
                                    {{ $page->page_title ?: 'Sem título' }}
                                </div>
                                <div class="text-xs text-gray-500 font-mono">
                                    <a href="{{ $page->page_path }}" target="_blank"
                                        class="hover:text-blue-600 flex items-center gap-1">
                                        {{ Str::limit($page->page_path, 40) }}
                                        <svg class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                            <td class="py-4 px-6 text-sm text-gray-800 font-bold text-right">
                                {{ number_format($page->total_views, 0, ',', '.') }}
                            </td>
                            <td class="py-4 px-6 text-sm text-gray-600 text-right">
                                {{ gmdate(($page->avg_time >= 3600 ? "H:i:s" : "i:s"), intval($page->avg_time)) }}
                            </td>
                            <td class="py-4 px-6 text-sm text-gray-600 text-right">
                                <span class="{{ $page->exit_rate > 70 ? 'text-red-600 font-medium' : '' }}">
                                    {{ number_format($page->exit_rate, 1, ',', '.') }}%
                                </span>
                            </td>
                            <td class="py-4 px-6">
                                @php
                                    $barWidth = ($page->total_views / $maxViews) * 100;
                                @endphp
                                <div class="w-full bg-gray-100 rounded-full h-2">
                                    <div class="bg-indigo-500 shadow-sm h-2 rounded-full" style="width: {{ $barWidth }}%">
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-gray-500">
                                <svg class="w-12 h-12 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Nenhum dado de comportamento encontrado para o período.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>
