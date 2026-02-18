<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Nova Redação') }} - Escrita
        </h2>
    </x-slot>

    @php
        $endTime = $essay->started_at->copy()->addMinutes($essay->time_limit);
        $remainingSeconds = max(0, now()->diffInSeconds($endTime, false));
    @endphp

    <div class="py-12" x-data="essayWriter({{ $remainingSeconds }})">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">

                <!-- Steps Indicator -->
                <div class="border-b border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center justify-center space-x-8">
                        <div class="text-blue-600 font-bold">1. Tipo e Tempo</div>
                        <div class="w-12 h-0.5 bg-blue-600"></div>
                        <div class="text-blue-600 font-bold">2. Tema</div>
                        <div class="w-12 h-0.5 bg-blue-600"></div>
                        <div class="text-blue-600 font-bold">3. Escrita</div>
                    </div>
                </div>

                <div class="p-6 text-gray-900 dark:text-gray-100">

                    <!-- Timer & Info -->
                    <div class="flex justify-between items-center bg-blue-50 dark:bg-blue-900 p-4 rounded-md mb-6 transition-colors duration-300"
                        :class="{ 'bg-red-100 dark:bg-red-900': remainingSeconds <= 120, 'bg-blue-50 dark:bg-blue-900': remainingSeconds > 120 }">
                        <div>
                            <span class="font-bold">Tempo limite:</span> {{ $essay->time_limit }} min
                        </div>
                        <div class="flex flex-col items-end">
                            <div class="font-mono text-xl font-bold"
                                :class="{ 'text-red-600 dark:text-red-400': remainingSeconds <= 120, 'text-blue-700 dark:text-blue-300': remainingSeconds > 120 }"
                                x-text="timerDisplay">
                                00:00:00
                            </div>
                            <div x-show="remainingSeconds <= 120"
                                class="text-xs font-bold text-red-600 dark:text-red-400 animate-pulse mt-1">
                                ⚠ Faltam menos de 2 minutos!
                            </div>
                        </div>
                    </div>

                    <!-- Topic Toggle/View -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-md mb-4 text-sm" x-data="{ open: false }">
                        <div class="flex justify-between cursor-pointer" @click="open = !open">
                            <span class="font-bold flex items-center">
                                <span class="mr-2">Tema:</span>
                                <span class="prose prose-sm dark:prose-invert max-w-none inline-block">
                                    {!! \Illuminate\Support\Str::markdown($essay->title) !!}
                                </span>
                            </span>
                            <span x-text="open ? '▲' : '▼'"></span>
                        </div>
                        <div x-show="open"
                            class="mt-2 prose prose-slate prose-sm dark:prose-invert max-w-none whitespace-pre-line leading-relaxed">
                            {!! \Illuminate\Support\Str::markdown($essay->topic_description) !!}
                        </div>
                    </div>

                    <form action="{{ route('essays.submit', $essay) }}" method="POST" id="essayForm">
                        @csrf
                        <div>
                            <textarea name="content" x-model="content" @input="updateCounts" rows="25"
                                :disabled="hasAutoSubmitted"
                                class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm resize-none font-serif text-lg leading-relaxed p-6 disabled:opacity-50 disabled:bg-gray-100 dark:disabled:bg-gray-800"
                                placeholder="Escreva sua redação aqui...">{{ old('content', $essay->content) }}</textarea>

                            <div class="flex justify-end space-x-4 mt-2 text-sm text-gray-500">
                                <span>Caracteres: <span x-text="charCount">0</span></span>
                                <span>Palavras: <span x-text="wordCount">0</span></span>
                            </div>
                        </div>

                        <div class="flex justify-end mt-6">
                            <button type="submit" :disabled="hasAutoSubmitted"
                                class="bg-blue-600 text-white px-8 py-3 rounded-md hover:bg-blue-700 font-bold text-lg disabled:opacity-50 disabled:cursor-not-allowed"
                                onclick="return confirm('Tem certeza que deseja enviar sua redação para correção?');">
                                Enviar Redação
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function essayWriter(initialSeconds) {
                return {
                    content: @json(old('content', $essay->content ?? '')),
                    timerDisplay: '00:00:00',
                    remainingSeconds: initialSeconds,
                    expiryTime: null,
                    wordCount: 0,
                    charCount: 0,
                    timerInterval: null,
                    hasAutoSubmitted: false,

                    init() {
                        this.updateCounts();
                        // Calibrate expiry time based on client loaded moment + remaining server seconds
                        // This ensures that even if client clock is off, the duration is correct relative to logic
                        this.expiryTime = Date.now() + (this.remainingSeconds * 1000);
                        this.startTimer();
                        this.tick(); // Initial tick
                    },

                    startTimer() {
                        this.timerInterval = setInterval(() => {
                            this.tick();
                        }, 1000);
                    },

                    stopTimer() {
                        clearInterval(this.timerInterval);
                    },

                    tick() {
                        const now = Date.now();
                        const diff = Math.ceil((this.expiryTime - now) / 1000);

                        if (diff <= 0) {
                            this.remainingSeconds = 0;
                            this.timerDisplay = '00:00:00';
                            this.autoSubmit();
                        } else {
                            this.remainingSeconds = diff;
                            const h = Math.floor(this.remainingSeconds / 3600).toString().padStart(2, '0');
                            const m = Math.floor((this.remainingSeconds % 3600) / 60).toString().padStart(2, '0');
                            const s = (this.remainingSeconds % 60).toString().padStart(2, '0');
                            this.timerDisplay = `${h}:${m}:${s}`;
                        }
                    },

                    autoSubmit() {
                        if (this.hasAutoSubmitted) return;
                        this.hasAutoSubmitted = true;
                        this.stopTimer();

                        // Visual feedback
                        alert('Tempo esgotado! Sua redação será enviada automaticamente.');

                        // Submit form
                        document.getElementById('essayForm').submit();
                    },

                    updateCounts() {
                        const text = this.content || '';
                        this.charCount = text.length;
                        this.wordCount = text.trim() === '' ? 0 : text.trim().split(/\s+/).filter(w => w.length > 0).length;
                    }
                }
            }
        </script>
    @endpush
</x-app-layout>