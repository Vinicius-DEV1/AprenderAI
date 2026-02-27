<x-layouts.admin>
    <div class="max-w-4xl mx-auto">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Configurações do Site ⚙️</h1>
            <p class="text-gray-600">Gerencie as informações básicas da plataforma.</p>
        </div>

        @if(session('success'))
            <div class="mb-6 p-4 bg-green-100 border-l-4 border-green-500 text-green-700">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <form action="{{ route('admin.settings.update') }}" method="POST" class="p-8">
                @csrf
                
                <div class="grid grid-cols-1 gap-6">
                    <div>
                        <label for="site_name" class="block text-sm font-semibold text-gray-700 mb-2">Nome do Site</label>
                        <input type="text" name="site_name" id="site_name" 
                               value="{{ old('site_name', $settings['site_name'] ?? '') }}"
                               class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all outline-none"
                               placeholder="Ex: aprenderAI">
                        @error('site_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-2 text-xs text-gray-500 italic">
                            Este nome será exibido em e-mails, meta tags e em toda a interface do sistema.
                        </p>
                    </div>

                    <div>
                        <label for="ai_name" class="block text-sm font-semibold text-gray-700 mb-2">Nome do Assistente (IA)</label>
                        <input type="text" name="ai_name" id="ai_name" 
                               value="{{ old('ai_name', $settings['ai_name'] ?? 'Xavier') }}"
                               class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all outline-none"
                               placeholder="Ex: Xavier">
                        @error('ai_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-2 text-xs text-gray-500 italic">
                            O nome que o assistente inteligente usará para se identificar no chat e nas sugestões.
                        </p>
                    </div>

                    <div class="p-4 bg-blue-50 rounded-lg border border-blue-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-blue-900">Efeito Typewriter (Streaming)</h3>
                                <p class="text-xs text-blue-700">Faz o Xavier responder "letra por letra" em tempo real.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="ai_streaming_enabled" value="false">
                                <input type="checkbox" name="ai_streaming_enabled" value="true" class="sr-only peer" {{ ($settings['ai_streaming_enabled'] ?? 'false') === 'true' ? 'checked' : '' }}>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-100 flex justify-end">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg transition-all shadow-md hover:shadow-lg active:transform active:scale-95">
                            Salvar Alterações
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
