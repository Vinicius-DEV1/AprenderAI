<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-slate-200 leading-tight">
            {{ __('Recarga de Redações') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-md mx-auto sm:px-6 lg:px-8">
            <div
                class="bg-white dark:bg-slate-900 overflow-hidden shadow-sm dark:shadow-none dark:border dark:border-slate-700 sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-slate-200">
                    <h3 class="text-lg font-medium mb-4">Confirmar Recarga</h3>

                    <div class="mb-6 p-4 bg-gray-50 dark:bg-slate-800 rounded-lg border dark:border-slate-700">
                        <p class="text-sm text-gray-500 dark:text-slate-400">Plano Atual: {{ $planName }}</p>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-xl font-bold">+{{ $credits }} Redações</span>
                            <span class="text-xl font-bold text-green-600 dark:text-green-400">R$
                                {{ number_format($price, 2, ',', '.') }}</span>
                        </div>
                    </div>

                    <form action="{{ route('recharge') }}" method="POST">
                        @csrf

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Forma de
                                Pagamento</label>
                            <div class="space-y-2">
                                <label
                                    class="flex items-center space-x-3 p-3 border dark:border-slate-700 rounded cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-800">
                                    <input type="radio" name="payment_method" value="credit_card" checked
                                        class="text-blue-600 focus:ring-blue-500 dark:bg-slate-900 dark:border-slate-600">
                                    <span>Cartão de Crédito</span>
                                </label>
                                <label
                                    class="flex items-center space-x-3 p-3 border dark:border-slate-700 rounded cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-800">
                                    <input type="radio" name="payment_method" value="pix"
                                        class="text-blue-600 focus:ring-blue-500 dark:bg-slate-900 dark:border-slate-600">
                                    <span>Pix</span>
                                </label>
                            </div>
                        </div>

                        <!-- Card Fields (Simplified for Surgical Implementation) -->
                        <div id="card-fields" class="space-y-4 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Nome no
                                    Cartão</label>
                                <input type="text" name="card_name"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Número do
                                    Cartão</label>
                                <input type="text" name="card_number"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Mês
                                        (MM)</label>
                                    <input type="text" name="card_expiry_month" maxlength="2"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Ano
                                        (AAAA)</label>
                                    <input type="text" name="card_expiry_year" maxlength="4"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                </div>
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-700 dark:text-slate-300">CCV</label>
                                    <input type="text" name="card_ccv" maxlength="4"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">CPF do
                                    Titular</label>
                                <input type="text" name="card_cpf"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                        </div>

                        <div class="flex items-center justify-end">
                            <a href="{{ route('essays.index') }}"
                                class="text-sm text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-slate-200 mr-4">Cancelar</a>
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-500 active:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 transition ease-in-out duration-150">
                                Pagar R$ {{ number_format($price, 2, ',', '.') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Toggle Card Fields -->
    <script>
        document.querySelectorAll('input[name="payment_method"]').forEach(elem => {
            elem.addEventListener('change', function () {
                const cardFields = document.getElementById('card-fields');
                if (this.value === 'credit_card') {
                    cardFields.style.display = 'block';
                    // Re-enable required inputs if needed
                } else {
                    cardFields.style.display = 'none';
                }
            });
        });
    </script>
</x-app-layout>