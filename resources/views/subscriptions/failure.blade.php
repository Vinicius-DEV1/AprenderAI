<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center">
                <div class="mb-4 text-red-500">
                    <svg class="h-16 w-16 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
                <h2 class="text-2xl font-bold mb-2">Pagamento Falhou ou Cancelado</h2>
                <p class="text-gray-600 mb-6">Não foi possível processar seu pagamento. Tente novamente.</p>
                <a href="{{ route('plans.index') }}"
                    class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">Tentar Outro Plano</a>
            </div>
        </div>
    </div>
</x-app-layout>