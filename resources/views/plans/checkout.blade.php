<x-layouts.app>
    {{-- ======================================================= --}}
    {{-- SANDBOX BANNER: exibido quando o sistema está em testes  --}}
    {{-- (injetado via session pela middleware CheckPaymentActive) --}}
    {{-- ======================================================= --}}
    @if (session('sandbox_mode'))
        <div class="bg-amber-400 text-amber-900 text-center text-sm font-semibold py-2 px-4">
            🧪 <strong>Ambiente de Testes (Sandbox)</strong> — Nenhuma cobrança real será realizada.
            Use os <a href="https://asaasv3.docs.apiary.io/#introduction/sandbox" target="_blank"
                class="underline hover:text-amber-700">dados de teste do Asaas</a>.
        </div>
    @endif

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h2 class="text-2xl font-bold mb-6 text-gray-900">Finalizar Assinatura</h2>

                    <!-- Plan Summary -->
                    <div class="bg-blue-50 p-4 rounded-lg mb-8 flex justify-between items-center">
                        <div>
                            <span class="text-gray-600 block text-sm">Você está assinando:</span>
                            <span class="text-xl font-bold text-blue-800">{{ $plan->name }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-gray-600 block text-sm">Valor:</span>
                            <span class="text-2xl font-bold text-blue-800">R$
                                {{ number_format($plan->price, 2, ',', '.') }}</span>
                            <span
                                class="text-sm text-gray-500">/{{ $plan->interval === 'yearly' ? 'ano' : 'mês' }}</span>
                        </div>
                    </div>

                    <!-- Payment Method Tabs -->
                    <div x-data="{ method: 'credit_card' }">
                        <div class="flex border-b border-gray-200 mb-6">
                            <button @click="method = 'credit_card'"
                                :class="{ 'border-blue-500 text-blue-600': method === 'credit_card', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': method !== 'credit_card' }"
                                class="w-1/2 py-4 px-1 text-center border-b-2 font-medium text-sm focus:outline-none transition-colors">
                                Cartão de Crédito
                            </button>
                            <button @click="method = 'pix'"
                                :class="{ 'border-blue-500 text-blue-600': method === 'pix', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': method !== 'pix' }"
                                class="w-1/2 py-4 px-1 text-center border-b-2 font-medium text-sm focus:outline-none transition-colors">
                                Pix (Instantâneo)
                            </button>
                        </div>

                        <!-- Coupon Section -->
                        <div class="mb-6 bg-gray-50 p-4 rounded-md"
                            x-data="{ couponCode: '', couponMessage: '', couponSuccess: false, originalPrice: {{ $plan->price }}, finalPrice: {{ $plan->price }} }">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Possui um
                                cupom de desconto?</label>
                            <div class="flex gap-2">
                                <input type="text" x-model="couponCode" name="coupon_code" placeholder="Código do cupom"
                                    class="flex-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 uppercase">
                                <button type="button" @click="validateCoupon()"
                                    class="px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Aplicar
                                </button>
                            </div>
                            <p x-show="couponMessage" :class="couponSuccess ? 'text-green-600' : 'text-red-600'"
                                class="mt-2 text-sm" x-text="couponMessage"></p>

                            <div x-show="couponSuccess" class="mt-2 text-sm text-gray-600">
                                Novo valor: <span class="font-bold text-green-600">R$ <span
                                        x-text="finalPrice.toFixed(2).replace('.', ',')"></span></span>
                            </div>

                            <script>
                                function validateCoupon() {
                                    const code = this.couponCode;
                                    if (!code) return;

                                    fetch('{{ route('plans.validate-coupon', $plan) }}', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                        },
                                        body: JSON.stringify({ code: code })
                                    })
                                        .then(response => response.json())
                                        .then(data => {
                                            this.couponMessage = data.message;
                                            this.couponSuccess = data.valid;
                                            if (data.valid) {
                                                this.finalPrice = data.new_price;
                                            } else {
                                                this.finalPrice = this.originalPrice;
                                            }
                                        })
                                        .catch(error => {
                                            this.couponMessage = 'Erro ao validar cupom.';
                                            this.couponSuccess = false;
                                        });
                                }
                            </script>
                        </div>

                        <form action="{{ route('plans.store', $plan) }}" method="POST" id="payment-form">
                            @csrf
                            <input type="hidden" name="payment_method" :value="method">

                            <!-- Credit Card Form -->
                            <div x-show="method === 'credit_card'" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Nome no
                                        Cartão</label>
                                    <input type="text" name="card_name" placeholder="Como impresso no cartão"
                                        class="mt-1 block w-full border-gray-300 bg-white text-gray-900 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Número do
                                        Cartão</label>
                                    <input type="text" name="card_number" placeholder="0000 0000 0000 0000"
                                        class="mt-1 block w-full border-gray-300 bg-white text-gray-900 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div class="grid grid-cols-3 gap-4">
                                    <div class="col-span-2">
                                        <label class="block text-sm font-medium text-gray-700">Validade
                                            (MM/AA)</label>
                                        <div class="flex gap-2">
                                            <input type="text" name="card_expiry_month" placeholder="MM" maxlength="2"
                                                class="mt-1 block w-full border-gray-300 bg-white text-gray-900 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                            <input type="text" name="card_expiry_year" placeholder="AA" maxlength="2"
                                                class="mt-1 block w-full border-gray-300 bg-white text-gray-900 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">CVV</label>
                                        <input type="text" name="card_ccv" placeholder="123" maxlength="4"
                                            class="mt-1 block w-full border-gray-300 bg-white text-gray-900 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">CPF do
                                        Titular</label>
                                    <input type="text" name="card_cpf" placeholder="000.000.000-00"
                                        class="mt-1 block w-full border-gray-300 bg-white text-gray-900 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>

                            <!-- Pix Info -->
                            <div x-show="method === 'pix'" class="text-center py-8 space-y-4">
                                <div class="bg-gray-50 p-6 rounded-full inline-block">
                                    <svg class="w-16 h-16 text-green-500 mx-auto" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                                        </path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900">Pagamento via Pix</h3>
                                <p class="text-gray-500 max-w-sm mx-auto">
                                    Ao confirmar, será gerado um QR Code para pagamento instantâneo. Sua assinatura será
                                    ativada assim que o pagamento for confirmado.
                                </p>
                            </div>

                            <div class="mt-8">
                                <button type="submit"
                                    class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                    Confirmar Assinatura
                                </button>
                                <p class="mt-4 text-center text-xs text-gray-500">
                                    Ambiente seguro. Seus dados são criptografados.
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>