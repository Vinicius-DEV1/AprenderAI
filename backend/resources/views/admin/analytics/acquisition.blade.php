<x-layouts.admin>
    <x-slot name="header">
        <h1 class="text-2xl font-bold text-gray-800">Analytics: Aquisição</h1>
        <p class="text-gray-500 text-sm mt-1">Origem do tráfego, dispositivos e demografia dos usuários.</p>
    </x-slot>

    <!-- Menu Interno de Analytics -->
    <div class="flex overflow-x-auto gap-2 pb-4 mb-6 border-b border-gray-100">
        <a href="{{ route('admin.analytics.index') }}"
            class="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Visão
            Geral</a>
        <a href="{{ route('admin.analytics.behavior') }}"
            class="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Comportamento</a>
        <a href="{{ route('admin.analytics.acquisition') }}"
            class="whitespace-nowrap px-4 py-2 font-medium text-sm rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-100">Aquisição</a>
        <a href="{{ route('admin.analytics.conversion') }}"
            class="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Conversão</a>
        <a href="{{ route('admin.analytics.monetization') }}"
            class="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Monetização</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Devices Donut Fake (Data table mapping visually) -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100 bg-gray-50">
                <h2 class="text-lg font-bold text-gray-800">Dispositivos</h2>
            </div>
            <div class="p-6">
                @php $totalDevices = $devices->sum('total_sessions') ?: 1; @endphp
                <div class="space-y-4">
                    @foreach($devices as $device)
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm font-medium text-gray-700 flex items-center gap-2">
                                    @if($device->device_category == 'Mobile')
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                    @elseif($device->device_category == 'Tablet')
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 18h.01M9 21h6a2 2 0 002-2V5a2 2 0 00-2-2H9a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                    @else
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    @endif
                                    {{ $device->device_category }}
                                </span>
                                <span
                                    class="text-sm font-bold text-gray-800">{{ number_format(($device->total_sessions / $totalDevices) * 100, 1) }}%
                                    <span
                                        class="text-xs text-gray-400 font-normal">({{ number_format($device->total_sessions, 0, ',', '.') }})</span></span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2.5">
                                <div class="bg-blue-500 h-2.5 rounded-full"
                                    style="width: {{ ($device->total_sessions / $totalDevices) * 100 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Origens Principais -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100 bg-gray-50">
                <h2 class="text-lg font-bold text-gray-800">Origens de Tráfego</h2>
            </div>
            <div class="p-0">
                <table class="w-full text-left">
                    <tbody class="divide-y divide-gray-100">
                        @foreach($sources->take(5) as $source)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td
                                    class="py-3 px-6 text-sm text-gray-800 font-medium border-l-4 border-transparent hover:border-indigo-500">
                                    {{ $source->source_medium }}
                                </td>
                                <td class="py-3 px-6 text-sm text-gray-600 text-right font-bold">
                                    {{ number_format($source->total_sessions, 0, ',', '.') }}
                                    <span class="text-[10px] text-gray-400 font-normal ml-1">sessões</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.admin>
