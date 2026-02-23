<x-layouts.admin>
    @push('head')
    <style>
        [x-cloak] { display: none !important; }
        .custom-scrollbar::-webkit-scrollbar {
            width: 8px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1; /* slate-300 */
            border-radius: 10px;
            border: 2px solid transparent;
            background-clip: content-box;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8; /* slate-400 */
            border: 2px solid transparent;
            background-clip: content-box;
        }
    </style>
    @endpush

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    
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

    <div class="max-w-7xl mx-auto space-y-6" x-data="{ 
        showHelp: false, 
        helpTitle: '', 
        helpBody: '',
        ruleText: '🚨 Lembre-se: Apenas uma chave pode estar ativa para esta função por vez para garantir controle total de custos.',
        
        // Log Modal State
        showLogModal: false,
        activeLog: { user: '', provider: '', model: '', prompt: '', response: '', input_tokens: 0, output_tokens: 0, execution_time: 0, estimated_cost: 0 },

        openHelp(title, body) {
            this.helpTitle = title;
            this.helpBody = body;
            this.showHelp = true;
        },
        openLog(log) {
            this.activeLog = log;
            this.showLogModal = true;
        }
    }" @keydown.escape.window="showHelp = false; showLogModal = false">
        
        <!-- 1. COFRE DE CHAVES (KEY VAULT) -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100" 
             x-data="{ collapsed: localStorage.getItem('sre_vault_collapsed') === 'true' }">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center cursor-pointer hover:bg-gray-50 transition-colors" @click="collapsed = !collapsed; localStorage.setItem('sre_vault_collapsed', collapsed)">
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <span class="p-2 bg-indigo-50 text-indigo-600 rounded-lg">🔐</span>
                    Cofre de Chaves (Key Vault)
                </h3>
                <svg class="w-5 h-5 text-gray-400 transition-transform duration-300" :class="collapsed ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
            <div x-show="!collapsed" x-collapse x-transition class="p-6 pt-4">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Form de Cadastro no Cofre -->
                    <div class="lg:col-span-1 border-r border-gray-100 pr-0 lg:pr-6">
                        <h4 class="text-sm font-bold text-gray-700 mb-4 uppercase tracking-wider">Novo Registro</h4>
                        <form action="{{ route('admin.api-keys.vault.store') }}" method="POST" class="space-y-4" id="form-vault">
                            @csrf
                            <div>
                                <x-input-label for="vault_nickname" value="Apelido da Chave (Ex: Google Prod)" />
                                <x-text-input name="nickname" id="vault_nickname" class="block w-full mt-1" required placeholder="Nome amigável" />
                            </div>
                            <div>
                                <x-input-label for="vault_provider" value="Provedor" />
                                <select name="provider" id="vault_provider" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full">
                                    <option value="gemini">Google Gemini</option>
                                    <option value="openai">OpenAI</option>
                                    <option value="grok">Grok (xAI)</option>
                                </select>
                            </div>
                            <div>
                                <x-input-label for="vault_key" value="Chave Secreta" />
                                <x-text-input name="key" id="vault_key" type="password" class="block w-full mt-1" required placeholder="sk-..." />
                            </div>
                            <div class="pt-2">
                                <x-primary-button class="w-full justify-center">Guardar no Cofre</x-primary-button>
                            </div>
                        </form>
                    </div>

                    <!-- Listagem do Cofre -->
                    <div class="lg:col-span-2">
                        <h4 class="text-sm font-bold text-gray-700 mb-4 uppercase tracking-wider">Chaves Armazenadas</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @forelse($vaultKeys as $vk)
                                <div class="p-4 rounded-xl border border-gray-100 bg-gray-50/50 flex justify-between items-center group hover:border-indigo-200 transition-colors shadow-sm bg-white">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-gray-800">{{ $vk->nickname }}</span>
                                            <span class="text-[10px] uppercase px-1.5 py-0.5 rounded {{ $vk->provider === 'openai' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700' }}">
                                                {{ $vk->provider }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-400 mt-1">Status: {{ $vk->is_valid ? '✅ Validada' : '❓ Não Testada' }}</p>
                                    </div>
                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                                        <span class="text-xs text-gray-300 italic">No Cofre</span>
                                    </div>
                                </div>
                            @empty
                                <div class="col-span-2 py-8 text-center text-gray-400 italic">O cofre está vazio.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. CONFIGURAÇÃO POR FUNCIONALIDADE (ROUTING) -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100" 
             x-data="routingData()">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center cursor-pointer hover:bg-gray-50 transition-colors" @click="toggleCollapse()">
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <span class="p-2 bg-purple-50 text-purple-600 rounded-lg">🎯</span>
                    Configuração por Funcionalidade (Roteamento)
                </h3>
                <svg class="w-5 h-5 text-gray-400 transition-transform duration-300" :class="collapsed ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
            
            <div x-show="!collapsed" x-collapse x-transition class="p-6 pt-4">
                <form action="{{ route('admin.api-keys.store') }}" method="POST" class="space-y-6">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Seleção de Origem -->
                        <div>
                            <x-input-label for="vault_id" value="1. Selecione a Chave do Cofre" />
                            <select name="vault_id" id="vault_id" x-model="selectedVaultId" 
                                    @change="discoverModels()"
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full bg-gray-50">
                                <option value="">-- Escolha um Apelido --</option>
                                @foreach($vaultKeys as $vk)
                                    <option value="{{ $vk->id }}">{{ $vk->nickname }} ({{ strtoupper($vk->provider) }})</option>
                                @endforeach
                            </select>
                            <p class="text-[10px] text-gray-400 mt-1 italic">O sistema disparará um ping de descoberta automático ao selecionar.</p>
                        </div>

                        <!-- Modelo Selecionado (Visual only until modal) -->
                        <div>
                            <x-input-label value="2. Modelo Selecionado" />
                            <div class="mt-1 flex gap-2">
                                <div class="flex-1 p-2 bg-gray-100 border border-gray-200 rounded-md text-sm font-mono text-gray-600 flex items-center gap-2 h-[42px]">
                                    <template x-if="selectedModel">
                                        <span class="flex items-center gap-2">
                                            <span class="text-green-500">✨</span>
                                            <span x-text="selectedModel"></span>
                                        </span>
                                    </template>
                                    <template x-if="!selectedModel">
                                        <span class="text-gray-400 italic">
                                            <span x-show="!loadingModels">Aguardando seleção...</span>
                                            <span x-show="loadingModels">Aguardando descoberta...</span>
                                        </span>
                                    </template>
                                    <input type="hidden" name="preferred_model" :value="selectedModel">
                                </div>
                                <button type="button" @click="discoverModels()" :disabled="!selectedVaultId || loadingModels"
                                        class="px-4 py-2 bg-white border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none disabled:opacity-50">
                                    <span x-show="!loadingModels">🔍</span>
                                    <span x-show="loadingModels" class="animate-spin inline-block">⌛</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Erro de Descoberta -->
                    <div x-show="discoveryError" class="p-3 bg-red-50 border border-red-100 rounded-lg text-xs text-red-600 flex items-center gap-2">
                        <span>❌</span>
                        <span x-text="discoveryError"></span>
                    </div>

                    <!-- Grid de Capacidades -->
                    <div class="bg-gray-50/80 border border-gray-200 rounded-xl p-6">
                        <div class="flex justify-between items-center mb-4">
                            <x-input-label value="3. Atribuir Funcionalidades" class="!mb-0" />
                            <span class="text-[10px] bg-white border border-gray-200 px-2 py-1 rounded text-gray-400 font-bold uppercase tracking-tighter">Roteamento N:N</span>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @php
                                $helpContent = [
                                    'general' => [
                                        'title' => 'Uso Geral / Fallback',
                                        'body' => 'Chave de segurança padrão para qualquer tarefa sem mapeamento específico.'
                                    ],
                                    'questions' => [
                                        'title' => 'Geração/Correção de Questões',
                                        'body' => 'Motor de IA para criação de questões, melhoria do banco e avaliações de dificuldade.'
                                    ],
                                    'essays' => [
                                        'title' => 'Avaliação de Redações',
                                        'body' => 'Processamento de redações dos alunos, análise de competências e feedbacks estruturados.'
                                    ],
                                    'triage' => [
                                        'title' => 'Triagem e Moderação',
                                        'body' => 'Classificação automática de Disciplinas e Assuntos em importações de lotes.'
                                    ],
                                    'search' => [
                                        'title' => 'Busca Inteligente (Xavier)',
                                        'body' => 'Alimenta o chat tutor Xavier para tirar dúvidas dos alunos.'
                                    ],
                                    'study_plans' => [
                                        'title' => 'Geração de Plano de Estudos',
                                        'body' => 'Criação de calendários de estudo personalizados baseados no desempenho real do aluno em simulados.'
                                    ]
                                ];
                            @endphp
                            @foreach($availableCapabilities as $code => $label)
                                @php $content = $helpContent[$code] ?? ['title' => $label, 'body' => '']; @endphp
                                <label class="relative flex items-center bg-white p-4 rounded-xl border border-gray-100 shadow-sm hover:border-indigo-200 transition-all group cursor-pointer">
                                    <input type="checkbox" name="capabilities[]" value="{{ $code }}" class="w-5 h-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <div class="ml-3">
                                        <span class="text-sm font-bold text-gray-700 block">{{ $label }}</span>
                                        <button type="button" @click="openHelp({{ json_encode($content['title']) }}, {{ json_encode($content['body']) }})" 
                                                class="text-[10px] text-indigo-400 hover:text-indigo-600 underline font-medium mt-0.5">
                                            Como funciona?
                                        </button>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <x-primary-button class="h-12 px-8 shadow-lg shadow-indigo-200" x-bind:disabled="!selectedModel">
                            Ativar Roteamento
                        </x-primary-button>
                    </div>
                </form>

            </div>

            <!-- Model Selection Modal (Premium) -->
            <div x-show="showModelModal" class="fixed inset-0 z-[150] flex items-center justify-center p-4" x-cloak
                 @keydown.escape.window="showModelModal = false"
                 x-effect="document.body.style.overflow = showModelModal ? 'hidden' : ''"
                 x-init="$watch('showModelModal', value => { if (value) setTimeout(() => { $refs.modalContainer && $refs.modalContainer.focus() }, 50) })">
                <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[140]" @click="showModelModal = false"></div>
                <div class="relative z-[160] bg-white rounded-2xl shadow-2xl max-w-lg w-full flex flex-col overflow-hidden border border-gray-100 outline-none" 
                     x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                     x-ref="modalContainer" tabindex="-1">
                    <div class="bg-indigo-600 px-6 py-4 flex justify-between items-center text-white shrink-0 sticky top-0 z-10">
                        <h3 class="font-bold flex items-center gap-2">
                            <span>🤖</span> Modelos Disponíveis no Provedor
                        </h3>
                        <button type="button" @click="showModelModal = false" class="hover:text-gray-200 transition-colors">✕</button>
                    </div>
                    <div class="p-6 overflow-y-auto custom-scrollbar flex-1 max-h-[70vh]">
                        <div class="grid grid-cols-1 gap-2">
                            <template x-for="model in models" :key="model.id">
                                <div @click="selectedModel = model.id; showModelModal = false" 
                                     class="p-4 rounded-xl border border-gray-100 hover:bg-gray-100 hover:border-indigo-200 cursor-pointer transition-all flex justify-between items-center group">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-800 group-hover:text-indigo-700 transition-colors" x-text="model.name"></span>
                                        <span class="text-[10px] font-mono text-gray-400 group-hover:text-indigo-400 transition-colors" x-text="model.id"></span>
                                    </div>
                                    <span class="opacity-0 group-hover:opacity-100 text-indigo-500 font-medium">Selecionar →</span>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-4 text-center text-[10px] text-gray-400 italic shrink-0">
                        O acesso aos modelos depende da sua quota na conta do provedor.
                    </div>
                </div>
            </div>

        </div>


        <!-- 3. LISTAGEM E PRIORIDADES (M:N) -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100"
             x-data="priorityGridData()">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center cursor-pointer hover:bg-gray-50 transition-colors" @click="toggleCollapse()">
                <h3 class="text-lg font-medium text-gray-900">Prioridades de Roteamento (Failover M:N)</h3>
                <svg class="w-5 h-5 text-gray-400 transition-transform duration-300" :class="collapsed ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
            
            <div x-show="!collapsed" x-collapse x-transition>
                <div class="p-6 pt-4 bg-gray-50">
                    <p class="text-sm text-gray-600 mb-6">
                        (ℹ) Arraste e solte os provedores para definir a ordem de tentativa. O sistema tentará o modelo <strong>primário (top 1)</strong> primeiro. Se falhar por erro temporário (ex: Quota), passará automaticamente para o próximo.
                    </p>

                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                        @foreach($availableCapabilities as $cap => $label)
                            <div class="bg-white border text-sm border-gray-200 shadow-sm rounded-xl overflow-hidden flex flex-col">
                                <div class="bg-indigo-50/50 border-b border-indigo-100 px-4 py-3 flex justify-between items-center">
                                    <h4 class="font-bold text-indigo-900 flex items-center gap-2">
                                        <span class="text-indigo-400">⚡</span> {{ $label }}
                                    </h4>
                                    <span class="text-[10px] font-mono bg-white px-2 py-0.5 rounded border border-indigo-100 text-indigo-400">{{ $cap }}</span>
                                </div>
                                
                                <div class="p-3 flex-1 bg-gray-50/30">
                                    @php $keys = $capabilitiesGrid[$cap] ?? collect([]); @endphp
                                    
                                    @if($keys->isEmpty())
                                        <div class="text-center py-6 text-gray-400 italic text-xs border-2 border-dashed border-gray-200 rounded-lg">
                                            Nenhum provedor configurado para esta rota.
                                        </div>
                                    @else
                                        <!-- Sortable List -->
                                        <ul class="space-y-2 sortable-list" data-capability="{{ $cap }}">
                                            @foreach($keys as $index => $key)
                                                @php
                                                    $isTop = $index === 0;
                                                    $statusClasses = match($key->status) {
                                                        'online' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                                        'offline' => 'bg-red-50 text-red-700 border-red-200',
                                                        'quota_exceeded' => 'bg-amber-50 text-amber-700 border-amber-200',
                                                        default => 'bg-gray-50 text-gray-700 border-gray-200'
                                                    };
                                                    
                                                    // Pivot data
                                                    $pivotId = $key->pivot->id ?? 'unknown';
                                                @endphp
                                                <li class="flex items-center gap-3 p-3 bg-white border rounded-lg shadow-sm hover:border-indigo-300 transition-colors {{ $statusClasses }}" data-id="{{ $pivotId }}">
                                                    
                                                    <!-- Drag Handle -->
                                                    <div class="cursor-grab hover:text-indigo-600 text-gray-400 p-1 drag-handle active:cursor-grabbing">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                                                    </div>

                                                    <!-- Order Badge -->
                                                    <div class="flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center font-bold text-[10px] {{ $isTop ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-600' }}">
                                                        {{ $index + 1 }}
                                                    </div>

                                                    <!-- Model Info -->
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center gap-2 mb-0.5">
                                                            <span class="font-bold text-gray-900 truncate">{{ $key->effective_provider }}</span>
                                                            <span class="text-[9px] uppercase px-1.5 py-0.5 rounded-full bg-white border border-gray-200 font-bold whitespace-nowrap">
                                                                {{ $key->vault ? $key->vault->nickname : 'Direto' }}
                                                            </span>
                                                        </div>
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-xs font-mono text-gray-500 truncate">{{ $key->preferred_model ?? 'Auto' }}</span>
                                                            @if($key->status === 'online')
                                                                <span class="text-[9px] text-emerald-600 font-bold">● Online</span>
                                                            @elseif($key->status === 'quota_exceeded')
                                                                <span class="text-[9px] text-amber-600 font-bold">● Quota Req</span>
                                                            @else
                                                                <span class="text-[9px] text-red-600 font-bold">● Offline</span>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <!-- Actions -->
                                                    <div class="flex flex-col items-end gap-2 shrink-0">
                                                        <div class="flex border border-gray-200 rounded divide-x divide-gray-200 bg-white">
                                                            <form action="{{ route('admin.api-keys.retest', $key) }}" method="POST" class="inline m-0 p-0">
                                                                @csrf
                                                                <button type="submit" class="px-2 py-1 hover:bg-gray-50 text-amber-600 transition-colors" title="Retestar Conexão">⚡</button>
                                                            </form>
                                                            <form action="{{ route('admin.api-keys.destroy', $key) }}" method="POST" class="inline m-0 p-0" onsubmit="return confirm('Remover este vínculo do roteamento?');">
                                                                @csrf @method('DELETE')
                                                                <button type="submit" class="px-2 py-1 hover:bg-red-50 text-red-600 transition-colors" title="Remover da Pilha">✕</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </li>
                                                
                                                <!-- Error details below the card if errored -->
                                                @if($key->status !== 'online' && $key->last_error_message)
                                                    <li class="px-4 py-2 mt-[-0.5rem] bg-white border border-t-0 rounded-b-lg border-gray-200 text-[10px] text-red-600 font-mono italic flex items-start gap-2 max-w-full overflow-hidden">
                                                        <span class="mt-0.5">↳</span>
                                                        <span class="truncate" title="{{ $key->last_error_message }}">{{ Str::limit($key->last_error_message, 80) }}</span>
                                                    </li>
                                                @endif

                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Histórico de Uso (AI Requests) -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100" 
             x-data="{ collapsed: localStorage.getItem('sre_history_collapsed') === 'true' }">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center cursor-pointer hover:bg-gray-50 transition-colors" @click="collapsed = !collapsed; localStorage.setItem('sre_history_collapsed', collapsed)">
                <div>
                    <h3 class="text-lg font-medium text-gray-900">Histórico de Uso (Últimas 20 Transações)</h3>
                    <p class="text-xs text-gray-500">Log detalhado de prompts e respostas enviadas para a IA.</p>
                </div>
                <svg class="w-5 h-5 text-gray-400 transition-transform duration-300" :class="collapsed ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
            <div x-show="!collapsed" x-collapse x-transition>
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
                                        @click="openLog({
                                            user: {{ json_encode($log->user ? $log->user->name : 'Sistema/Job') }},
                                            provider: {{ json_encode($log->provider) }},
                                            model: {{ json_encode($log->model) }},
                                            prompt: {{ json_encode($log->prompt_text) }},
                                            response: {{ json_encode($log->response_text) }},
                                            input_tokens: {{ $log->tokens_used_input }},
                                            output_tokens: {{ $log->tokens_used_output }},
                                            execution_time: {{ round($log->execution_time, 3) }},
                                            estimated_cost: {{ round($log->estimated_cost, 4) }}
                                        })"
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
        </div></div>

        <!-- Ranking de Consumo (Top 20 Usuários) -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100"
             x-data="{ collapsed: localStorage.getItem('sre_ranking_collapsed') === 'true' }">
            <div class="p-6 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center cursor-pointer hover:bg-gray-100/50 transition-colors" @click="collapsed = !collapsed; localStorage.setItem('sre_ranking_collapsed', collapsed)">
                <div>
                    <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                        <span>🏆</span>
                        Top 20 Usuários - Consumo de IA
                    </h3>
                    <p class="text-xs text-gray-500">Ranking baseado no volume total de tokens consumidos.</p>
                </div>
                <svg class="w-5 h-5 text-gray-400 transition-transform duration-300" :class="collapsed ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
            <div x-show="!collapsed" x-collapse x-transition>
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
        </div></div>

        <!-- Logs de Atividade -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100"
             x-data="{ collapsed: localStorage.getItem('sre_logs_collapsed') === 'true' }">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center cursor-pointer hover:bg-gray-50 transition-colors" @click="collapsed = !collapsed; localStorage.setItem('sre_logs_collapsed', collapsed)">
                <h3 class="text-lg font-medium text-gray-900">Logs de Eventos da API (Últimos 20)</h3>
                <svg class="w-5 h-5 text-gray-400 transition-transform duration-300" :class="collapsed ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
            <div x-show="!collapsed" x-collapse x-transition>
                <div class="p-4 bg-gray-50/50 border-b border-gray-100 flex justify-end">
                    <form action="{{ route('admin.api-keys.clear-logs') }}" method="POST" onsubmit="return confirm('Limpar histórico?')">
                        @csrf
                        <button type="submit" class="text-sm text-gray-500 hover:text-red-600 transition-colors">Limpar Histórico</button>
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

        <!-- Modais Globais (Help & Logs) -->
        
        <!-- Modal de Ajuda Técnica -->
        <template x-if="showHelp">
            <div class="fixed inset-0 z-[120] flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm shadow-2xl" @click="showHelp = false" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"></div>
                
                <div class="relative bg-white rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.3)] max-w-lg w-full overflow-hidden border border-indigo-100" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
                    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4 flex justify-between items-center">
                        <h3 class="text-lg font-bold text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span x-text="helpTitle"></span>
                        </h3>
                        <button type="button" @click="showHelp = false" class="text-white hover:text-indigo-200 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    <div class="p-8">
                        <div class="text-slate-600 leading-relaxed text-sm mb-6" x-html="helpBody"></div>
                        <div class="bg-amber-50 border-l-4 border-amber-400 p-4 rounded-r-lg">
                            <p class="text-[11px] text-amber-700 font-bold" x-text="ruleText"></p>
                        </div>
                    </div>
                    <div class="bg-slate-50 px-6 py-4 flex justify-end">
                        <button type="button" @click="showHelp = false" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 rounded-lg hover:bg-slate-100 transition-colors text-sm font-semibold">
                            Entendi, fechar
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Modal de Detalhes de Log -->
        <template x-if="showLogModal">
            <div class="fixed inset-0 z-[120] flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm shadow-2xl" @click="showLogModal = false" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"></div>
                
                <div class="relative bg-white rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.3)] max-w-4xl w-full max-h-[90vh] flex flex-col overflow-hidden border border-gray-200" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-bold text-gray-800">Detalhes da Transação IA</h3>
                        <button @click="showLogModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    <div class="p-6 space-y-4 overflow-y-auto font-sans flex-1">
                        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 text-[10px] uppercase tracking-wider font-bold text-gray-400">
                            <div><p>Usuário</p><p class="text-gray-800 text-xs" x-text="activeLog.user"></p></div>
                            <div><p>Provedor / Modelo</p><p class="text-gray-800 text-xs" x-text="activeLog.provider + ' / ' + activeLog.model"></p></div>
                            <div><p>Tokens (In / Out)</p><p class="text-gray-800 text-xs" x-text="activeLog.input_tokens + ' / ' + activeLog.output_tokens"></p></div>
                            <div><p>Tempo</p><p class="text-gray-800 text-xs" x-text="activeLog.execution_time + 's'"></p></div>
                            <div><p>Custo Estimado</p><p class="text-indigo-600 text-xs font-bold" x-text="'R$ ' + activeLog.estimated_cost.toFixed(4)"></p></div>
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
        </template>


        <!-- (Modal movido para dentro do scope routingData) -->

    </div>

    <!-- Scripts da Página -->
    <script>
        function routingData() {
            return {
                collapsed: localStorage.getItem('sre_routing_collapsed') === 'true',
                loadingModels: false,
                models: [],
                showModelModal: false,
                selectedVaultId: '',
                selectedModel: '',
                discoveryError: '',

                toggleCollapse() {
                    this.collapsed = !this.collapsed;
                    localStorage.setItem('sre_routing_collapsed', this.collapsed);
                },

                discoverModels() {
                    if (!this.selectedVaultId) return;
                    this.loadingModels = true;
                    this.discoveryError = '';
                    this.models = [];
                    
                    fetch("{{ route('admin.api-keys.discover') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                        },
                        body: JSON.stringify({ vault_id: this.selectedVaultId })
                    })
                    .then(async res => {
                        if (!res.ok) {
                            let msg = 'Erro no servidor (' + res.status + ')';
                            try {
                                const errData = await res.json();
                                msg = errData.error || msg;
                            } catch(e) {}
                            throw new Error(msg);
                        }
                        return res.json();
                    })
                    .then(data => {
                        console.log('Discovery Response:', data); // Debug: respose payload
                        
                        if (data.is_valid && data.models) {
                            if (data.models.length === 0) {
                                this.discoveryError = 'Nenhum modelo disponível para esta chave.';
                                alert("Falha: " + this.discoveryError);
                                this.selectedModel = ''; // prevent 'Aguardando seleção...' state
                            } else {
                                this.models = data.models;
                                this.showModelModal = true;
                                // Dispatch event to focus modal or just log
                                console.log('Opening modal with models:', this.models);
                            }
                        } else {
                            this.discoveryError = data.error || 'Falha na descoberta de modelos.';
                            alert("Falha: " + this.discoveryError);
                            this.selectedModel = ''; // reset state
                        }
                    })
                    .catch(err => {
                        console.error('Fetch error:', err);
                        this.discoveryError = err.message || 'Erro de rede ao conectar com o servidor.';
                        alert("Falha na conexão: " + this.discoveryError);
                        this.selectedModel = ''; // reset state
                    })
                    .finally(() => {
                        this.loadingModels = false;
                    });
            }
        };
    }
        function priorityGridData() {
            return {
                collapsed: localStorage.getItem('sre_active_keys_collapsed') === 'true',
                
                toggleCollapse() {
                    this.collapsed = !this.collapsed;
                    localStorage.setItem('sre_active_keys_collapsed', this.collapsed);
                },

                init() {
                    const self = this;
                    // Initialize Sortable for each capability list
                    document.querySelectorAll('.sortable-list').forEach((el) => {
                        new Sortable(el, {
                            animation: 150,
                            handle: '.drag-handle',
                            ghostClass: 'bg-indigo-50',
                            onEnd: function (evt) {
                                self.savePriorityOrder(evt.to);
                            }
                        });
                    });
                },

                savePriorityOrder(listElement) {
                    const capability = listElement.dataset.capability;
                    const items = Array.from(listElement.querySelectorAll('li[data-id]'));
                    const orderedIds = items.map(li => li.dataset.id);

                    if (orderedIds.length === 0) return;

                    // Optimistic UI update: re-number the badges
                    items.forEach((li, index) => {
                        const badge = li.querySelector('.rounded-full');
                        if (badge) {
                            badge.textContent = index + 1;
                            if (index === 0) {
                                badge.className = 'flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center font-bold text-[10px] bg-indigo-600 text-white';
                            } else {
                                badge.className = 'flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center font-bold text-[10px] bg-gray-200 text-gray-600';
                            }
                        }
                    });

                    fetch('{{ route("admin.api-keys.update-priority") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            capability: capability,
                            ordered_ids: orderedIds
                        })
                    })
                    .then(response => {
                        if (!response.ok) throw new Error('Falha ao salvar a nova ordem');
                    })
                    .catch(error => {
                        console.error('Error updating priority:', error);
                        alert('Erro ao salvar a ordem de prioridade. A página será recarregada.');
                        window.location.reload();
                    });
                }
            }
        }
    </script>
</x-layouts.admin>
