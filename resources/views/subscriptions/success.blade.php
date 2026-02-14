<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center">
                <div class="mb-4 text-green-500">
                    <svg class="h-16 w-16 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h2 class="text-2xl font-bold mb-2">Pagamento Concluído!</h2>
                <p class="text-gray-600 mb-6">Sua assinatura foi ativada com sucesso. Aproveite todos os recursos.</p>
                <a href="{{ route('dashboard') }}"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Ir para o Dashboard</a>
            </div>
        </div>
    </div>
</x-app-layout>