{{--
|--------------------------------------------------------------------------
| Essays Index — Minhas Redações
|--------------------------------------------------------------------------
|
| Lista de redações do usuário com tabela responsiva.
| Dark mode: bg-white → dark:bg-slate-900, text-gray → dark:text-slate-,
| divide-gray → dark:divide-slate-700, bg-gray-50 → dark:bg-slate-800
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-slate-200 leading-tight">
            {{ __('Minhas Redações') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Limit Card --}}
            <div class="mb-6 bg-white dark:bg-slate-900 overflow-hidden shadow-sm dark:shadow-none dark:border dark:border-slate-700 sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-slate-200 flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-medium">Limite Mensal</h3>
                        <p class="text-sm text-gray-500 dark:text-slate-400">
                            Você usou <span class="font-bold">{{ $used }}</span> de <span
                                class="font-bold">{{ $limit === 0 ? '0 (Free)' : $limit }}</span> redações este mês.
                        </p>
                    </div>
                    @if($canCreate)
                        <a href="{{ route('essays.create') }}"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 active:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 transition ease-in-out duration-150">
                            Nova Redação
                        </a>
                    @else
                        <button disabled
                            class="opacity-50 cursor-not-allowed inline-flex items-center px-4 py-2 bg-gray-400 dark:bg-slate-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">
                            Limite Atingido / Plano Gratuito
                        </button>
                    @endif
                </div>
                @if(!$canCreate && $limit === 0)
                    <div class="px-6 pb-6">
                        <p class="text-red-500 dark:text-red-400 text-sm">Faça upgrade para o plano Basic ou Plus para enviar redações!</p>
                        <a href="{{ route('plans.index') }}" class="text-blue-500 dark:text-blue-400 hover:underline text-sm">Ver Planos</a>
                    </div>
                @endif
            </div>

            {{-- List --}}
            <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-sm dark:shadow-none dark:border dark:border-slate-700 sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-slate-200">
                    @if($essays->isEmpty())
                        <div class="text-center py-10">
                            <p class="text-gray-500 dark:text-slate-400">Nenhuma redação encontrada.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                                <thead class="bg-gray-50 dark:bg-slate-800">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">
                                            Data</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">
                                            Tema</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">
                                            Tipo</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">
                                            Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">
                                            Nota</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">
                                            Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-slate-900 divide-y divide-gray-200 dark:divide-slate-700">
                                    @foreach($essays as $essay)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-slate-400">
                                                {{ $essay->created_at->format('d/m/Y H:i') }}
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-slate-200">
                                                {{ Str::limit($essay->title, 40) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-slate-400">
                                                <span
                                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300">
                                                    {{ strtoupper($essay->type) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                @php
                                                    $statusClasses = [
                                                        'pending' => 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300',
                                                        'in_progress' => 'bg-gray-100 dark:bg-slate-700 text-gray-800 dark:text-slate-300',
                                                        'evaluating' => 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300',
                                                        'completed' => 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300',
                                                        'error' => 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300',
                                                    ];
                                                    $statusLabel = [
                                                        'pending' => 'Pendente',
                                                        'in_progress' => 'Rascunho',
                                                        'evaluating' => 'Avaliando',
                                                        'completed' => 'Corrigida',
                                                        'error' => 'Erro',
                                                    ];
                                                @endphp
                                                <span
                                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusClasses[$essay->status] ?? 'bg-gray-100 dark:bg-slate-700 text-gray-800 dark:text-slate-300' }}">
                                                    {{ $statusLabel[$essay->status] ?? ucfirst($essay->status) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-slate-200 font-bold">
                                                {{ $essay->score ?? '-' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <a href="{{ route('essays.show', $essay) }}"
                                                    class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">
                                                    Abrir
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            {{ $essays->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>