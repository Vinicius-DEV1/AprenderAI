<x-layouts.admin>
    <div class="mb-6 flex items-center gap-4">
        <a href="{{ route('admin.users.show', $log->user_id) }}" class="p-2 bg-white rounded-lg shadow-sm hover:shadow-md transition-all text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Histórico da Conversa</h1>
            <p class="text-gray-500">
                Monitorando interação de {{ $log->user->name }} • 
                <span class="font-mono text-xs bg-gray-100 px-2 py-0.5 rounded">{{ $log->model }}</span>
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Contexto da Questão -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white rounded-2xl shadow-sm p-6 sticky top-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Contexto</h3>
                
                @if($question)
                    <div class="prose prose-sm mb-6">
                        <div class="text-xs font-bold text-gray-400 uppercase mb-1">Enunciado</div>
                        <div class="text-gray-800 bg-gray-50 p-3 rounded-lg border border-gray-100">
                            {!! Str::markdown($question->statement) !!}
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="text-xs font-bold text-gray-400 uppercase">Alternativas</div>
                        @foreach($question->alternatives as $key => $text)
                            <div class="flex gap-2 text-sm {{ $key === $question->correct_answer ? 'text-green-700 font-medium' : 'text-gray-600' }}">
                                <span class="w-6 h-6 flex items-center justify-center rounded-full border {{ $key === $question->correct_answer ? 'border-green-500 bg-green-50' : 'border-gray-200' }} text-xs">
                                    {{ $key }}
                                </span>
                                <div>{{ $text }}</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-gray-500 italic text-sm">
                        Questão não encontrada ou log sem contexto vinculado.
                    </div>
                    <div class="mt-4">
                        <div class="text-xs font-bold text-gray-400 uppercase mb-1">Prompt Original</div>
                        <div class="text-xs font-mono bg-gray-900 text-gray-100 p-3 rounded overflow-x-auto">
                            {{ $log->prompt_text }}
                        </div>
                    </div>
                @endif

                <div class="mt-8 pt-4 border-t border-gray-100">
                    <div class="text-xs font-bold text-gray-400 uppercase mb-2">Metadados da Requisição</div>
                    <ul class="text-sm space-y-2">
                        <li class="flex justify-between">
                            <span class="text-gray-500">Data</span>
                            <span class="font-medium">{{ $log->created_at->format('d/m/Y H:i:s') }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="text-gray-500">Custo</span>
                            <span class="font-medium text-green-600">R$ {{ number_format($log->estimated_cost, 4, ',', '.') }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="text-gray-500">Tokens Total</span>
                            <span class="font-medium">{{ $log->tokens_used_total }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="text-gray-500">Tempo Exec.</span>
                            <span class="font-medium">{{ $log->execution_time }}s</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Chat History -->
        <div class="lg:col-span-2">
            <div class="bg-gray-50 rounded-2xl shadow-inner p-6 min-h-[600px] flex flex-col gap-4">
                @forelse($messages as $msg)
                    <div class="flex {{ $msg->role === 'user' ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[80%] rounded-2xl p-4 {{ $msg->role === 'user' ? 'bg-blue-600 text-white rounded-tr-none' : 'bg-white text-gray-800 shadow-sm rounded-tl-none border border-gray-100' }}">
                            @if($msg->role !== 'user')
                                <div class="text-xs font-bold text-gray-400 mb-1 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                                    AprovadoAI
                                </div>
                            @endif
                            
                            <div class="prose prose-sm {{ $msg->role === 'user' ? 'prose-invert' : '' }} max-w-none">
                                {!! Str::markdown($msg->message) !!}
                            </div>
                            
                            <div class="text-[10px] mt-2 opacity-60 text-right">
                                {{ $msg->created_at->format('H:i') }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center h-full text-gray-400">
                        <svg class="w-16 h-16 mb-4 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                        <p>Nenhuma mensagem encontrada neste contexto.</p>
                        @if($log->response_text)
                            <p class="mt-4 text-xs font-mono bg-gray-200 p-2 rounded">Resposta Raw do Log disponível (veja metadados)</p>
                        @endif
                    </div>
                @endforelse

                @if(!empty($log->response_text) && $messages->isEmpty())
                     <!-- Fallback to showing just the logged request/response if no chat history found -->
                    <div class="flex justify-end">
                        <div class="max-w-[80%] rounded-2xl p-4 bg-blue-600 text-white rounded-tr-none">
                             <div class="text-xs font-bold opacity-50 mb-1">Prompt Original</div>
                             {{ Str::limit($log->prompt_text, 200) }}
                        </div>
                    </div>
                    <div class="flex justify-start">
                         <div class="max-w-[80%] rounded-2xl p-4 bg-white text-gray-800 shadow-sm rounded-tl-none border border-gray-100">
                             <div class="text-xs font-bold text-gray-400 mb-1">Resposta do Log</div>
                             <div class="prose prose-sm">
                                 {!! Str::markdown($log->response_text) !!}
                             </div>
                         </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.admin>
