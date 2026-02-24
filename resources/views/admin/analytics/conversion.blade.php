<x-layouts.admin>
    <x-slot name="header">
        <h1 class="text-2xl font-bold text-gray-800">Analytics: Conversão</h1>
        <p class="text-gray-500 text-sm mt-1">Acompanhamento de eventos principais e metas de conversão.</p>
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
            class="whitespace-nowrap px-4 py-2 font-medium text-sm rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-100">Conversão</a>
        <a href="{{ route('admin.analytics.monetization') }}"
            class="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Monetização</a>
    </div>

    <!-- Tabela de Eventos -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-8">
        <div class="p-6 border-b border-gray-100 bg-gray-50">
            <h2 class="text-lg font-bold text-gray-800">Eventos Disparados (Últimos 7 dias)</h2>
            <p class="text-sm text-gray-500 mt-1">Lista de eventos registrados pela tag do GA4.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white border-b border-gray-100">
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm uppercase tracking-wider">Nome do
                            Evento</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm uppercase tracking-wider text-right">
                            Contagem Total</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm uppercase tracking-wider text-right">
                            Usuários Únicos</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($events as $event)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="py-4 px-6">
                                <div class="font-medium text-indigo-700 font-mono text-sm max-w-xs truncate">
                                    {{ $event->event_name }}
                                </div>
                            </td>
                            <td class="py-4 px-6 text-sm text-gray-800 font-bold text-right">
                                {{ number_format($event->total_events, 0, ',', '.') }}
                            </td>
                            <td class="py-4 px-6 text-sm text-gray-600 text-right">
                                {{ number_format($event->total_users, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-12 text-center text-gray-500">
                                Nenhum dado de eventos encontrado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>