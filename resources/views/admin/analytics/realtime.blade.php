<x-layouts.admin>
    <x-slot name="header">
        <h1 class="text-2xl font-bold text-gray-800">Analytics: Tempo Real</h1>
        <p class="text-gray-500 text-sm mt-1">Monitoramento ao vivo dos acessos na plataforma.</p>
    </x-slot>

    <!-- Menu Interno de Analytics -->
    <div class="flex overflow-x-auto gap-2 pb-4 mb-6 border-b border-gray-100">
        <a href="{{ route('admin.analytics.index') }}"
            class="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Visão
            Geral</a>
        <!-- ... -->
        <a href="{{ route('admin.analytics.realtime') }}"
            class="whitespace-nowrap px-4 py-2 font-medium text-sm rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-100 flex items-center gap-2">
            <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> Tempo Real
        </a>
    </div>

    <!-- Alpine.js Polling Component -->
    <div x-data="realtimeAnalytics()" x-init="startPolling()" class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">

        <!-- Live Active Users Wrapper -->
        <div
            class="lg:col-span-1 border border-green-200 bg-gradient-to-b from-green-50 to-white rounded-2xl p-8 flex flex-col items-center justify-center text-center shadow-sm relative overflow-hidden">
            <!-- Ping animation background -->
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <div class="w-48 h-48 bg-green-200 rounded-full animate-ping opacity-20"></div>
            </div>

            <p class="text-sm font-bold text-green-800 uppercase tracking-widest mb-4 z-10">Usuários Ativos Agora</p>
            <h2 class="text-7xl font-black text-green-600 z-10 tabular-nums animate-pulse-fast" x-text="activeUsers">
                <svg class="animate-spin h-10 w-10 text-green-600 mx-auto" xmlns="http://www.w3.org/2000/svg"
                    fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                </svg>
            </h2>

            <div class="mt-8 pt-6 border-t border-green-100 w-full z-10 flex flex-col gap-3">
                <template x-if="devices.length > 0">
                    <template x-for="device in devices">
                        <div class="flex justify-between items-center text-sm font-medium">
                            <span class="text-gray-600" x-text="device.category"></span>
                            <span class="text-gray-900 bg-green-100 px-2 py-0.5 rounded"
                                x-text="(Math.round((device.users / activeUsers) * 100) || 0) + '%'"></span>
                        </div>
                    </template>
                </template>
            </div>

            <p class="absolute bottom-2 text-[10px] text-gray-400 font-mono z-10" x-text="'Atualizado: ' + lastUpdate">
            </p>
        </div>

        <!-- Live Active Pages -->
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                <h3 class="font-bold text-gray-800">Páginas Ativas Top</h3>
                <span class="flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-3 w-3 rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                </span>
            </div>

            <div class="p-0">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-gray-50 text-xs uppercase tracking-wider text-gray-400">
                            <th class="py-3 px-6 font-medium">Caminho da Página</th>
                            <th class="py-3 px-6 font-medium text-right w-24">Usuários</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-for="page in pages" :key="page.path">
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="py-4 px-6 font-mono text-sm text-indigo-600" x-text="page.path"></td>
                                <td class="py-4 px-6 text-sm font-bold text-gray-800 text-right" x-text="page.users">
                                </td>
                            </tr>
                        </template>
                        <tr x-show="pages.length === 0">
                            <td colspan="2" class="py-12 text-center text-sm text-gray-400">Nenhum dado sendo
                                transmitido ou acesso em tempo real inativo.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('realtimeAnalytics', () => ({
                    activeUsers: 0,
                    pages: [],
                    devices: [],
                    lastUpdate: '--:--',
                    hasError: false,
                    interval: null,

                    async startPolling() {
                        this.fetchData();
                        // Poll cada 15 segs
                        this.interval = setInterval(() => this.fetchData(), 15000);
                    },

                    async fetchData() {
                        try {
                            const response = await fetch('{{ route("admin.analytics.realtime-data") }}', {
                                headers: { 'Accept': 'application/json' }
                            });
                            const data = await response.json();

                            this.activeUsers = data.activeUsers;
                            this.pages = data.current_pages || [];
                            this.devices = data.devices || [];
                            this.hasError = !!data.error;

                            const now = new Date();
                            this.lastUpdate = now.getHours().toString().padStart(2, '0') + ':' +
                                now.getMinutes().toString().padStart(2, '0') + ':' +
                                now.getSeconds().toString().padStart(2, '0');
                        } catch (e) {
                            console.error('Erro no polling realtime:', e);
                            this.hasError = true;
                        }
                    }
                }));
            });
        </script>
        <style>
            .animate-pulse-fast {
                animation: pulse 1s cubic-bezier(0.4, 0, 0.6, 1) infinite;
            }
        </style>
    @endpush
</x-layouts.admin>