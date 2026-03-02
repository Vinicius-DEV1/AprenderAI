import { useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import api from '../../api/axios';
import ReactMarkdown from 'react-markdown';

export default function SystemLogs() {
    const { logId } = useParams();

    const { data, isLoading } = useQuery({
        queryKey: ['admin-chat-log', logId],
        queryFn: async () => {
            const res = await api.get(`/api/v1/admin/chat_logs/${logId}`);
            return res.data;
        },
        enabled: !!logId
    });

    if (isLoading) return <div className="p-8">Carregando histórico do chat...</div>;

    // Safety checks
    if (!data || !data.log) return <div className="p-8 text-red-500">Log não encontrado ou removido.</div>;

    const { log, question, messages, siteName } = data;
    const alternatives = question?.alternatives ? Object.entries(question.alternatives) : [];

    return (
        <div className="py-6 px-4 md:px-6 w-full">
            <div className="mb-6 flex items-center gap-4">
                <Link to={`/admin/users/${log.user_id}`} className="p-2 bg-white rounded-lg shadow-sm hover:shadow-md transition-all text-gray-600">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                </Link>
                <div>
                    <h1 className="text-2xl font-bold text-gray-800">Histórico da Conversa</h1>
                    <p className="text-gray-500">
                        Monitorando interação de {log.user?.name || 'Usuário Desconhecido'} •
                        <span className="font-mono text-xs bg-gray-100 px-2 py-0.5 rounded ml-2">{log.model || 'N/A'}</span>
                    </p>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {/* Contexto da Questão */}
                <div className="lg:col-span-1 space-y-6">
                    <div className="bg-white rounded-2xl shadow-sm p-6 sticky top-6">
                        <h3 className="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Contexto</h3>

                        {question ? (
                            <>
                                <div className="prose prose-sm mb-6 max-w-none">
                                    <div className="text-xs font-bold text-gray-400 uppercase mb-1">Enunciado</div>
                                    <div className="text-gray-800 bg-gray-50 p-3 rounded-lg border border-gray-100">
                                        <ReactMarkdown>{question.statement || ''}</ReactMarkdown>
                                    </div>
                                </div>

                                <div className="space-y-3">
                                    <div className="text-xs font-bold text-gray-400 uppercase">Alternativas</div>
                                    {alternatives.map(([key, text]) => {
                                        const isCorrect = key === question.correct_answer;
                                        return (
                                            <div key={key} className={`flex gap-2 text-sm ${isCorrect ? 'text-green-700 font-medium' : 'text-gray-600'}`}>
                                                <span className={`w-6 h-6 flex items-center justify-center rounded-full border ${isCorrect ? 'border-green-500 bg-green-50' : 'border-gray-200'} text-xs shrink-0`}>
                                                    {key}
                                                </span>
                                                <div>{String(text)}</div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </>
                        ) : (
                            <>
                                <div className="text-gray-500 italic text-sm">
                                    Questão não encontrada ou log sem contexto vinculado.
                                </div>
                                <div className="mt-4">
                                    <div className="text-xs font-bold text-gray-400 uppercase mb-1">Prompt Original</div>
                                    <div className="text-xs font-mono bg-gray-900 text-gray-100 p-3 rounded overflow-x-auto whitespace-pre-wrap">
                                        {log.prompt_text || 'Sem prompt'}
                                    </div>
                                </div>
                            </>
                        )}

                        <div className="mt-8 pt-4 border-t border-gray-100">
                            <div className="text-xs font-bold text-gray-400 uppercase mb-2">Metadados da Requisição</div>
                            <ul className="text-sm space-y-2">
                                <li className="flex justify-between">
                                    <span className="text-gray-500">Data</span>
                                    <span className="font-medium">{log.created_at ? new Date(log.created_at).toLocaleString('pt-BR') : 'N/A'}</span>
                                </li>
                                <li className="flex justify-between">
                                    <span className="text-gray-500">Custo</span>
                                    <span className="font-medium text-green-600">R$ {Number(log.estimated_cost || 0).toLocaleString('pt-BR', { minimumFractionDigits: 4 })}</span>
                                </li>
                                <li className="flex justify-between">
                                    <span className="text-gray-500">Tokens Total</span>
                                    <span className="font-medium">{log.tokens_used_total || 0}</span>
                                </li>
                                <li className="flex justify-between">
                                    <span className="text-gray-500">Tempo Exec.</span>
                                    <span className="font-medium">{log.execution_time || 0}s</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                {/* Chat History */}
                <div className="lg:col-span-2">
                    <div className="bg-gray-50 rounded-2xl shadow-inner p-6 min-h-[600px] flex flex-col gap-4">
                        {messages && messages.length > 0 ? (
                            messages.map((msg: any, idx: number) => {
                                const isUser = msg.role === 'user';
                                return (
                                    <div key={idx} className={`flex ${isUser ? 'justify-end' : 'justify-start'}`}>
                                        <div className={`max-w-[80%] rounded-2xl p-4 ${isUser ? 'bg-blue-600 text-white rounded-tr-none' : 'bg-white text-gray-800 shadow-sm rounded-tl-none border border-gray-100'}`}>
                                            {!isUser && (
                                                <div className="text-xs font-bold text-gray-400 mb-1 flex items-center gap-1">
                                                    <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                                                    {siteName || 'Assistente IA'}
                                                </div>
                                            )}

                                            <div className={`prose prose-sm max-w-none ${isUser ? 'prose-invert text-white' : ''}`}>
                                                <ReactMarkdown>{msg.message || ''}</ReactMarkdown>
                                            </div>

                                            <div className="text-[10px] mt-2 opacity-60 text-right">
                                                {msg.created_at ? new Date(msg.created_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : ''}
                                            </div>
                                        </div>
                                    </div>
                                );
                            })
                        ) : (
                            <>
                                {(log.response_text || log.prompt_text) ? (
                                    <>
                                        {/* Fallback: Exibir Raw Log se não houver mensagens estruturadas */}
                                        <div className="flex justify-end mb-6">
                                            <div className="max-w-[90%] rounded-2xl p-4 bg-blue-600 text-white rounded-tr-none shadow-sm">
                                                <div className="text-[10px] uppercase font-bold opacity-70 mb-2 border-b border-blue-400 pb-1">Prompt Enviado</div>
                                                <div className="prose prose-sm prose-invert max-w-none text-xs font-mono whitespace-pre-wrap">
                                                    {log.prompt_text || 'Sem Dados'}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="flex justify-start">
                                            <div className="max-w-[90%] rounded-2xl p-4 bg-white text-gray-800 shadow-sm rounded-tl-none border border-gray-100">
                                                <div className="text-[10px] uppercase font-bold text-gray-400 mb-2 border-b border-gray-100 pb-1">Resposta da IA (Raw)</div>
                                                <div className="prose prose-sm max-w-none">
                                                    <ReactMarkdown>{log.response_text || '*Resposta Vazia*'}</ReactMarkdown>
                                                </div>
                                            </div>
                                        </div>
                                    </>
                                ) : (
                                    /* Estado Vazio Real */
                                    <div className="flex flex-col items-center justify-center h-full text-gray-400 py-12">
                                        <svg className="w-16 h-16 mb-4 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                                        <p>Nenhuma mensagem ou dado de log encontrado.</p>
                                    </div>
                                )}
                            </>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
