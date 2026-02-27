{{-- 
    VIEW: Study Plan Wizard
    DARK MODE: Garantido que inputs nativos (select, date) e cards herdem bg-slate-800 e bordas 700.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-200 leading-tight">
            {{ __('Gerar Seu Plano de Estudos') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-sm dark:shadow-none sm:rounded-lg">
                <div class="p-6 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700">
                    <div x-data="{
                        loading: false,
                        error: null,
                        studyPlanId: null,
                        hours: 4,
                        examType: 'enem',
                        async submitForm() {
                            this.loading = true;
                            this.error = null;
                            const form = this.$refs.form;
                            const formData = new FormData(form);

                            try {
                                const response = await fetch(form.action, {
                                    method: 'POST',
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                                    },
                                    body: formData
                                });

                                const data = await response.json();

                                if (!response.ok) {
                                    throw new Error(data.error || 'Erro ao iniciar geração.');
                                }

                                if (data.status === 'queued') {
                                    this.studyPlanId = data.study_plan_id;
                                    this.pollStatus();
                                } else {
                                    // Fallback if not queued
                                    window.location.href = '{{ route('study-plan.index') }}';
                                }

                            } catch (e) {
                                this.error = e.message;
                                this.loading = false;
                            }
                        },
                        async pollStatus() {
                            if (!this.studyPlanId) return;
                            
                            const poll = setInterval(async () => {
                                try {
                                    const res = await fetch(`{{ route('study-plan.status') }}?id=${this.studyPlanId}`);
                                    const data = await res.json();

                                    if (data.status === 'ready') {
                                        clearInterval(poll);
                                        window.location.href = '{{ route('study-plan.index') }}';
                                    } else if (data.status === 'failed') {
                                        clearInterval(poll);
                                        this.loading = false;
                                        this.error = data.message || 'Falha na geração do plano.';
                                    }
                                } catch (e) {
                                    console.error('Polling error', e);
                                    // Don't stop polling on network temporary error
                                }
                            }, 2000); // 2 seconds
                        }
                    }">
                        <form x-ref="form" action="{{ route('study-plan.store') }}" method="POST"
                            @submit.prevent="submitForm">
                            @csrf

                            <!-- Error Message -->
                            <div x-show="error" class="mb-4 bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 p-4 text-red-700 dark:text-red-400"
                                style="display: none;">
                                <p x-text="error"></p>
                            </div>

                            <!-- Step 1: Availability -->
                            <div class="mb-8">
                                <h3 class="text-lg font-medium leading-6 text-slate-900 dark:text-slate-100 mb-4">
                                    Disponibilidade</h3>
                                <label for="hours_per_day" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Quantas
                                    horas
                                    por dia você pode estudar?</label>
                                <select id="hours_per_day" name="hours_per_day" x-model="hours"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-200 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    @for ($i = 1; $i <= 10; $i++)
                                        <option value="{{ $i }}">{{ $i }} hora{{ $i > 1 ? 's' : '' }}</option>
                                    @endfor
                                </select>
                            </div>

                            <!-- Step 2: Goal -->
                            <div class="mb-8">
                                <h3 class="text-lg font-medium leading-6 text-slate-900 dark:text-slate-100 mb-4">
                                    Objetivo Principal</h3>

                                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
                                    <div>
                                        <label for="exam_type" class="block text-sm font-medium text-slate-700">Tipo de
                                            Prova</label>
                                        <select id="exam_type" name="exam_type" x-model="examType"
                                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-200 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                            <option value="enem">ENEM</option>
                                            <option value="concurso">Concurso Público</option>
                                        </select>
                                    </div>

                                    <div x-show="examType === 'concurso'" style="display: none;">
                                        <label for="exam_name" class="block text-sm font-medium text-slate-700">Nome do
                                            Concurso (opcional)</label>
                                        <input type="text" name="exam_name" id="exam_name"
                                            class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-200 rounded-md"
                                            placeholder="Ex: Receita Federal">
                                    </div>
                                </div>
                            </div>

                            <!-- Step 3: Date -->
                            <div class="mb-8">
                                <h3 class="text-lg font-medium leading-6 text-slate-900 dark:text-slate-100 mb-4">Data
                                    da Prova (Opcional)
                                </h3>
                                <div class="max-w-xs">
                                    <label for="exam_date" class="block text-sm font-medium text-slate-700">Quando será
                                        a
                                        prova?</label>
                                    <input type="date" name="exam_date" id="exam_date"
                                        class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-200 rounded-md">
                                </div>
                            </div>

                    </div>

                    <div class="pt-5 border-t border-slate-200 dark:border-slate-700">
                        <div class="flex justify-end">
                            <button type="submit" :disabled="loading"
                                class="ml-3 inline-flex justify-center py-3 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transform transition-all"
                                :class="{'opacity-75 cursor-not-allowed hover:scale-100': loading, 'hover:scale-105': !loading}">
                                <span x-show="!loading">Gerar Plano ✨</span>
                                <span x-show="loading" style="display: none;" class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                    Gerando...
                                </span>
                            </button>
                        </div>
                        <p class="mt-4 text-xs text-slate-500 text-center">
                            O sistema analisará seus simulados anteriores para criar a melhor estratégia. Isso
                            pode levar
                            alguns segundos.
                        </p>
                    </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    </div>
</x-app-layout>
