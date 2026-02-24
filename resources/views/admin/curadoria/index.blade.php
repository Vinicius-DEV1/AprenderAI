<x-layouts.admin>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            🎯 Portal de Curadoria & Produção
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Overview Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <!-- Card: Triagem IA -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-purple-500">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-purple-50 rounded-lg">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <span class="text-xs font-bold text-purple-600 bg-purple-50 px-2 py-1 rounded-full uppercase">IA Ativa</span>
                    </div>
                    <h3 class="text-gray-500 text-sm font-medium">Pendências de IA</h3>
                    <p class="text-3xl font-bold text-gray-800 mt-1">{{ $pendingTriage }}</p>
                    <a href="{{ route('admin.questions.index') }}" class="mt-4 inline-flex items-center text-sm font-semibold text-purple-600 hover:text-purple-700">
                        Ir para Triagem →
                    </a>
                </div>

                <!-- Card: Revisão de Imagens -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-yellow-50 rounded-lg">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <span class="text-xs font-bold text-yellow-600 bg-yellow-50 px-2 py-1 rounded-full uppercase">Manual</span>
                    </div>
                    <h3 class="text-gray-500 text-sm font-medium">Imagens Perto de Revisão</h3>
                    <p class="text-3xl font-bold text-gray-800 mt-1">{{ $pendingImport }}</p>
                    <a href="{{ route('admin.import.review.index') }}" class="mt-4 inline-flex items-center text-sm font-semibold text-yellow-600 hover:text-yellow-700">
                        Ver Painel de Revisão →
                    </a>
                </div>

                <!-- Card: Histórico de Lotes -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-blue-500">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-blue-50 rounded-lg">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-sm font-medium">Lotes IA Processados</h3>
                    <p class="text-3xl font-bold text-gray-800 mt-1">{{ $totalBatchCount }} total</p>
                    <a href="{{ route('admin.triagem.historico') }}" class="mt-4 inline-flex items-center text-sm font-semibold text-blue-600 hover:text-blue-700">
                        Histórico de Triagem →
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Métodos de Entrada -->
                <div class="bg-white rounded-2xl shadow-sm p-8">
                    <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center gap-2">
                        📥 Métodos de Entrada de Conteúdo
                    </h3>
                    <div class="space-y-4">
                        <a href="{{ route('admin.import.index') }}" class="flex items-center gap-4 p-4 rounded-xl border border-gray-100 hover:bg-slate-50 transition-all group">
                            <div class="w-12 h-12 bg-indigo-50 rounded-lg flex items-center justify-center text-indigo-600 group-hover:scale-110 transition-transform">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800">Importação de Arquivo (.ZIP)</h4>
                                <p class="text-xs text-gray-500">Carregue bancos SQLite e pastas de imagens do scraper.</p>
                            </div>
                        </a>

                        <a href="{{ route('admin.enem-import.index') }}" class="flex items-center gap-4 p-4 rounded-xl border border-gray-100 hover:bg-slate-50 transition-all group">
                            <div class="w-12 h-12 bg-emerald-50 rounded-lg flex items-center justify-center text-emerald-600 group-hover:scale-110 transition-transform">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800">API ENEM Dev</h4>
                                <p class="text-xs text-gray-500">Sincronize questões diretamente da API externa.</p>
                            </div>
                        </a>

                        <a href="{{ route('admin.questions.create') }}" class="flex items-center gap-4 p-4 rounded-xl border border-gray-100 hover:bg-slate-50 transition-all group">
                            <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center text-blue-600 group-hover:scale-110 transition-transform">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800">Cadastro Manual</h4>
                                <p class="text-xs text-gray-500">Crie uma nova questão individualmente no banco.</p>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Histórico Recente -->
                <div class="bg-white rounded-2xl shadow-sm p-8">
                    <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center gap-2">
                            🕒 Lotes Recentes
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-400 font-medium pb-4">
                                    <th class="pb-3 uppercase text-[10px] tracking-wider">Lote</th>
                                    <th class="pb-3 uppercase text-[10px] tracking-wider text-center">Progresso</th>
                                    <th class="pb-3 uppercase text-[10px] tracking-wider text-right">Data</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @forelse($recentImports as $import)
                                    <tr>
                                        <td class="py-3">
                                            <div class="font-semibold text-gray-800">{{ $import->batch_name }}</div>
                                            <div class="text-[10px] text-gray-400">{{ $import->status }}</div>
                                        </td>
                                        <td class="py-3 text-center">
                                            <span class="px-2 py-1 bg-green-50 text-green-600 rounded text-xs font-bold">
                                                {{ $import->total_questions }} qst
                                            </span>
                                        </td>
                                        <td class="py-3 text-right text-gray-500 text-xs">
                                            {{ $import->created_at->diffForHumans() }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="py-10 text-center text-gray-400 italic">Nenhum lote recente processado.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-6">
                        <a href="{{ route('admin.import.index') }}" class="block text-center py-2 bg-gray-50 text-gray-600 rounded-lg text-sm font-semibold hover:bg-gray-100 transition-colors">
                            Ver Histórico Completo
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
