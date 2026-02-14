<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center">
                <div class="mb-4 text-yellow-500">
                    <svg class="h-16 w-16 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h2 class="text-2xl font-bold mb-2">Pagamento Pendente</h2>
                <p class="text-gray-600 mb-6">Estamos processando seu pagamento. Assim que confirmado, seu plano será
                    ativado.</p>
                <a href="{{ route('dashboard') }}"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Voltar ao Dashboard</a>
            </div>
        </div>
    </div>
</x-app-layout>