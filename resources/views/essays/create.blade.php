<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Nova Redação') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">

                <!-- Steps Indicator -->
                <div class="border-b border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center justify-center space-x-8">
                        <div class="text-blue-600 font-bold">1. Tipo e Tempo</div>
                        <div class="w-12 h-0.5 bg-gray-300"></div>
                        <div class="text-gray-400">2. Tema</div>
                        <div class="w-12 h-0.5 bg-gray-300"></div>
                        <div class="text-gray-400">3. Escrita</div>
                    </div>
                </div>

                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form action="{{ route('essays.store') }}" method="POST" class="space-y-6">
                        @csrf

                        <h3 class="text-lg font-medium">Escolha o formato</h3>

                        <div>
                            <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Tipo de
                                Redação</label>
                            <select name="type"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="enem">ENEM</option>
                                <option value="concurso">Concurso Público</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Tempo
                                Disponível</label>
                            <select name="time_limit"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="30">30 minutos</option>
                                <option value="45">45 minutos</option>
                                <option value="60" selected>60 minutos (1 hora)</option>
                                <option value="90">90 minutos (1h 30m)</option>
                                <option value="120">120 minutos (2 horas)</option>
                            </select>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                                Continuar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>