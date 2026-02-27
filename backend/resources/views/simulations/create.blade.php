{{--
VIEW: Simulations Create
DARK MODE: Inclui overrides para Glass Card e estilização para TomSelect e Range Slider no modo escuro.
--}}
@extends('layouts.app')

@section('page-title', 'Nova Prova')

@section('content')
    <!-- TomSelect CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <!-- Custom Styles for TomSelect & Glassmorphism -->
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 10px 40px -10px rgba(0, 0, 0, 0.05);
        }

        /* Dark mode: glass-card com fundo slate-900 */
        :root.dark .glass-card {
            background: rgba(15, 23, 42, 0.95);
            border-color: rgba(255, 255, 255, 0.08);
            box-shadow: 0 10px 40px -10px rgba(0, 0, 0, 0.3);
        }

        /* TomSelect Customization */
        .ts-control {
            border-radius: 0.5rem;
            padding: 0.6rem 0.75rem;
            border-color: #e2e8f0;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }

        :root.dark .ts-control {
            background: #1e293b;
            border-color: #334155;
            color: #e2e8f0;
        }

        :root.dark .ts-dropdown {
            background: #1e293b;
            border-color: #334155;
            color: #e2e8f0;
        }

        :root.dark .ts-dropdown .active {
            background: #334155;
            color: #f1f5f9;
        }

        :root.dark .ts-wrapper.multi .ts-control>div {
            background: rgba(99, 102, 241, 0.2);
            color: #a5b4fc;
        }

        /* Range Slider */
        input[type=range] {
            -webkit-appearance: none;
            width: 100%;
            background: transparent;
        }

        input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none;
            height: 24px;
            width: 24px;
            border-radius: 50%;
            background: #4f46e5;
            cursor: pointer;
            margin-top: -10px;
            box-shadow: 0 2px 6px rgba(79, 70, 229, 0.4);
            transition: transform 0.1s;
        }

        input[type=range]::-webkit-slider-thumb:hover {
            transform: scale(1.1);
        }

        input[type=range]::-webkit-slider-runnable-track {
            width: 100%;
            height: 4px;
            cursor: pointer;
            background: #e2e8f0;
            border-radius: 2px;
        }

        :root.dark input[type=range]::-webkit-slider-runnable-track {
            background: #334155;
        }
    </style>

    <div class="max-w-4xl mx-auto py-8" x-data="simulationForm()">

        <!-- Header -->
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100 mb-2">Configurar Simulado</h1>
            <p class="text-slate-500">Personalize sua experiência de treino com foco total.</p>
        </div>

        <form method="POST" action="{{ route('simulations.store') }}" class="glass-card rounded-2xl p-6 md:p-10">
            @csrf

            <!-- Type Selector -->
            <div class="mb-10">
                <label
                    class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-4 uppercase tracking-wider">Tipo
                    de
                    Prova</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- ENEM Option -->
                    <label
                        class="relative flex items-center p-4 cursor-pointer rounded-xl border-2 transition-all duration-200"
                        :class="type === 'enem' ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600'">
                        <input type="radio" name="type" value="enem" class="sr-only" x-model="type">
                        <div class="flex-1">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-bold text-slate-900 dark:text-white">ENEM</span>
                                <span
                                    class="text-xs font-semibold px-2 py-1 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300"
                                    x-show="type === 'enem'">Selecionado</span>
                            </div>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Padrão oficial. 90 questões fixas
                                (Matemática e Linguagens).
                            </p>
                        </div>
                    </label>

                    <!-- Concurso Option -->
                    <label
                        class="relative flex items-center p-4 cursor-pointer rounded-xl border-2 transition-all duration-200"
                        :class="type === 'concurso' ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600'">
                        <input type="radio" name="type" value="concurso" class="sr-only" x-model="type">
                        <div class="flex-1">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-bold text-slate-900 dark:text-white">Concurso / Multidisciplinar</span>
                                <span
                                    class="text-xs font-semibold px-2 py-1 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300"
                                    x-show="type === 'concurso'">Selecionado</span>
                            </div>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Totalmente flexível. Escolha disciplinas,
                                bancas e quantidade.</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- ENEM Configuration Section -->
            <div x-show="type === 'enem'" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform -translate-y-2"
                x-transition:enter-end="opacity-100 transform translate-y-0" style="display: none;">
                <div class="p-6 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700">
                    <h3 class="font-semibold text-slate-800 dark:text-white mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        Modo de Aplicação
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <template x-for="mode in enemModes" :key="mode.value">
                            <label class="cursor-pointer">
                                <input type="radio" name="enem_mode" :value="mode.value" x-model="selectedEnemMode"
                                    class="sr-only">
                                <div class="px-4 py-3 rounded-lg border text-center transition-colors"
                                    :class="selectedEnemMode === mode.value ? 'bg-white dark:bg-slate-800 border-indigo-500 shadow-sm dark:shadow-none text-indigo-700 dark:text-indigo-300' : 'border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-800'">
                                    <span class="block font-medium" x-text="mode.label"></span>
                                    <span class="text-xs opacity-75" x-text="mode.desc"></span>
                                </div>
                            </label>
                        </template>
                    </div>

                    <!-- Hidden Inputs for ENEM logic compatibility -->
                    <input type="hidden" name="total_questions" value="90">
                    <input type="hidden" name="subject_distribution[Matemática]" :value="enemDistribution.math">
                    <input type="hidden" name="subject_distribution[Português]" :value="enemDistribution.port">
                </div>
            </div>

            <!-- Concurso Configuration Section -->
            <div x-show="type === 'concurso'" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform -translate-y-2"
                x-transition:enter-end="opacity-100 transform translate-y-0" style="display: none;">

                <!-- 1. Total Questions Slider -->
                <div
                    class="mb-10 p-6 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700">
                    <div class="flex justify-between items-end mb-6">
                        <label for="questionSlider"
                            class="font-semibold text-slate-800 dark:text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                            Volume de Questões
                        </label>
                        <span class="text-3xl font-bold text-indigo-600 dark:text-indigo-400"
                            x-text="totalQuestions + ' questões'"></span>
                    </div>
                    <input type="range" id="questionSlider" min="5" max="120" step="5" x-model.number="totalQuestions"
                        class="mb-2">
                    <div class="flex justify-between text-xs text-slate-400 font-medium px-1">
                        <span>5</span>
                        <span>60</span>
                        <span>120</span>
                    </div>
                    <input type="hidden" name="total_questions" :value="totalQuestions">
                </div>

                <!-- 2. Dynamic Subject Selector -->
                <div class="mb-10">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-semibold text-slate-800 dark:text-white uppercase tracking-wider text-sm">
                            Distribuição de Disciplinas</h3>
                        <div class="text-sm font-medium"
                            :class="totalAllocated === totalQuestions ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400'">
                            <span x-text="totalAllocated"></span> / <span x-text="totalQuestions"></span> alocadas
                        </div>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(subject, index) in subjects" :key="index">
                            <div
                                class="flex items-center gap-3 p-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm group">
                                <!-- Drag Handle (Visual only for now) -->
                                <div class="text-slate-300 dark:text-slate-600 cursor-grab">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 8h16M4 16h16" />
                                    </svg>
                                </div>

                                <!-- Subject Name Input -->
                                <div class="flex-1 relative">
                                    <input type="text" list="available-subjects"
                                        class="w-full border-0 border-b border-transparent focus:border-indigo-500 focus:ring-0 bg-transparent font-medium text-slate-700 dark:text-slate-200 placeholder-slate-400"
                                        x-model="subject.name"
                                        placeholder="Digite a disciplina (ex: Direito Administrativo...)" required>
                                </div>
                                <datalist id="available-subjects">
                                    @foreach($subjects as $sub)
                                        <option value="{{ $sub }}"></option>
                                    @endforeach
                                </datalist>

                                <!-- Quantity Input -->
                                <div class="w-24">
                                    <input type="number"
                                        class="w-full text-center rounded-md border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        x-model.number="subject.qty" min="1" :max="totalQuestions"
                                        @input="validateTotals()">
                                </div>

                                <!-- Remove Button -->
                                <button type="button" @click="removeSubject(index)"
                                    class="text-slate-400 hover:text-red-500 transition-colors p-2 rounded-full hover:bg-slate-100 dark:hover:bg-slate-700">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>

                                <!-- Hidden input for backend -->
                                <input type="hidden" :name="'subject_distribution[' + subject.name + ']'"
                                    :value="subject.qty">
                            </div>
                        </template>
                    </div>

                    <!-- Add Subject Button -->
                    <button type="button" @click="addSubject()"
                        class="mt-4 flex items-center gap-2 text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 px-4 py-2 rounded-lg transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Adicionar Disciplina
                    </button>

                    <!-- Validation Message -->
                    <div x-show="totalAllocated !== totalQuestions"
                        class="mt-2 text-sm text-amber-600 dark:text-amber-500 flex items-center gap-2 bg-amber-50 dark:bg-amber-900/20 p-3 rounded-lg">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        A soma das questões (:totalAllocated) deve ser igual ao volume total (:totalQuestions). Ajuste as
                        quantidades.
                    </div>
                </div>

                <!-- 3. Advanced Filters (Accordion) -->
                <div class="border-t border-slate-200 dark:border-slate-700 pt-6" x-data="{ expanded: false }">
                    <button type="button" @click="expanded = !expanded"
                        class="flex items-center justify-between w-full text-left group">
                        <div class="flex items-center gap-2">
                            <span
                                class="p-2 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-500 group-hover:text-indigo-600 transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                </svg>
                            </span>
                            <div>
                                <h4
                                    class="font-semibold text-slate-800 dark:text-white group-hover:text-indigo-600 transition-colors">
                                    Filtros do Edital (Opcional)</h4>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Restringir por Banca, Órgão ou Cargo
                                </p>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-slate-400 transform transition-transform duration-200"
                            :class="expanded ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="expanded" x-collapse style="display: none;">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
                            <!-- Banca -->
                            <div>
                                <label
                                    class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-2">Banca</label>
                                <select id="select-organization" name="organization[]" multiple
                                    placeholder="Selecione as bancas..." autocomplete="off">
                                    @foreach($organizations as $org)
                                        <option value="{{ $org }}">{{ $org }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Órgão -->
                            <div>
                                <label
                                    class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-2">Órgão</label>
                                <select id="select-institution" name="institution[]" multiple
                                    placeholder="Selecione os órgãos..." autocomplete="off">
                                    @foreach($institutions as $inst)
                                        <option value="{{ $inst }}">{{ $inst }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Cargo -->
                            <div>
                                <label
                                    class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-2">Cargo</label>
                                <select id="select-role" name="role[]" multiple placeholder="Selecione os cargos..."
                                    autocomplete="off">
                                    @foreach($roles as $role)
                                        <option value="{{ $role }}">{{ $role }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Footer -->
            <div
                class="mt-8 pt-6 border-t border-slate-200 dark:border-slate-700 flex flex-col md:flex-row items-center justify-between gap-4">
                @if($canUseEssayInSimulation)
                    {{-- Paid plan: normal interactive toggle --}}
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <div class="relative">
                            <input type="checkbox" name="include_essay" value="1" class="sr-only peer">
                            <div
                                class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-indigo-600">
                            </div>
                        </div>
                        <div>
                            <span class="block text-sm font-medium text-slate-700 dark:text-slate-300">Incluir Redação</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">Gera um tema dissertativo
                                extra</span>
                        </div>
                    </label>
                @else
                    {{-- Free plan: disabled toggle with upgrade message --}}
                    <div class="flex items-center gap-3 opacity-60 cursor-not-allowed"
                        onclick="alert('Esta funcionalidade está disponível nos planos Básico e Plus. Faça upgrade para incluir uma redação no simulado!')">
                        <div class="relative pointer-events-none">
                            <input type="checkbox" disabled class="sr-only peer">
                            <div
                                class="w-11 h-6 bg-slate-200 rounded-full dark:bg-slate-700 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5">
                            </div>
                        </div>
                        <div>
                            <span class="block text-sm font-medium text-slate-700 dark:text-slate-300 flex items-center gap-1">
                                🔒 Incluir Redação
                            </span>
                            <span class="block text-xs text-indigo-500 dark:text-indigo-400 font-semibold">Disponível nos planos
                                Básico e Plus</span>
                        </div>
                    </div>
                @endif

                <button type="submit" :disabled="(type === 'concurso' && totalAllocated !== totalQuestions) || submitting"
                    class="w-full md:w-auto px-8 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-500/30 transition-all transform hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none flex items-center justify-center gap-2">
                    <span x-show="!submitting">Iniciar Simulado</span>
                    <span x-show="submitting" class="animate-spin rounded-full h-5 w-5 border-b-2 border-white"></span>
                </button>
            </div>

        </form>
    </div>

    <!-- TomSelect JS -->
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('simulationForm', () => ({
                type: 'enem', // enem or concurso
                submitting: false,

                // ENEM State
                selectedEnemMode: 'mixed',
                enemModes: [
                    { value: 'mixed', label: 'Prova Completa', desc: '45 Mat + 45 Port' },
                    { value: 'math', label: 'Só Matemática', desc: '90 Questões' },
                    { value: 'português', label: 'Só Português', desc: '90 Questões' }
                ],

                // Concurso State
                totalQuestions: 60,
                subjects: [
                    { name: 'Matemática', qty: 30 },
                    { name: 'Português', qty: 30 }
                ],

                get enemDistribution() {
                    if (this.selectedEnemMode === 'math') return { math: 90, port: 0 };
                    if (this.selectedEnemMode === 'portuguese') return { math: 0, port: 90 };
                    return { math: 45, port: 45 };
                },

                get totalAllocated() {
                    return this.subjects.reduce((sum, sub) => sum + (parseInt(sub.qty) || 0), 0);
                },

                init() {
                    // Initialize TomSelects for Filters
                    const config = {
                        plugins: ['remove_button', 'dropdown_input'],
                        create: true, // Allow user to type new values
                        maxOptions: 50,
                        persist: false,
                        render: {
                            item: function (data, escape) {
                                return '<div class="px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300 text-xs font-semibold">' + escape(data.text) + '</div>';
                            }
                        }
                    };

                    new TomSelect('#select-organization', config);
                    new TomSelect('#select-institution', config);
                    new TomSelect('#select-role', config);

                    this.$watch('totalQuestions', (val) => {
                        // Intelligent auto-adjust when slider changes
                        // If we have subjects, try to redistribute proportionally or just warn user
                        // For simplicity MVP: verify logic only
                        this.validateTotals();
                    });
                },

                addSubject() {
                    this.subjects.push({ name: '', qty: 0 });
                },

                removeSubject(index) {
                    if (this.subjects.length > 1) {
                        this.subjects.splice(index, 1);
                    }
                },

                validateTotals() {
                    // Just triggers reactivity for totalAllocated
                }
            }));
        });
    </script>
@endsection
