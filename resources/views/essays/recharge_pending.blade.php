<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-slate-200 leading-tight">
            {{ __('Pagamento Pendente') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-md mx-auto sm:px-6 lg:px-8">
            <div
                class="bg-white dark:bg-slate-900 overflow-hidden shadow-sm dark:shadow-none dark:border dark:border-slate-700 sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-slate-200 text-center">
                    <h3 class="text-lg font-medium mb-4">Pagamento via Pix</h3>

                    <div class="mb-6">
                        <p class="text-sm text-gray-500 dark:text-slate-400 mb-4">Escaneie o QR Code abaixo para pagar
                            R$ {{ number_format($price, 2, ',', '.') }} e liberar suas +{{ $credits }} redações.</p>

                        <div class="flex justify-center mb-4">
                            <img src="data:image/png;base64,{{ $pix_image }}" alt="QR Code Pix"
                                class="border p-2 rounded-lg">
                        </div>

                        <div class="mb-4">
                            <label
                                class="block text-xs font-bold uppercase text-gray-500 dark:text-slate-400 mb-1">Copia e
                                Cola</label>
                            <textarea readonly
                                class="w-full text-xs p-2 border rounded bg-gray-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300"
                                rows="3">{{ $pix_payload }}</textarea>
                        </div>
                    </div>

                    <div class="mt-6">
                        <a href="{{ route('essays.index') }}"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 active:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 transition ease-in-out duration-150">
                            Voltar para Redações
                        </a>
                        <p class="mt-2 text-xs text-gray-500 dark:text-slate-400">Seus créditos serão liberados assim
                            que o pagamento for confirmado.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>