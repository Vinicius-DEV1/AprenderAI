<x-layouts.admin>
    <x-slot name="header">
        <h1 class="text-2xl font-bold text-gray-800">Analytics: Monetização Estratégica</h1>
        <p class="text-gray-500 text-sm mt-1">Otimização de ganhos via AdSense e Retenção.</p>
    </x-slot>

    <!-- Menu Interno de Analytics -->
    <div class="flex overflow-x-auto gap-2 pb-4 mb-6 border-b border-gray-100">
        <a href="{{ route('admin.analytics.index') }}"
            class="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Visão
            Geral</a>
        <a href="{{ route('admin.analytics.behavior') }}"
            class="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Comportamento</a>
        <a href="{{ route('admin.analytics.acquisition') }}"
            class="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Aquisição</a>
        <a href="{{ route('admin.analytics.conversion') }}"
            class="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Conversão</a>
        <a href="{{ route('admin.analytics.monetization') }}"
            class="whitespace-nowrap px-4 py-2 font-medium text-sm rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-100">Monetização</a>
    </div>

    @if(count($insights) > 0)
        <div class="bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-100 rounded-xl p-6 mb-8 shadow-sm">
            <h3 class="flex items-center gap-2 text-emerald-800 font-bold mb-3">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
                Recomendações para AdSense
            </h3>
            <ul class="space-y-2">
                @foreach($insights as $insight)
                    <li class="flex items-start gap-2 text-sm text-emerald-700">
                        <span class="mt-1 flex-shrink-0 w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        {{ $insight }}
                    </li>
                @endforeach
                <li class="flex items-start gap-2 text-sm text-emerald-700 font-medium">
                    <span class="mt-1 flex-shrink-0 w-1.5 h-1.5 rounded-full bg-emerald-500 focus:outline-none"></span>
                    DICA: Combine locais de anúncios com as páginas abaixo que possuem MAIOR RETENÇÃO de usuários.
                </li>
            </ul>
        </div>
    @endif

    <!-- Tabela de Retenção -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-8">
        <div class="p-6 border-b border-gray-100 bg-gray-50">
            <h2 class="text-lg font-bold text-gray-800">Páginas de Alta Retenção (Melhores para AdSense)</h2>
            <p class="text-sm text-gray-500 mt-1">Páginas onde os usuários passam mais tempo.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white border-b border-gray-100">
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm uppercase tracking-wider">Página</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm uppercase tracking-wider text-right">
                            Tempo Médio</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm uppercase tracking-wider text-right">
                            Acessos</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($pages as $page)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="py-4 px-6">
                                <span
                                    class="font-medium text-gray-800 text-sm break-all font-mono">{{ Str::limit($page->page_path, 60) }}</span>
                            </td>
                            <td class="py-4 px-6 text-sm text-emerald-600 font-bold text-right">
                                {{ gmdate(($page->avg_time >= 3600 ? "H:i:s" : "i:s"), intval($page->avg_time)) }}
                            </td>
                            <td class="py-4 px-6 text-sm text-gray-600 text-right">
                                {{ number_format($page->total_views, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-12 text-center text-gray-500">Nenhum dado encontrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>