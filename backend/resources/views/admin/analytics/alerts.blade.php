<x-layouts.admin>
    <x-slot name="header">
        <h1 class="text-2xl font-bold text-gray-800">Histórico de Alertas</h1>
        <p class="text-gray-500 text-sm mt-1">Todos os eventos de anomalia, quedas ou picos registrados no Analytics.</p>
    </x-slot>

    <!-- Menu Interno de Analytics -->
    <div class="flex overflow-x-auto gap-2 pb-4 mb-6 border-b border-gray-100">
        <a href="{{ route('admin.analytics.index') }}" class="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Visão Geral</a>
        <!-- ... outros itens ... -->
        <a href="{{ route('admin.analytics.alerts') }}" class="whitespace-nowrap px-4 py-2 font-medium text-sm rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-100">Alertas</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        @if($alerts->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100">
                            <th class="py-4 px-6 font-semibold text-gray-600 text-sm uppercase tracking-wider">Tipo</th>
                            <th class="py-4 px-6 font-semibold text-gray-600 text-sm uppercase tracking-wider">Mensagem</th>
                            <th class="py-4 px-6 font-semibold text-gray-600 text-sm uppercase tracking-wider">Data</th>
                            <th class="py-4 px-6 font-semibold text-gray-600 text-sm uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($alerts as $alert)
                            @php
                                $isHighlight = request('highlight') == $alert->id;
                            @endphp
                            <tr class="hover:bg-gray-50 transition-colors {{ $isHighlight ? 'bg-yellow-50/50' : '' }}">
                                <td class="py-4 px-6">
                                    @if($alert->type == 'drop')
                                        <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-md text-xs font-medium bg-red-50 text-red-700 border border-red-100">
                                            📉 Queda
                                        </span>
                                    @elseif($alert->type == 'growth' || $alert->type == 'peak')
                                        <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-md text-xs font-medium bg-green-50 text-green-700 border border-green-100">
                                            🚀 Pico/Cresc.
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-md text-xs font-medium bg-orange-50 text-orange-700 border border-orange-100">
                                            ⚠️ Anomalia
                                        </span>
                                    @endif
                                </td>
                                <td class="py-4 px-6 text-sm text-gray-800 font-medium">
                                    {{ $alert->message }}
                                </td>
                                <td class="py-4 px-6 text-sm text-gray-500 whitespace-nowrap">
                                    {{ $alert->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="py-4 px-6 text-sm text-gray-500">
                                    @if($alert->read_at)
                                        <span class="text-xs text-gray-400">Lido</span>
                                    @else
                                        <span class="text-xs font-bold text-indigo-600 flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span> Novo
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            @if($alerts->hasPages())
                <div class="px-6 py-4 border-t border-gray-100">
                    {{ $alerts->links() }}
                </div>
            @endif
        @else
            <div class="p-12 text-center text-gray-500">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                <p class="text-lg font-medium text-gray-600">Nenhum alerta registrado até o momento.</p>
                <p class="text-sm text-gray-400 mt-1">Anomalias aparecerão aqui automaticamente.</p>
            </div>
        @endif
    </div>
</x-layouts.admin>
