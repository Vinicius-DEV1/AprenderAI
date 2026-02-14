<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Gerenciar Chaves de API') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- Adicionar Nova Chave -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">AIzaSyA0ZSiRuR0T66mS-2KU-V23PcSL2uX-fvo</h3>
                <form action="{{ route('admin.api-keys.store') }}" method="POST" class="flex gap-4 items-end">
                    @csrf
                    <div>
                        <x-input-label for="provider" value="Provedor" />
                        <select name="provider" id="provider" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full">
                            <option value="gemini">Google Gemini</option>
                            <option value="openai">OpenAI (GPT-4)</option>
                            <option value="grok">Grok (xAI)</option>
                        </select>
                    </div>
                    <div class="flex-1">
                        <x-input-label for="key" value="Chave de API (sk-...)" />
                        <x-text-input id="key" name="key" type="password" class="mt-1 block w-full" required />
                    </div>
                    <x-primary-button>Salvar Chave</x-primary-button>
                </form>
            </div>

            <!-- Listagem -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if($keys->isEmpty())
                        <div class="text-center py-8 text-gray-500">
                            Nenhuma chave configurada. O sistema usará respostas simuladas.
                        </div>
                    @else
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Provedor</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uso</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Último Uso</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($keys as $key)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <span class="font-medium capitalize">{{ $key->provider }}</span>
                                                @if($key->is_primary)
                                                    <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                        Primária
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($key->is_active)
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                    Ativa
                                                </span>
                                            @else
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                    Inativa
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $key->requests_count }} reqs
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $key->last_used_at ? $key->last_used_at->diffForHumans() : '-' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <form action="{{ route('admin.api-keys.toggle', $key) }}" method="POST" class="inline">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                                    {{ $key->is_active ? 'Desativar' : 'Ativar' }}
                                                </button>
                                            </form>
                                            <form action="{{ route('admin.api-keys.destroy', $key) }}" method="POST" class="inline" onsubmit="return confirm('Tem certeza?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900">Remover</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
