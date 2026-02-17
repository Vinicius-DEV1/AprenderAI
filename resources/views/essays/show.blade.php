<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Redação') }}: {{ $essay->title }}
        </h2>
    </x-slot>

    <div class="py-12" x-data="{ tab: 'general' }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!-- Status / Alert -->
            <div
                class="mb-6 p-4 rounded-lg text-center font-bold text-lg
                {{ $essay->status === 'completed' ? 'bg-green-100 text-green-800 border border-green-200' : '' }}
                {{ $essay->status === 'evaluating' ? 'bg-purple-100 text-purple-800 border border-purple-200 animate-pulse' : '' }}
                {{ $essay->status === 'error' ? 'bg-red-100 text-red-800 border border-red-200' : '' }}
                {{ $essay->status === 'pending' || $essay->status === 'in_progress' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                {{ $statusMessage }}
            </div>

            @if($essay->status === 'error')
                <div class="mb-6 flex justify-center">
                    <form action="{{ route('essays.retry-evaluation', $essay) }}" method="POST">
                        @csrf
                        <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded shadow">
                            Tentar novamente
                        </button>
                    </form>
                </div>
            @endif

            @if($essay->status === 'completed')
                <!-- Score Card -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6 text-gray-900 dark:text-gray-100 text-center">
                        <h3 class="text-sm uppercase tracking-widest text-gray-500 font-bold mb-2">Nota Xavier</h3>
                        <div class="text-6xl font-extrabold text-blue-600">
                            {{ $essay->score }}
                            <span class="text-2xl text-gray-400 font-normal">/
                                {{ $essay->type === 'enem' ? '1000' : '100' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <div class="flex space-x-2 mb-6 overflow-x-auto pb-2">
                    <button @click="tab = 'general'"
                        :class="{'bg-blue-600 text-white': tab === 'general', 'bg-white text-gray-700 dark:bg-gray-700 dark:text-gray-300': tab !== 'general'}"
                        class="px-4 py-2 rounded-md font-bold shadow transition">Resumo</button>
                    <button @click="tab = 'points'"
                        :class="{'bg-blue-600 text-white': tab === 'points', 'bg-white text-gray-700 dark:bg-gray-700 dark:text-gray-300': tab !== 'points'}"
                        class="px-4 py-2 rounded-md font-bold shadow transition">Pontos Fortes/Fracos</button>
                    <button @click="tab = 'corrections'"
                        :class="{'bg-blue-600 text-white': tab === 'corrections', 'bg-white text-gray-700 dark:bg-gray-700 dark:text-gray-300': tab !== 'corrections'}"
                        class="px-4 py-2 rounded-md font-bold shadow transition">Correções</button>
                    <button @click="tab = 'improved'"
                        :class="{'bg-blue-600 text-white': tab === 'improved', 'bg-white text-gray-700 dark:bg-gray-700 dark:text-gray-300': tab !== 'improved'}"
                        class="px-4 py-2 rounded-md font-bold shadow transition">Versão Melhorada</button>
                </div>

                <!-- Tab Contents -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg min-h-[400px]">
                    <div class="p-6 text-gray-900 dark:text-gray-100">

                        <!-- General -->
                        <div x-show="tab === 'general'">
                            <h3 class="text-xl font-bold mb-4">Resumo da Avaliação</h3>
                            <p class="mb-4 text-lg leading-relaxed">
                                {{ $essay->feedback_json['summary'] ?? $essay->feedback ?? 'Sem resumo disponível.' }}
                            </p>

                            @if(isset($essay->feedback_json['checklist']))
                                <h4 class="font-bold mt-6 mb-2">Checklist Rápido</h4>
                                <ul class="space-y-2">
                                    @foreach($essay->feedback_json['checklist'] as $item)
                                        <li class="flex items-center">
                                            @if(strtolower($item['status']) === 'ok')
                                                <span class="text-green-500 mr-2">✔</span>
                                            @else
                                                <span class="text-yellow-500 mr-2">⚠</span>
                                            @endif
                                            <span class="font-semibold mr-2">{{ $item['item'] }}:</span> {{ $item['status'] }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        <!-- Points -->
                        <div x-show="tab === 'points'" style="display:none;">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h3 class="text-green-600 font-bold text-lg mb-3">Pontos Fortes</h3>
                                    <ul class="list-disc pl-5 space-y-1">
                                        @foreach($essay->feedback_json['strengths'] ?? [] as $s)
                                            <li>{{ $s }}</li>
                                        @endforeach
                                        @if(empty($essay->feedback_json['strengths']))
                                            <li class="text-gray-500 italic">Nenhum ponto forte destacado.</li>
                                        @endif
                                    </ul>
                                </div>
                                <div>
                                    <h3 class="text-red-500 font-bold text-lg mb-3">A Melhorar</h3>
                                    <ul class="list-disc pl-5 space-y-1">
                                        @foreach($essay->feedback_json['weaknesses'] ?? [] as $w)
                                            <li>{{ $w }}</li>
                                        @endforeach
                                        @if(empty($essay->feedback_json['weaknesses']))
                                            <li class="text-gray-500 italic">Nenhum ponto de melhoria destacado.</li>
                                        @endif
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Corrections -->
                        <div x-show="tab === 'corrections'" style="display:none;">
                            <h3 class="text-xl font-bold mb-4">Correções Pontuais</h3>
                            <div class="space-y-4">
                                @foreach($essay->feedback_json['corrections'] ?? [] as $c)
                                    <div class="border-l-4 border-yellow-400 pl-4 py-2 bg-gray-50 dark:bg-gray-700">
                                        <p class="font-mono text-sm text-red-600 mb-1">"{{ $c['excerpt'] ?? 'Trecho' }}"</p>
                                        <p class="font-bold text-gray-800 dark:text-gray-200">{{ $c['issue'] ?? 'Problema' }}
                                        </p>
                                        <p class="text-green-600 italic">Sugestão: {{ $c['suggestion'] ?? '' }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Improved -->
                        <div x-show="tab === 'improved'" style="display:none;">
                            <h3 class="text-xl font-bold mb-4">Versão Sugerida por Xavier</h3>
                            <div
                                class="prose dark:prose-invert max-w-none bg-gray-50 dark:bg-gray-900 p-6 rounded-lg border border-gray-200 dark:border-gray-700">
                                {!! nl2br(e($essay->feedback_json['improved_version'] ?? 'Versão melhorada indisponível.')) !!}
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Original Text (Always visible at bottom) -->
            <div class="mt-8 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="font-bold text-gray-500 uppercase tracking-widest text-sm mb-4">Seu Texto Original</h3>
                    <div
                        class="prose dark:prose-invert max-w-none whitespace-pre-line text-gray-700 dark:text-gray-300">
                        {{ $essay->content }}
                    </div>
                </div>
            </div>

        </div>
    </div>

    @if($essay->status === 'evaluating')
        <script>
            setTimeout(() => {
                window.location.reload();
            }, 10000); // Auto reload every 10s to check status
        </script>
    @endif
</x-app-layout>