<x-layouts.admin>
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Configurações de Pagamento</h1>
        <p class="text-gray-600">Gerencie a integração com o gateway Asaas e status dos pagamentos.</p>
    </div>

    <!-- Main Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-xl font-semibold text-gray-800">Integração Asaas</h2>
        </div>

        <form action="{{ route('admin.payment-settings.update') }}" method="POST" class="p-6 space-y-8">
            @csrf

            <!-- API Key Section -->
            <div class="space-y-4">
                <label for="asaas_api_key" class="block text-sm font-medium text-gray-700">Chave da API (API Key)</label>
                <div class="relative rounded-md shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z">
                            </path>
                        </svg>
                    </div>
                    <input type="text" name="asaas_api_key" id="asaas_api_key"
                        value="{{ $asaas_api_key }}"
                        class="focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 sm:text-sm border-gray-300 rounded-lg p-2.5"
                        placeholder="Ex: $aact_..." autocomplete="off">
                </div>
                <p class="text-sm text-gray-500">
                    A chave de API pode ser obtida no painel do Asaas em <strong>Minha Conta > Integração</strong>.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Sandbox Mode -->
                <div class="relative flex items-start p-4 bg-gray-50 rounded-xl border border-gray-200">
                    <div class="flex items-center h-5">
                        <input id="asaas_sandbox" name="asaas_sandbox" type="checkbox" value="1"
                            {{ $asaas_sandbox ? 'checked' : '' }}
                            class="focus:ring-blue-500 h-5 w-5 text-blue-600 border-gray-300 rounded transition-colors duration-200">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="asaas_sandbox" class="font-medium text-gray-700 block mb-1">Modo Sandbox (Testes)</label>
                        <p class="text-gray-500">
                            Quando ativado, todas as transações serão feitas no ambiente de homologação do Asaas.
                            <span class="block mt-1 text-amber-600 font-medium">⚠️ Não use dados reais neste
                                modo.</span>
                        </p>
                    </div>
                </div>

                <!-- Payment Active -->
                <div class="relative flex items-start p-4 bg-gray-50 rounded-xl border border-gray-200">
                    <div class="flex items-center h-5">
                        <input id="payment_active" name="payment_active" type="checkbox" value="1"
                            {{ $payment_active ? 'checked' : '' }}
                            class="focus:ring-green-500 h-5 w-5 text-green-600 border-gray-300 rounded transition-colors duration-200">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="payment_active" class="font-medium text-gray-700 block mb-1">Sistema de Pagamentos Ativo</label>
                        <p class="text-gray-500">
                            Desative para entrar em "Manutenção". Usuários não conseguirão realizar novas assinaturas.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Warning Alert if Sandbox is ON -->
            @if ($asaas_sandbox)
                <div class="bg-amber-50 border-l-4 border-amber-400 p-4 rounded-r-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-amber-700">
                                O sistema está rodando em <strong>Modo Sandbox</strong>. Transações financeiras reais
                                não serão processadas.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="pt-6 border-t border-gray-100 flex items-center justify-end">
                <button type="submit"
                    class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                    <svg class="w-5 h-5 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4">
                        </path>
                    </svg>
                    Salvar Configurações
                </button>
            </div>
        </form>
    </div>
</x-layouts.admin>
