<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-200 leading-tight">
            {{ __('Plano de Estudos Personalizado') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl dark:shadow-none sm:rounded-lg p-8 text-center relative border dark:border-slate-700">
                <!-- Lock Icon -->
                <div class="absolute top-4 right-4 text-slate-100 dark:text-slate-800">
                    <svg class="w-24 h-24" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 17a2 2 0 100-4 2 2 0 000 4zm6-9v2H6V8h12zm2-2H4v2h16V6zM4 22v-8h16v8H4z" />
                    </svg>
                </div>

                <div class="relative z-10 max-w-2xl mx-auto">
                    <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>

                </div>

                <h3 class="text-3xl font-bold text-slate-900 dark:text-slate-100 mb-4">
                    Desbloqueie seu Potencial Máximo
                </h3>

                </h3>

                <p class="text-lg text-slate-600 dark:text-slate-400 mb-8">
                    O Plano de Estudos Personalizado com IA é um recurso exclusivo para membros
                    <span class="font-bold text-purple-600">PLUS</span>.
                </p>

                <div class="grid md:grid-cols-2 gap-6 text-left mb-10">
                    <div class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-green-500 flex-shrink-0" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <div>
                            <h4 class="font-semibold text-slate-900 dark:text-slate-200">Cronograma Inteligente</h4>
                            <p class="text-sm text-slate-500 dark:text-slate-400">A IA organiza sua rotina baseada nos
                                seus horários.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-green-500 flex-shrink-0" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <div>
                            <h4 class="font-semibold text-slate-900 dark:text-slate-200">Foco nas Dificuldades</h4>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Cronograma adaptado aos seus pontos
                                fracos.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-green-500 flex-shrink-0" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <div>
                            <h4 class="font-semibold text-slate-900 dark:text-slate-200">Evolução Constante</h4>
                            <p class="text-sm text-slate-500 dark:text-slate-400">O plano se atualiza conforme você
                                estuda.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-green-500 flex-shrink-0" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <div>
                            <h4 class="font-semibold text-slate-900 dark:text-slate-200">Estratégia de Aprovação</h4>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Metodologia validada por
                                especialistas.</p>
                        </div>
                    </div>
                </div>

                <a href="{{ route('plans.index') }}"
                    class="inline-flex items-center px-8 py-4 bg-purple-600 border border-transparent rounded-lg font-bold text-white uppercase tracking-widest hover:bg-purple-700 active:bg-purple-900 focus:outline-none focus:border-purple-900 focus:ring ring-purple-300 disabled:opacity-25 transition ease-in-out duration-150 shadow-lg transform hover:-translate-y-1">
                    Fazer Upgrade para PLUS agora
                </a>
            </div>
        </div>
    </div>
    </div>
</x-app-layout>
