<x-layouts.admin>
    <div class="mb-6 flex items-center gap-4">
        <a href="{{ route('admin.users.index') }}" class="p-2 bg-white rounded-lg shadow-sm hover:shadow-md transition-all text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Detalhes do Usuário</h1>
            <p class="text-gray-500">Gerenciando {{ $user->name }}</p>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-r shadow-sm">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-700">{{ session('success') }}</p>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column: Profile & Actions -->
        <div class="space-y-6">
            <!-- Profile Card -->
            <div class="bg-white rounded-2xl shadow-sm p-6 text-center">
                <div class="relative inline-block">
                    <img src="{{ $user->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($user->name) }}" 
                         alt="{{ $user->name }}" 
                         class="w-24 h-24 rounded-full mx-auto border-4 border-gray-100 shadow-sm">
                    <span class="absolute bottom-1 right-1 w-5 h-5 rounded-full border-2 border-white {{ $user->is_banned ? 'bg-red-500' : 'bg-green-500' }}"></span>
                </div>
                <h2 class="mt-4 text-xl font-bold text-gray-800">{{ $user->name }}</h2>
                <p class="text-gray-500 text-sm">{{ $user->email }}</p>
                <div class="mt-4 flex justify-center gap-2">
                    <span class="px-3 py-1 bg-blue-50 text-blue-700 rounded-full text-xs font-semibold">
                        {{ $user->plan->name ?? 'Free' }}
                    </span>
                    <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-semibold">
                        ID: {{ $user->id }}
                    </span>
                 </div>
            </div>

            <!-- Edit Profile Form -->
            <div class="bg-white rounded-2xl shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Editar Perfil</h3>
                <form action="{{ route('admin.users.update', $user) }}" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nome Completo</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500">
                        @error('name') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500">
                        @error('email') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
                        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500">
                        @error('phone') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1 flex justify-between">
                            <span>Consumo de IA (O que já usou)</span>
                            <span class="text-xs text-gray-500">Limite Atual: {{ $user->max_ai_questions_override ?? $user->plan->max_ai_questions ?? 'N/A' }}</span>
                        </label>
                        <div class="flex items-center gap-2">
                            <input type="number" name="ai_questions_count" value="{{ old('ai_questions_count', $user->ai_questions_count) }}" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500" min="0">
                            <button type="button" onclick="document.querySelector('input[name=ai_questions_count]').value = 0" class="text-xs text-blue-600 hover:underline">Zerar</button>
                        </div>
                        @error('ai_questions_count') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1 flex justify-between">
                            <span>Limite Individual de IA (Override)</span>
                            <span class="text-xs text-gray-500">Plano: {{ $user->plan->max_ai_questions ?? 'N/A' }}</span>
                        </label>
                        <input type="number" name="max_ai_questions_override"
                               value="{{ old('max_ai_questions_override', $user->max_ai_questions_override) }}"
                               class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500"
                               min="0" placeholder="Vazio = usar limite do plano">
                        @error('max_ai_questions_override') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        <p class="text-xs text-gray-400 mt-1">0 = ilimitado. Vazio = usar padrão do plano.</p>
                    </div>

                    {{-- ============================================== --}}
                    {{-- SIMULATION QUOTA OVERRIDE                     --}}
                    {{-- Same pattern as AI questions override above.  --}}
                    {{-- ============================================== --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1 flex justify-between">
                            <span>Limite Individual de Simulados (Override)</span>
                            <span class="text-xs text-gray-500">
                                Plano: {{ $user->plan->simulations_limit ?? 'N/A' }}
                                &bull;
                                Usado este mês: {{ $user->monthlySimulationUsed() }}
                            </span>
                        </label>
                        <input type="number" name="max_simulations_override"
                               value="{{ old('max_simulations_override', $user->max_simulations_override) }}"
                               class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500"
                               min="0" placeholder="Vazio = usar limite do plano">
                        @error('max_simulations_override') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        <p class="text-xs text-gray-400 mt-1">0 = ilimitado. Vazio = usar padrão do plano.</p>
                    </div>

                    {{-- ============================================== --}}
                    {{-- ESSAY QUOTA OVERRIDE                          --}}
                    {{-- ============================================== --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1 flex justify-between">
                            <span>Limite Individual de Redações (Override)</span>
                            <span class="text-xs text-gray-500">
                                Plano: {{ $user->plan->essays_limit ?? 'N/A' }}
                                &bull;
                                Usado este mês: {{ $user->monthlyEssayUsed() }}
                            </span>
                        </label>
                        <input type="number" name="max_essays_override"
                               value="{{ old('max_essays_override', $user->max_essays_override) }}"
                               class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500"
                               min="0" placeholder="Vazio = usar limite do plano">
                        @error('max_essays_override') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        <p class="text-xs text-gray-400 mt-1">0 = ilimitado. Vazio = usar padrão do plano.</p>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 font-medium transition-colors">
                        Salvar Alterações
                    </button>
                </form>
            </div>

            <!-- Security Actions -->
            <div class="bg-white rounded-2xl shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center text-red-600">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                    Segurança
                </h3>
                
                {{-- Ban/Unban Toggle --}}
                <div class="bg-gray-50 p-4 rounded-xl mb-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-medium text-gray-700">Acesso ao Sistema</span>
                        <span class="text-xs font-bold {{ $user->is_banned ? 'text-red-500' : 'text-green-500' }}">
                            {{ $user->is_banned ? 'BANIDO' : 'ATIVO' }}
                        </span>
                    </div>
                    <form action="{{ route('admin.users.toggle-status', $user) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="w-full py-2 px-4 rounded-lg border {{ $user->is_banned ? 'border-green-500 text-green-600 hover:bg-green-50' : 'border-red-500 text-red-600 hover:bg-red-50' }} transition-colors text-sm font-semibold">
                            {{ $user->is_banned ? 'Desbloquear Usuário' : 'Banir Usuário' }}
                        </button>
                    </form>
                </div>

                <hr class="border-gray-100 my-4">

                {{-- Password Reset --}}
                <h4 class="text-sm font-semibold text-gray-600 mb-3">Redefinir Senha</h4>
                <div class="space-y-3">
                    <form action="{{ route('admin.users.reset-password', $user) }}" method="POST">
                        @csrf
                        <input type="hidden" name="send_email" value="1">
                        <button type="submit" class="w-full flex items-center justify-center gap-2 bg-white border border-gray-300 text-gray-700 py-2 rounded-lg hover:bg-gray-50 transition-colors text-sm font-medium">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                            Enviar Email de Reset
                        </button>
                    </form>

                    <form action="{{ route('admin.users.reset-password', $user) }}" method="POST" class="mt-2">
                        @csrf
                        <label class="text-xs text-gray-500 mb-1 block">Ou defina manualmente:</label>
                        <div class="flex gap-2">
                            <input type="password" name="new_password" placeholder="Nova senha" class="flex-1 rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                            <button type="submit" class="bg-gray-800 text-white px-3 rounded-lg hover:bg-gray-900 text-sm font-medium">
                                Salvar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column: Stats & Logs -->
        <div class="lg:col-span-2 space-y-6">
            <!-- AI Metrics Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-blue-500">
                    <div class="text-gray-500 text-[10px] uppercase font-bold mb-1">Simulados</div>
                    <div class="text-2xl font-bold text-gray-800">{{ $stats['simulations'] }}</div>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-purple-500">
                    <div class="text-gray-500 text-[10px] uppercase font-bold mb-1">Redações</div>
                    <div class="text-2xl font-bold text-gray-800">{{ $stats['essays'] }}</div>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-green-500">
                    <div class="text-gray-500 text-[10px] uppercase font-bold mb-1">Investimento Aluno</div>
                    <div class="text-2xl font-bold text-gray-800">
                        R$ {{ number_format($user->subscriptions->where('status', 'active')->sum(fn($s) => $s->plan->price ?? 0), 2, ',', '.') }}
                    </div>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-amber-500">
                    <div class="text-gray-500 text-[10px] uppercase font-bold mb-1">Consumo IA (R$)</div>
                    <div class="text-2xl font-bold text-slate-900">R$ {{ number_format($stats['ai']['total_cost'], 2, ',', '.') }}</div>
                    <div class="text-[10px] text-slate-500 mt-1">Gasto total acumulado</div>
                </div>
            </div>

            <!-- Métricas de IA -->
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden border border-indigo-100">
                <div class="p-6 border-b border-indigo-50 bg-indigo-50/30 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-indigo-900 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                        Métricas de IA
                    </h3>
                    <div class="flex gap-4">
                        <div class="text-center">
                            <p class="text-[10px] text-gray-400 uppercase font-bold">Requisições</p>
                            <p class="text-sm font-bold text-indigo-600">{{ $stats['ai']['request_count'] }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-[10px] text-gray-400 uppercase font-bold">Sucesso</p>
                            <p class="text-sm font-bold text-green-600">{{ number_format($stats['ai']['success_rate'], 1) }}%</p>
                        </div>
                        <div class="text-center">
                            <p class="text-[10px] text-gray-400 uppercase font-bold">Pico Uso</p>
                            <p class="text-sm font-bold text-orange-600">{{ $stats['ai']['peak_hour'] }}</p>
                        </div>
                    </div>
                </div>
                
                <div class="p-6 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-400 uppercase tracking-wider">
                                <th class="pb-3 font-bold">Data</th>
                                <th class="pb-3 font-bold">Modelo</th>
                                <th class="pb-3 font-bold">Tokens (I/O)</th>
                                <th class="pb-3 font-bold text-right">Custo (R$)</th>
                                <th class="pb-3 font-bold text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @forelse($promptHistory as $log)
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="py-3 text-gray-500 whitespace-nowrap">
                                        {{ $log->created_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="py-3">
                                        <div class="flex flex-col">
                                            <span class="font-medium text-gray-800">{{ $log->provider }}</span>
                                            <span class="text-[10px] text-gray-400">{{ $log->model }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3 text-gray-500 font-mono text-xs">
                                        {{ $log->tokens_used_input }} / {{ $log->tokens_used_output }}
                                    </td>
                                    <td class="py-3 text-right font-bold text-gray-800">
                                        R$ {{ number_format($log->estimated_cost, 4, ',', '.') }}
                                    </td>
                                    <td class="py-3 text-center">
                                        @if(!empty($log->prompt_text) || !empty($log->response_text))
                                            <button onclick="document.getElementById('dialog-transcript-{{ $log->id }}').showModal()" class="text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 px-3 py-1 rounded-full text-xs font-semibold transition-colors" title="Ver Transcrição do Chat">
                                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" /></svg>
                                                Ver Chat
                                            </button>

                                            <!-- HTML5 Dialog Modal for Transcript -->
                                            <dialog id="dialog-transcript-{{ $log->id }}" class="p-0 rounded-2xl shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm w-full max-w-4xl mx-auto top-10 bottom-10 open:animate-fade-in relative">
                                                <div class="flex flex-col h-[80vh] bg-gray-50">
                                                    <!-- Header -->
                                                    <div class="px-6 py-4 border-b bg-white flex justify-between items-center sticky top-0 z-10 shadow-sm">
                                                        <div>
                                                            <h3 class="text-lg font-bold text-gray-800">Cópia do Chat (Transcript) <span class="text-xs ml-2 text-gray-400 font-mono">ID: {{ $log->id }}</span></h3>
                                                            <p class="text-sm text-gray-500">{{ $log->provider }} - {{ $log->model }} | {{ $log->created_at->format('d/m/Y H:i:s') }}</p>
                                                        </div>
                                                        <button onclick="document.getElementById('dialog-transcript-{{ $log->id }}').close()" class="text-gray-400 hover:bg-gray-100 hover:text-gray-800 rounded-full p-2 transition-colors">
                                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                        </button>
                                                    </div>

                                                    <!-- Body (Chat Flow) -->
                                                    <div class="p-6 overflow-y-auto flex-1 space-y-6">
                                                        <!-- User Prompt -->
                                                        @if(!empty($log->prompt_text))
                                                        <div class="flex justify-end">
                                                            <div class="bg-blue-600 text-white rounded-2xl rounded-tr-none p-4 max-w-3xl shadow-sm whitespace-pre-wrap font-sans text-sm">
                                                                <div class="text-[10px] text-blue-200 uppercase font-bold mb-2 flex items-center justify-end">
                                                                    Prompt de Comando <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                                                </div>
                                                                {{ $log->prompt_text }}
                                                            </div>
                                                        </div>
                                                        @endif

                                                        <!-- AI Response -->
                                                        @if(!empty($log->response_text))
                                                        <div class="flex justify-start">
                                                            <div class="bg-white border border-gray-200 text-gray-800 rounded-2xl rounded-tl-none p-5 max-w-3xl shadow-sm font-sans text-sm overflow-x-auto">
                                                                <div class="text-[10px] text-indigo-500 uppercase font-bold mb-2 flex items-center">
                                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                                                    Resposta da IA (Payload ou Completion)
                                                                </div>
                                                                <pre class="whitespace-pre-wrap font-mono text-xs text-gray-700 bg-gray-50 p-4 rounded border border-gray-100">{{ is_string($log->response_text) ? $log->response_text : json_encode(json_decode($log->response_text), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                            </div>
                                                        </div>
                                                        @endif
                                                    </div>

                                                    <!-- Footer Stats -->
                                                    <div class="px-6 py-3 bg-white border-t flex justify-between items-center text-xs text-gray-500 shrink-0">
                                                        <div class="flex gap-4">
                                                            <span>Tokens Entrada: <strong class="text-gray-800">{{ $log->tokens_used_input }}</strong></span>
                                                            <span>Tokens Saída: <strong class="text-gray-800">{{ $log->tokens_used_output }}</strong></span>
                                                            <span>Custo Estimado: <strong class="text-red-600">US$ {{ number_format($log->estimated_cost, 5, ',', '.') }}</strong></span>
                                                        </div>
                                                        <span>Latência: <strong>{{ number_format($log->execution_time ?? 0, 1) }}s</strong></span>
                                                    </div>
                                                </div>
                                            </dialog>
                                        @else
                                            <span class="text-gray-300" title="Corpo Vazio">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-gray-500 italic">Nenhum uso de IA registrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="mt-4">
                        {{ $promptHistory->links() }}
                    </div>
                </div>
            </div>


            <!-- Subscription History -->
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="text-lg font-bold text-gray-800">Histórico de Assinaturas</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left font-medium text-gray-500">Plano</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-500">Status</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-500">Data Início</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-500">Fim/Renovação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($user->subscriptions as $sub)
                                <tr>
                                    <td class="px-6 py-3 font-medium text-gray-800">{{ $sub->plan->name ?? 'Desconhecido' }}</td>
                                    <td class="px-6 py-3">
                                        @if($sub->status === 'active')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Ativa</span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">{{ $sub->status }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-gray-600">{{ $sub->created_at->format('d/m/Y') }}</td>
                                    <td class="px-6 py-3 text-gray-600">{{ $sub->current_period_end ? $sub->current_period_end->format('d/m/Y') : '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-gray-500">Nenhuma assinatura registrada.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Audit Logs -->
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                        Log de Auditoria
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left font-medium text-gray-500">Ação</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-500">Descrição</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-500">IP</th>
                                <th class="px-6 py-3 text-right font-medium text-gray-500">Data</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($user->logs as $log)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-3 font-medium text-gray-800">{{ $log->action }}</td>
                                    <td class="px-6 py-3 text-gray-600 truncate max-w-xs" title="{{ $log->description }}">{{ $log->description }}</td>
                                    <td class="px-6 py-3 text-gray-500 font-mono text-xs">{{ $log->ip_address }}</td>
                                    <td class="px-6 py-3 text-right text-gray-500">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-gray-500">Nenhum registro de log encontrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
