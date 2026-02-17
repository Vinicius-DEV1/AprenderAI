<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Nova Redação') }} - Tema
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">

                <!-- Steps Indicator -->
                <div class="border-b border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center justify-center space-x-8">
                        <div class="text-blue-600 font-bold">1. Tipo e Tempo</div>
                        <div class="w-12 h-0.5 bg-blue-600"></div>
                        <div class="text-blue-600 font-bold">2. Tema</div>
                        <div class="w-12 h-0.5 bg-gray-300"></div>
                        <div class="text-gray-400">3. Escrita</div>
                    </div>
                </div>

                <div id="topic-ui" class="text-center py-6" data-essay-id="{{ $essay->id }}"
                    data-initial-status="{{ $essay->status }}" data-has-topic="{{ !empty($essay->topic_description) }}"
                    data-regen-count="{{ $essay->topic_regen_count }}">

                    <!-- LOADING STATE -->
                    <div id="state-loading" class="hidden">
                        <div class="flex flex-col items-center justify-center space-y-4">
                            <svg class="animate-spin h-10 w-10 text-blue-600" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            <p class="text-lg font-medium text-gray-700 dark:text-gray-300">
                                Xavier está preparando um tema exclusivo para você...
                            </p>
                        </div>
                    </div>

                    <!-- ERROR STATE -->
                    <div id="state-error" class="hidden">
                        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative mb-4"
                            role="alert">
                            <strong class="font-bold">Ops!</strong>
                            <span class="block sm:inline">Não foi possível gerar o tema agora.</span>
                        </div>
                        <button id="btn-retry"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded focus:outline-none focus:shadow-outline">
                            Tentar Novamente
                        </button>
                    </div>

                    <!-- SUCCESS/CONTENT STATE -->
                    <div id="state-content" class="hidden text-left">
                        <div
                            class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg border border-gray-200 dark:border-gray-600 mb-6">
                            <h4 id="topic-title" class="text-xl font-bold mb-4 text-gray-900 dark:text-white">
                                {{ $essay->title }}
                            </h4>
                            <div id="topic-body"
                                class="prose dark:prose-invert max-w-none whitespace-pre-line text-gray-800 dark:text-gray-200">
                                {!! nl2br(e($essay->topic_description)) !!}
                            </div>
                        </div>

                        <div class="flex justify-between items-center mt-6">
                            <div class="text-sm text-gray-500" id="regen-info">
                                <!-- Populated by JS -->
                            </div>

                            <div class="flex space-x-4">
                                <button id="btn-regen"
                                    class="hidden text-blue-600 hover:text-blue-800 text-sm font-semibold px-4 py-2 border border-blue-200 rounded hover:bg-blue-50 transition">
                                    Gerar outro tema
                                </button>

                                <a href="{{ route('essays.write', $essay) }}"
                                    class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 font-bold transition">
                                    Começar a Escrever
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const ui = document.getElementById('topic-ui');
                        const essayId = ui.dataset.essayId;
                        const hasTopic = ui.dataset.hasTopic;
                        let status = ui.dataset.initialStatus;
                        let regenCount = parseInt(ui.dataset.regenCount);

                        // Elements
                        const elLoading = document.getElementById('state-loading');
                        const elError = document.getElementById('state-error');
                        const elContent = document.getElementById('state-content');
                        const elTitle = document.getElementById('topic-title');
                        const elBody = document.getElementById('topic-body');
                        const elRegenInfo = document.getElementById('regen-info');
                        const btnRegen = document.getElementById('btn-regen');
                        const btnRetry = document.getElementById('btn-retry');

                        const showState = (state) => {
                            elLoading.classList.add('hidden');
                            elError.classList.add('hidden');
                            elContent.classList.add('hidden');

                            if (state === 'loading') elLoading.classList.remove('hidden');
                            if (state === 'error') elError.classList.remove('hidden');
                            if (state === 'content') elContent.classList.remove('hidden');
                        };

                        const updateContent = (title, description, count) => {
                            elTitle.innerText = title;
                            elBody.innerHTML = description.replace(/\n/g, '<br>');

                            // Update regen info
                            if (count < 3) {
                                elRegenInfo.innerText = `Você pode gerar mais ${3 - count} temas.`;
                                btnRegen.classList.remove('hidden');
                            } else {
                                elRegenInfo.innerHTML = '<span class="text-red-500">Limite de gerações atingido.</span>';
                                btnRegen.classList.add('hidden');
                            }
                        };

                        const startGeneration = async () => {
                            showState('loading');
                            try {
                                const res = await fetch(`/essays/${essayId}/start-topic`, {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'Content-Type': 'application/json'
                                    }
                                });
                                if (!res.ok) throw new Error('Falha ao iniciar');
                                pollStatus();
                            } catch (e) {
                                console.error(e);
                                showState('error');
                            }
                        };

                        const pollStatus = () => {
                            const interval = setInterval(async () => {
                                try {
                                    const res = await fetch(`/essays/${essayId}/topic-status`);
                                    const data = await res.json();

                                    if (data.topic_description) {
                                        clearInterval(interval);
                                        updateContent(data.title, data.topic_description, data.topic_regen_count);
                                        showState('content');
                                        status = 'completed'; // or whatever
                                    } else if (data.status === 'error') {
                                        clearInterval(interval);
                                        showState('error');
                                    }
                                    // Else continue polling
                                } catch (e) {
                                    console.error('Polling error', e);
                                    // Don't stop polling immediately on network glitch, but maybe count errors?
                                    // For now keep trying.
                                }
                            }, 2000);
                        };

                        // Initial Logic
                        if (hasTopic) {
                            showState('content');
                            updateContent('{{ $essay->title }}', `{!! nl2br(e($essay->topic_description)) !!}`, regenCount);
                        } else if (status === 'error') {
                            showState('error');
                        } else {
                            // Auto start
                            startGeneration();
                        }

                        // Event Listeners
                        btnRetry.onclick = () => startGeneration();
                        btnRegen.onclick = () => {
                            if (confirm('Gerar um novo tema substituirá o atual. Confirmar?')) {
                                startGeneration();
                            }
                        };
                    });
                </script>
            </div>
        </div>
    </div>
</x-app-layout>