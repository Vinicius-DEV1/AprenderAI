<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center">
                @if(isset($pix_payload))
                    <div class="mb-4 text-green-500">
                        <svg class="h-16 w-16 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold mb-2">Pagamento via Pix Gerado!</h2>
                    <p class="text-gray-600 mb-6">Escaneie o QR Code abaixo ou use o código Copia e Cola para finalizar.</p>

                    <div class="flex justify-center mb-6">
                        <img src="data:image/jpeg;base64,{{ $pix_image }}" alt="QR Code Pix" class="border p-2 rounded-lg shadow-sm" style="max-width: 300px;">
                    </div>

                    <div class="max-w-xl mx-auto mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pix Copia e Cola</label>
                        <div class="flex">
                            <input type="text" id="pix-code" readonly value="{{ $pix_payload }}"
                                class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-l-md bg-gray-50">
                            <button onclick="copyPixCode()"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-r-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Copiar
                            </button>
                        </div>
                        <p id="copy-feedback" class="text-sm text-green-600 mt-2 hidden">Código copiado com sucesso!</p>
                    </div>

                    <script>
                        function copyPixCode() {
                            var copyText = document.getElementById("pix-code");
                            copyText.select();
                            copyText.setSelectionRange(0, 99999); 
                            navigator.clipboard.writeText(copyText.value);
                            
                            document.getElementById("copy-feedback").classList.remove("hidden");
                            setTimeout(function(){
                                document.getElementById("copy-feedback").classList.add("hidden");
                            }, 3000);
                        }
                    </script>
                @else
                    <div class="mb-4 text-yellow-500">
                        <svg class="h-16 w-16 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold mb-2">Pagamento Pendente</h2>
                    <p class="text-gray-600 mb-6">Estamos processando seu pagamento. Assim que confirmado, seu plano será
                        ativado.</p>
                @endif
                <a href="{{ route('dashboard') }}"
                    class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">Voltar ao Dashboard</a>
            </div>
        </div>
    </div>
</x-app-layout>