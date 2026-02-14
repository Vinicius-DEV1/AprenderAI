<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Painel Administrativo') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <!-- Users Card -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-blue-500">
                    <div class="text-gray-500 text-sm uppercase tracking-wide">Usuários</div>
                    <div class="text-3xl font-bold text-gray-800">{{ $users_count }}</div>
                </div>

                <!-- Simulations Card -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-green-500">
                    <div class="text-gray-500 text-sm uppercase tracking-wide">Provas Realizadas</div>
                    <div class="text-3xl font-bold text-gray-800">{{ $simulations_count }}</div>
                </div>

                <!-- Essays Card -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-purple-500">
                    <div class="text-gray-500 text-sm uppercase tracking-wide">Redações Enviadas</div>
                    <div class="text-3xl font-bold text-gray-800">{{ $essays_count }}</div>
                </div>

                <!-- API Keys Card -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-yellow-500">
                    <div class="text-gray-500 text-sm uppercase tracking-wide">Chaves de API</div>
                    <div class="text-3xl font-bold text-gray-800">{{ $api_keys_count }}</div>
                    <a href="{{ route('admin.api-keys') }}"
                        class="text-sm text-blue-600 hover:underline mt-2 block">Gerenciar &rarr;</a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>