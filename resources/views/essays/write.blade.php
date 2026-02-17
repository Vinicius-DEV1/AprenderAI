<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Nova Redação') }} - Escrita
        </h2>
    </x-slot>

    <div class="py-12" x-data="essayWriter()">
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
                    <div class="flex justify-between items-center bg-blue-50 dark:bg-blue-900 p-4 rounded-md mb-6">
                        <div>
                            <span class="font-bold">Tempo limite:</span> {{ $essay->time_limit }} min
                        </div>
                        <div class="font-mono text-xl font-bold text-blue-700 dark:text-blue-300" x-text="timerDisplay">
                            00:00:00
                        </div>
                    </div>

                    <!-- Topic Toggle/View -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-md mb-4 text-sm" x-data="{ open: false }">
                        <div class="flex justify-between cursor-pointer" @click="open = !open">
                            <span class="font-bold">Tema: {{ $essay->title }}</span>
                            <span x-text="open ? '▲' : '▼'"></span>
                        </div>
                        <div x-show="open" class="mt-2 prose dark:prose-invert max-w-none text-xs whitespace-pre-line">
                            {!! nl2br(e($essay->topic_description)) !!}
                        </div>
                    </div>

                    <form action="{{ route('essays.submit', $essay) }}" method="POST" id="essayForm">
                        @csrf
                        <div>
                            <textarea name="content" x-model="content" @input="updateCounts" rows="25"
                                class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm resize-none font-serif text-lg leading-relaxed p-6"
                                placeholder="Escreva sua redação aqui...">{{ old('content', $essay->content) }}</textarea>

                            <div class="flex justify-end space-x-4 mt-2 text-sm text-gray-500">
                                <span>Caracteres: <span x-text="charCount">0</span></span>
                                <span>Palavras: <span x-text="wordCount">0</span></span>
                            </div>
                        </div>

                        <div class="flex justify-end mt-6">
                            <button type="submit"
                                class="bg-blue-600 text-white px-8 py-3 rounded-md hover:bg-blue-700 font-bold text-lg"
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
            function essayWriter() {
                return {
                    content: @json(old('content', $essay->content ?? '')),
                    timerDisplay: '00:00:00',
                    secondsElapsed: 0,
                    wordCount: 0,
                    charCount: 0,
                    timerInterval: null,

                    init() {
                        this.updateCounts();
                        this.startTimer();
                    },

                    startTimer() {
                        // Ideally check diff between started_at and now
                        // For now simple counter
                        this.timerInterval = setInterval(() => {
                            this.secondsElapsed++;
                            const h = Math.floor(this.secondsElapsed / 3600).toString().padStart(2, '0');
                            const m = Math.floor((this.secondsElapsed % 3600) / 60).toString().padStart(2, '0');
                            const s = (this.secondsElapsed % 60).toString().padStart(2, '0');
                            this.timerDisplay = `${h}:${m}:${s}`;
                        }, 1000);
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