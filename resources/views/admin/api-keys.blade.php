<x-layouts.admin>
    <!-- Header -->
    <div class="mb-8 flex justify-between items-start">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">SRE Dashboard: APIs</h1>
            <p class="text-gray-600">Monitoramento e controle de provedores de IA.</p>
        </div>
        @if($hasRecentErrors)
            <div class="bg-red-50 border-l-4 border-red-400 p-4 animate-pulse">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <span class="text-red-400">⚠️</span>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700 font-bold">
                            ALERTA CRÍTICO: Detectamos erros nas últimas 6 horas.
                        </p>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="max-w-7xl mx-auto space-y-6">
        
        <!-- Adicionar Nova Chave -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Adicionar/Testar Nova Chave</h3>
            <form action="{{ route('admin.api-keys.store') }}" method="POST" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="provider" value="Provedor" />
                        <select name="provider" id="provider" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" onchange="resetValidation()">
                            <option value="gemini">Google Gemini</option>
                            <option value="openai">OpenAI (GPT-4)</option>
                            <option value="grok">Grok (xAI)</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label for="key" value="Chave de API" />
                        <div class="flex gap-2">
                            <x-text-input id="key" name="key" type="password" class="mt-1 block w-full" required placeholder="Insira a chave para testar" oninput="resetValidation()" />
                            <button type="button" onclick="testConnection()" id="btn-test" class="mt-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors flex items-center gap-2">
                                <span id="btn-text">Testar</span>
                                <span id="btn-loader" class="hidden animate-spin">⌛</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Model Selection (Hidden by default) -->
                <div id="model-section" class="hidden bg-indigo-50 p-4 rounded-md border border-indigo-100">
                    <x-input-label for="preferred_model" value="Modelo Preferido (Detectado)" />
                    <select name="preferred_model" id="preferred_model" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full">
                        <!-- Populated via JS -->
                    </select>
                    <p class="text-xs text-indigo-600 mt-1">✓ Chave validada com sucesso. Selecione o modelo para uso.</p>
                </div>

                <div id="feedback-area" class="hidden p-4 rounded-md text-sm"></div>

                <div class="flex justify-end">
                    <x-primary-button id="btn-save" class="opacity-50 cursor-not-allowed" disabled>Salvar Configuração</x-primary-button>
                </div>
            </form>
        </div>

        <!-- Listagem e Status -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Chaves Ativas e Saúde</h3>
                @if($keys->isEmpty())
                    <div class="text-center py-8 text-gray-500">Nenhuma chave configurada.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Provedor / Modelo</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Saúde (SRE)</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Check / Adição</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Uso Acumulado</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($keys as $key)
                                        <tr>
                                            <td class="px-6 py-4">
                                                <div class="flex flex-col">
                                                    <span class="font-bold capitalize text-gray-900 text-base">{{ $key->provider }}</span>
                                                    <span class="text-xs font-mono bg-indigo-50 text-indigo-700 px-1 rounded inline-block w-fit">
                                                        {{ $key->preferred_model ?? 'Padrão' }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-2">
                                                    @php
                                                        $statusClasses = match($key->status) {
                                                            'online' => 'bg-green-100 text-green-800',
                                                            'offline' => 'bg-red-100 text-red-800',
                                                            'quota_exceeded' => 'bg-yellow-100 text-yellow-800',
                                                            default => 'bg-gray-100 text-gray-800'
                                                        };
                                                        $statusLabel = match($key->status) {
                                                            'online' => '🟢 Online',
                                                            'offline' => '🔴 Offline',
                                                            'quota_exceeded' => '🟡 Quota Exceeded',
                                                            default => '⚪ Desconhecido'
                                                        };
                                                    @endphp
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusClasses }}">
                                                        {{ $statusLabel }}
                                                    </span>
                                                    
                                                    <form action="{{ route('admin.api-keys.retest', $key) }}" method="POST" class="inline">
                                                        @csrf
                                                        <button type="submit" title="Forçar reteste agora" class="text-gray-400 hover:text-indigo-600 transition-colors">
                                                            🔄
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex flex-col text-xs text-gray-500">
                                                    <span><strong>Check:</strong> {{ $key->last_health_check_at ? $key->last_health_check_at->diffForHumans() : 'Nunca' }}</span>
                                                    <span><strong>Criado:</strong> {{ $key->created_at->format('d/m/y H:i') }}</span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500 font-mono">
                                                {{ number_format($key->requests_count) }} reqs
                                            </td>
                                            <td class="px-6 py-4 text-right text-sm">
                                                 <form action="{{ route('admin.api-keys.toggle', $key) }}" method="POST" class="inline">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="text-indigo-600 hover:text-indigo-900 mr-2 p-1 border rounded hover:bg-indigo-50" title="{{ $key->is_active ? 'Desativar' : 'Ativar' }}">
                                                        {{ $key->is_active ? '🔓' : '🔒' }}
                                                    </button>
                                                </form>
                                                <form action="{{ route('admin.api-keys.destroy', $key) }}" method="POST" class="inline" onsubmit="return confirm('Apagar chave?');">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-900 p-1 border rounded hover:bg-red-50" title="Excluir">🗑️</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <!-- Histórico de Uso (AI Requests) -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100" 
             x-data="{ 
                openModal: false, 
                activeLog: {
                    user: '',
                    provider: '',
                    model: '',
                    prompt: '',
                    response: '',
                    input_tokens: 0,
                    output_tokens: 0,
                    execution_time: 0,
                    estimated_cost: 0
                } 
             }">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-medium text-gray-900">Histórico de Uso (Últimas 20 Transações)</h3>
                <p class="text-xs text-gray-500">Log detalhado de prompts e respostas enviadas para a IA.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Usuário</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Provedor / Modelo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tokens (I/O)</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tempo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Custo (R$)</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($aiLogs as $log)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $log->user ? $log->user->name : 'Sistema/Job' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-bold text-gray-800">{{ $log->api_key_name }}</span>
                                        <span class="text-xs text-gray-400">{{ $log->model }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600 font-mono">
                                    {{ $log->tokens_used_input }} / {{ $log->tokens_used_output }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">
                                    {{ round($log->execution_time, 2) }}s
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs font-bold text-gray-700">
                                    R$ {{ number_format($log->estimated_cost, 4, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button 
                                        @click="activeLog = {
                                            user: {{ json_encode($log->user ? $log->user->name : 'Sistema/Job') }},
                                            provider: {{ json_encode($log->provider) }},
                                            model: {{ json_encode($log->model) }},
                                            prompt: {{ json_encode($log->prompt_text) }},
                                            response: {{ json_encode($log->response_text) }},
                                            input_tokens: {{ $log->tokens_used_input }},
                                            output_tokens: {{ $log->tokens_used_output }},
                                            execution_time: {{ round($log->execution_time, 3) }},
                                            estimated_cost: {{ round($log->estimated_cost, 4) }}
                                        }; openModal = true;"
                                        class="text-indigo-600 hover:text-indigo-900 text-xs font-bold border border-indigo-100 px-2 py-1 rounded hover:bg-indigo-50">
                                        Ver Detalhes
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500">Nenhuma transação registrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Modal -->
            <div x-show="openModal" class="fixed inset-0 z-[100] overflow-y-auto" style="display: none;">
                <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="openModal" @click="openModal = false" class="fixed inset-0 transition-opacity" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                        <div class="absolute inset-0 bg-gray-900 opacity-75"></div>
                    </div>

                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen"></span>&#8203;

                    <div x-show="openModal" class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full border border-gray-200" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
                        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                            <h3 class="text-lg font-bold text-gray-800">Detalhes da Transação IA</h3>
                            <button @click="openModal = false" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                        <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto font-sans">
                            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 text-[10px] uppercase tracking-wider font-bold text-gray-400">
                                <div>
                                    <p>Usuário</p>
                                    <p class="text-gray-800 text-xs" x-text="activeLog.user"></p>
                                </div>
                                <div>
                                    <p>Provedor / Modelo</p>
                                    <p class="text-gray-800 text-xs" x-text="activeLog.provider + ' / ' + activeLog.model"></p>
                                </div>
                                <div>
                                    <p>Tokens (In / Out)</p>
                                    <p class="text-gray-800 text-xs" x-text="activeLog.input_tokens + ' / ' + activeLog.output_tokens"></p>
                                </div>
                                <div>
                                    <p>Tempo de Execução</p>
                                    <p class="text-gray-800 text-xs" x-text="activeLog.execution_time + 's'"></p>
                                </div>
                                <div>
                                    <p>Custo Estimado</p>
                                    <p class="text-indigo-600 text-xs font-bold" x-text="'R$ ' + activeLog.estimated_cost.toFixed(4)"></p>
                                </div>
                            </div>

                            <hr class="border-gray-100">

                            <div>
                                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Prompt Enviado</h4>
                                <div class="bg-gray-900 text-green-400 p-4 rounded-lg font-mono text-[11px] overflow-x-auto whitespace-pre-wrap border border-gray-800 shadow-inner" x-text="activeLog.prompt"></div>
                            </div>

                            <div>
                                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Resposta da IA</h4>
                                <div class="bg-indigo-50 text-indigo-900 p-4 rounded-lg font-mono text-[11px] overflow-x-auto whitespace-pre-wrap border border-indigo-100 shadow-inner" x-text="activeLog.response"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ranking de Consumo (Top 20 Usuários) -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100">
            <div class="p-6 border-b border-gray-100 bg-gray-50/50">
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <span>🏆</span>
                    Top 20 Usuários - Consumo de IA
                </h3>
                <p class="text-xs text-gray-500">Ranking baseado no volume total de tokens consumidos.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">#</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Usuário</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Total Tokens</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Investimento (R$)</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider font-mono">Média/Req</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @forelse($aiRanking as $index => $stat)
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold {{ $index < 3 ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $index + 1 }}º
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="text-sm">
                                            <a href="{{ route('admin.users.show', $stat->user_id) }}" class="font-bold text-indigo-600 hover:text-indigo-900">
                                                {{ $stat->user ? $stat->user->name : 'N/A' }}
                                            </a>
                                            <div class="text-xs text-gray-400 font-mono">{{ $stat->user ? $stat->user->email : 'N/A' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-700">
                                    {{ number_format($stat->total_tokens, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-bold text-green-600">R$ {{ number_format($stat->total_cost, 2, ',', '.') }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 font-mono">
                                    {{ number_format($stat->total_tokens / $stat->request_count, 0, ',', '.') }} tk/req
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500">Sem dados de consumo suficientes para gerar o ranking.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Logs de Atividade -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">Logs de Eventos da API (Últimos 20)</h3>
                <form action="{{ route('admin.api-keys.clear-logs') }}" method="POST" onsubmit="return confirm('Limpar histórico?')">
                    @csrf
                    <button type="submit" class="text-sm text-gray-500 hover:text-red-600">Limpar Histórico</button>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <tbody class="bg-white divide-y divide-gray-50">
                        @forelse($logs as $log)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-400 font-mono">
                                    {{ $log->created_at->format('H:i:s') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $typeClasses = match($log->type) {
                                            'success' => 'text-green-600',
                                            'error' => 'text-red-600 font-bold',
                                            'warning' => 'text-yellow-600',
                                            'fallback' => 'text-purple-600 font-bold',
                                            default => 'text-gray-600'
                                        };
                                        $typeEmoji = match($log->type) {
                                            'success' => '✅',
                                            'error' => '❌',
                                            'warning' => '⚠️',
                                            'fallback' => '🔄',
                                            default => '🔹'
                                        };
                                    @endphp
                                    <span class="text-sm {{ $typeClasses }}">
                                        {{ $typeEmoji }} {{ strtoupper($log->type) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <span class="font-bold text-gray-800 capitalize">{{ $log->provider }}:</span>
                                    <span class="{{ $log->status_code == 429 ? 'text-red-700 font-bold' : '' }}">
                                        {{ $log->message }}
                                    </span>
                                    @if($log->status_code)
                                        <span class="text-xs {{ $log->status_code == 429 ? 'bg-red-100 text-red-800 border border-red-200' : 'bg-gray-100' }} px-1 rounded">
                                            Code: {{ $log->status_code }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-gray-500">Sem atividade recente.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function resetValidation() {
            document.getElementById('model-section').classList.add('hidden');
            document.getElementById('feedback-area').classList.add('hidden');
            document.getElementById('btn-save').disabled = true;
            document.getElementById('btn-save').classList.add('opacity-50', 'cursor-not-allowed');
        }

        async function testConnection() {
            const provider = document.getElementById('provider').value;
            const key = document.getElementById('key').value;
            const btnTest = document.getElementById('btn-test');
            const btnText = document.getElementById('btn-text');
            const btnLoader = document.getElementById('btn-loader');
            const feedback = document.getElementById('feedback-area');
            const modelSection = document.getElementById('model-section');
            const modelSelect = document.getElementById('preferred_model');
            const btnSave = document.getElementById('btn-save');

            if (!key) {
                alert('Por favor, insira uma chave.');
                return;
            }

            btnTest.disabled = true;
            btnText.textContent = 'Testando...';
            btnLoader.classList.remove('hidden');
            feedback.classList.add('hidden');
            modelSection.classList.add('hidden');

            try {
                const response = await fetch("{{ route('admin.api-keys.test') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': "{{ csrf_token() }}"
                    },
                    body: JSON.stringify({ provider, key })
                });

                const result = await response.json();

                if (result.is_valid) {
                    feedback.className = 'p-4 rounded-md text-sm bg-green-50 text-green-700 block mb-4 border border-green-100';
                    feedback.textContent = '✅ Conexão estabelecida com sucesso!';
                    
                    modelSelect.innerHTML = '';
                    if (result.models && result.models.length > 0) {
                        result.models.forEach(model => {
                            const option = document.createElement('option');
                            option.value = model.id;
                            option.textContent = model.name;
                            modelSelect.appendChild(option);
                        });
                        modelSection.classList.remove('hidden');
                    } else {
                        const option = document.createElement('option');
                        option.value = '';
                        option.textContent = 'Padrão (Nenhum modelo específico)';
                        modelSelect.appendChild(option);
                    }

                    btnSave.disabled = false;
                    btnSave.classList.remove('opacity-50', 'cursor-not-allowed');
                } else {
                    throw new Error(result.error || 'Erro desconhecido no servidor');
                }
            } catch (error) {
                feedback.className = 'p-4 rounded-md text-sm bg-red-50 text-red-700 block border border-red-100';
                feedback.textContent = '❌ Erro SRE: ' + error.message;
            } finally {
                btnTest.disabled = false;
                btnText.textContent = 'Testar';
                btnLoader.classList.add('hidden');
                feedback.classList.remove('hidden');
            }
        }
    </script>
</x-layouts.admin>
