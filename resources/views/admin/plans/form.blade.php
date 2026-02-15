<x-layouts.admin>
    <div class="mb-6 flex items-center gap-4">
        <a href="{{ route('admin.plans.index') }}" class="p-2 bg-white rounded-lg shadow-sm hover:shadow-md transition-all text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-800">{{ $plan->exists ? 'Editar Plano' : 'Novo Plano' }}</h1>
            <p class="text-gray-500">Defina os detalhes e limites do plano</p>
        </div>
    </div>

    @if($errors->any())
        <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r shadow-sm">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Erros encontrados:</h3>
                    <ul class="mt-1 list-disc list-inside text-sm text-red-700">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <form action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" method="POST">
        @csrf
        @if($plan->exists)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Info -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Informações Básicas</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nome do Plano</label>
                            <input type="text" name="name" value="{{ old('name', $plan->name) }}" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500" placeholder="Ex: Premium Mensal" required>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Preço (R$)</label>
                                <div class="relative rounded-md shadow-sm">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <span class="text-gray-500 sm:text-sm">R$</span>
                                    </div>
                                    <input type="number" name="price" step="0.01" min="0" value="{{ old('price', $plan->price) }}" class="block w-full rounded-lg border-gray-300 pl-10 focus:border-blue-500 focus:ring-blue-500 sm:text-sm" placeholder="0.00" required>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Intervalo de Cobrança</label>
                                <select name="interval" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="month" {{ old('interval', $plan->interval) == 'month' ? 'selected' : '' }}>Mensal</option>
                                    <option value="year" {{ old('interval', $plan->interval) == 'year' ? 'selected' : '' }}>Anual</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" /></svg>
                        Limites e Quotas (Mensal)
                    </h3>
                    <p class="text-sm text-gray-500 mb-4">Defina "0" para ilimitado.</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Limite de Simulados</label>
                            <input type="number" name="simulations_limit" min="0" value="{{ old('simulations_limit', $plan->simulations_limit) }}" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Limite de Redações</label>
                            <input type="number" name="essays_limit" min="0" value="{{ old('essays_limit', $plan->essays_limit) }}" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar Actions -->
            <div class="space-y-6">
                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Status & Publicação</h3>
                    
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-sm font-medium text-gray-700">Plano Ativo?</span>
                         <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ old('is_active', $plan->is_active ?? true) ? 'checked' : '' }}>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                    <p class="text-xs text-gray-500 mb-6">Planos inativos não aparecem na página de checkout, mas assinaturas existentes continuam funcionando.</p>

                    <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 font-bold shadow-lg shadow-blue-500/30 transition-all transform hover:-translate-y-0.5">
                        {{ $plan->exists ? 'Atualizar Plano' : 'Criar Plano' }}
                    </button>
                    
                    <a href="{{ route('admin.plans.index') }}" class="block w-full text-center mt-3 text-gray-500 hover:text-gray-700 text-sm">
                        Cancelar
                    </a>
                </div>

                @if($plan->exists)
                <div class="bg-blue-50 rounded-2xl p-6 border border-blue-100">
                    <h4 class="font-semibold text-blue-800 mb-2">Dica importante</h4>
                    <p class="text-sm text-blue-700">
                        Alterar o preço aqui afetará apenas <strong>novas assinaturas</strong>. Assinantes antigos continuarão pagando o valor original contratado.
                    </p>
                </div>
                @endif
            </div>
        </div>
    </form>
</x-layouts.admin>
