<x-layouts.app>
    <div class="min-h-[80vh] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 bg-slate-50">
        <div class="max-w-2xl w-full space-y-8 bg-white p-10 rounded-3xl shadow-xl border border-slate-100">
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-blue-100 rounded-full mb-6">
                    <span class="text-4xl text-blue-600">🚀</span>
                </div>
                <h2 class="text-3xl font-extrabold text-slate-900 mb-2">
                    Escolha Inteligente, {{ Auth::user()->name }}!
                </h2>
                <p class="text-lg text-slate-600">
                    Você selecionou o plano <span class="font-bold text-blue-600">{{ $plan->name }}</span>. 
                    Confirme sua assinatura para desbloquear todo o poder do AprovadoAI.
                </p>
            </div>

            <!-- Plan Details Card -->
            <div class="bg-slate-50 rounded-2xl p-8 border border-slate-200">
                <div class="flex items-baseline justify-center mb-6">
                    <span class="text-5xl font-extrabold text-slate-900">R$ {{ number_format($plan->price, 2, ',', '.') }}</span>
                    <span class="text-slate-500 ml-2 text-xl">/mês</span>
                </div>

                <div class="space-y-4">
                    <h3 class="font-semibold text-slate-800 uppercase tracking-wider text-sm mb-4">O que você terá acesso:</h3>
                    <ul class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($plan->features as $feature)
                        <li class="flex items-start">
                            <svg class="h-6 w-6 text-green-500 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            {{-- Simple mapping for readable features if needed, or just display raw --}}
                            <span class="text-slate-600 text-sm">
                                @php
                                    $labels = [
                                        'basic_correction' => 'Correção Básica',
                                        'detailed_correction' => 'Correções Detalhadas',
                                        'improvement_points' => 'Pontos de Melhoria',
                                        'essay_correction' => 'Correção de Redação',
                                        'advanced_correction' => 'Correção Premium',
                                        'personalized_study_plan' => 'Plano de Estudos Personalizado',
                                        'error_explanation' => 'Explicação de Erros',
                                        'unlimited_simulations' => 'Simulados Ilimitados',
                                        'essay_examples' => 'Exemplos de Redação',
                                        'performance_analysis' => 'Análise de Desempenho',
                                        'time_analysis' => 'Análise de Tempo',
                                    ];
                                @endphp
                                {{ $labels[$feature] ?? ucfirst(str_replace('_', ' ', $feature)) }}
                            </span>
                        </li>
                        @endforeach
                        <li class="flex items-start">
                            <svg class="h-6 w-6 text-green-500 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span class="text-slate-600 text-sm">
                                {{ $plan->simulations_limit == 0 ? 'Simulados Ilimitados' : $plan->simulations_limit . ' Simulados/mês' }}
                            </span>
                        </li>
                        @if($plan->essays_limit > 0)
                        <li class="flex items-start">
                            <svg class="h-6 w-6 text-green-500 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span class="text-slate-600 text-sm">
                                {{ $plan->essays_limit }} Redações/mês
                            </span>
                        </li>
                        @endif
                    </ul>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <a href="{{ route('plans.checkout', $plan) }}" 
                   class="flex justify-center items-center px-8 py-4 bg-blue-600 text-white font-bold rounded-xl text-lg shadow-lg hover:bg-blue-700 transition transform hover:-translate-y-1">
                    Confirmar Assinatura e Começar ⚡
                </a>
                
                <a href="{{ route('checkout.skip') }}" 
                   class="flex justify-center items-center px-8 py-4 bg-white text-slate-500 font-semibold rounded-xl text-lg border border-slate-200 hover:bg-slate-50 transition">
                    Assinar depois, ir para o Dashboard
                </a>
            </div>

            <p class="text-center text-xs text-slate-400">
                Garantia de satisfação: Cancele a qualquer momento.
            </p>
        </div>
    </div>
</x-layouts.app>
