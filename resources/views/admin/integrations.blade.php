<x-layouts.admin>
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-800">Integrações</h1>
        <p class="text-gray-600">Gerencie as integrações externas do sistema.</p>
    </div>

    <form action="{{ route('admin.integrations.update') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <!-- Google Login -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-8">
            <div class="p-6 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-white rounded-lg shadow-sm flex items-center justify-center">
                        <svg class="w-6 h-6 text-gray-700" viewBox="0 0 24 24" fill="currentColor">
                            <path
                                d="M12.545,10.239v3.821h5.445c-0.712,2.315-2.647,3.972-5.445,3.972c-3.332,0-6.033-2.701-6.033-6.032s2.701-6.032,6.033-6.032c1.498,0,2.866,0.549,3.921,1.453l2.814-2.814C17.503,2.988,15.139,2,12.545,2C7.021,2,2.543,6.477,2.543,12s4.478,10,10.002,10c8.396,0,10.249-7.85,9.426-11.748L12.545,10.239z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">Google Login</h2>
                        <p class="text-sm text-gray-500">Permitir que usuários façam login com suas contas do Google.
                        </p>
                    </div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="google_login_enabled" value="1" class="sr-only peer" {{ $google_login_enabled ? 'checked' : '' }}>
                    <div
                        class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600">
                    </div>
                </label>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Google Client ID</label>
                    <input type="text" name="google_client_id" value="{{ $google_client_id }}"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        placeholder="Ex: 123456789-abcdef...">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Google Client Secret</label>
                    <input type="password" name="google_client_secret"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        placeholder="Deixe em branco para manter o atual">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Redirect URI</label>
                    <input type="url" name="google_redirect_uri" value="{{ $google_redirect_uri }}"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 bg-gray-50"
                        readonly>
                    <p class="text-xs text-gray-500 mt-1">Adicione esta URL no console do Google Cloud.</p>
                </div>
            </div>
        </div>

        <!-- Google Analytics -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-8">
            <div class="p-6 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-white rounded-lg shadow-sm flex items-center justify-center">
                        <svg class="w-6 h-6 text-orange-500" viewBox="0 0 24 24" fill="currentColor">
                            <path
                                d="M4 19h4v-7H4v7zm6 0h4V9h-4v10zm6 0h4v-4h-4v4zm2-16C9.58 3 3 8 3 14c0 1.54.55 2.96 1.48 4.09l1.52-1.28C5.45 15.93 5 15.02 5 14c0-4.42 4.93-8 11-8s11 3.58 11 8c0 1.02-.45 1.93-1 2.81l1.52 1.28C22.45 16.96 23 15.54 23 14c0-6-6.58-11-14-11z" />
                            <path d="M0 0h24v24H0z" fill="none" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">Google Analytics 4</h2>
                        <p class="text-sm text-gray-500">Acompanhe o tráfego do site com o Google Analytics 4.</p>
                    </div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="analytics_enabled" value="1" class="sr-only peer" {{ $analytics_enabled ? 'checked' : '' }}>
                    <div
                        class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600">
                    </div>
                </label>
            </div>

            <div class="p-6">
                <!-- Measurement ID -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Measurement ID (G-XXXXXXXXXX)</label>
                    <input type="text" name="analytics_measurement_id" value="{{ $analytics_measurement_id }}"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        placeholder="G-ABC1234567">
                    <p class="text-xs text-gray-500 mt-1">Usado para rastreamento front-end nas páginas.</p>
                </div>

                <hr class="my-6 border-gray-100">
                <h3 class="text-md font-medium text-gray-800 mb-4">Módulo de Analytics Avançado (Painel)</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Property ID -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Property ID (GA4)</label>
                        <input type="text" name="analytics_property_id" value="{{ $analytics_property_id ?? '' }}"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="Ex: 123456789">
                        <p class="text-xs text-gray-500 mt-1">ID da Propriedade no Google Analytics 4 (Apenas números).
                        </p>
                    </div>

                    <!-- Sync Frequency -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Frequência de Sincronização</label>
                        <select name="analytics_sync_frequency"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="daily" {{ ($analytics_sync_frequency ?? 'daily') == 'daily' ? 'selected' : '' }}>Apenas Diária</option>
                            <option value="hourly" {{ ($analytics_sync_frequency ?? '') == 'hourly' ? 'selected' : '' }}>
                                Hora em Hora</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Define com que frequência os jobs rodam em background.</p>
                    </div>

                    <!-- Service Account JSON -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Service Account (JSON)</label>
                        <div class="flex items-center gap-4">
                            <input type="file" name="analytics_service_account_json" accept=".json" class="block w-full text-sm text-gray-500
                                file:mr-4 file:py-2 file:px-4
                                file:rounded-lg file:border-0
                                file:text-sm file:font-semibold
                                file:bg-blue-50 file:text-blue-700
                                hover:file:bg-blue-100">

                            @if(!empty($has_service_account))
                                <span
                                    class="inline-flex items-center gap-1.5 py-1.5 px-3 rounded-md text-xs font-medium bg-green-50 text-green-700 border border-green-200">
                                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    Arquivo Configurado
                                </span>
                            @else
                                <span
                                    class="inline-flex items-center gap-1.5 py-1.5 px-3 rounded-md text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">
                                    Não Configurado
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Faça upload do JSON de credenciais da Service Account
                            gerada no Google Cloud Consle.</p>
                    </div>

                    <!-- Connection Test Area (We will implement button to call a test connection route later or handle via alert) -->
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-6 rounded-lg transition-colors shadow-sm">
                Salvar Configurações
            </button>
        </div>
    </form>
</x-layouts.admin>