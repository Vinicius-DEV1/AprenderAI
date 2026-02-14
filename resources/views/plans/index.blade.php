@extends('layouts.app')

@section('page-title', 'Planos e Assinaturas')

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('info'))
                <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6" role="alert">
                    <p>{{ session('info') }}</p>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p>{{ session('error') }}</p>
                </div>
            @endif

            <div class="text-center mb-10">
                <h3 class="text-3xl font-bold text-gray-900">Escolha o plano ideal para sua aprovação</h3>
                <p class="mt-2 text-gray-600">Faça upgrade e desbloqueie correção detalhada por IA e planos de estudo.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                @foreach($plans as $plan)
                    <div
                        class="bg-white rounded-lg shadow-lg overflow-hidden flex flex-col {{ $userPlan->id === $plan->id ? 'border-2 border-blue-500 ring-2 ring-blue-200' : '' }}">
                        @if($userPlan->id === $plan->id)
                            <div class="bg-blue-500 text-white text-xs font-bold uppercase py-1 text-center">
                                Seu Plano Atual
                            </div>
                        @endif

                        <div class="p-8 flex-1">
                            <h4 class="text-2xl font-bold text-gray-900 text-center mb-4">{{ $plan->name }}</h4>
                            <div class="text-center mb-6">
                                <span class="text-4xl font-extrabold text-blue-600">R$
                                    {{ number_format($plan->price, 2, ',', '.') }}</span>
                                <span class="text-gray-500">/mês</span>
                            </div>

                            <ul class="space-y-4 text-gray-600 mb-8">
                                <li class="flex items-center">
                                    <svg class="h-5 w-5 text-green-500 mr-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    {{ $plan->simulations_limit > 0 ? $plan->simulations_limit . ' provas mensais' : 'Provas Ilimitadas' }}
                                </li>
                                <li class="flex items-center">
                                    <svg class="h-5 w-5 text-green-500 mr-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    {{ $plan->essays_limit > 0 ? $plan->essays_limit . ' redações mensais' : 'Redações Ilimitadas' }}
                                </li>
                                @if(in_array('ai_correction_detailed', $plan->features ?? []))
                                    <li class="flex items-center">
                                        <svg class="h-5 w-5 text-green-500 mr-2" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Correção detalhada por IA
                                    </li>
                                @endif
                                @if(in_array('study_plan', $plan->features ?? []))
                                    <li class="flex items-center">
                                        <svg class="h-5 w-5 text-green-500 mr-2" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Plano de estudos personalizado
                                    </li>
                                @endif
                            </ul>
                        </div>

                        <div class="p-8 bg-gray-50 border-t border-gray-100">
                            @if($userPlan->id === $plan->id)
                                <button disabled
                                    class="w-full block text-center bg-gray-300 text-gray-600 font-bold py-3 px-4 rounded cursor-not-allowed">
                                    Plano Atual
                                </button>
                            @else
                                <form action="{{ route('plans.checkout', $plan) }}" method="POST">
                                    @csrf
                                    <button type="submit"
                                        class="w-full block text-center bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded transition duration-200">
                                        {{ $plan->price > 0 ? 'Assinar Agora' : 'Mudar para Gratuito' }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-12 text-center text-gray-500 text-sm">
                <p>Pagamento seguro via Mercado Pago. Cancele quando quiser.</p>
            </div>
        </div>
    </div>
@endsection