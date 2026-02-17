<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">
            {{ __('Plano de Estudos Personalizado') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-8 text-center border">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                </div>

            </div>

            <h3 class="text-2xl font-bold text-slate-900 mb-2">
                Vamos conhecer seu nível primeiro?
            </h3>

            </h3>

            <p class="text-slate-600 mb-8 max-w-lg mx-auto">
                Para que a IA possa criar um plano realmente efetivo e personalizado,
                precisamos de dados sobre seu desempenho atual. Realize pelo menos um simulado completo.
            </p>

            <div class="flex flex-col sm:flex-row justify-center gap-4">
                <a href="{{ route('simulations.create', ['type' => 'enem']) }}"
                    class="inline-flex items-center justify-center px-6 py-3 bg-blue-600 border border-transparent rounded-md font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                    Fazer Simulado ENEM
                </a>
                <a href="{{ route('simulations.create', ['type' => 'concurso']) }}"
                    class="inline-flex items-center justify-center px-6 py-3 bg-white border border-slate-300 rounded-md font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                    Fazer Simulado Concurso
                </a>
            </div>
        </div>
    </div>
    </div>
</x-app-layout>