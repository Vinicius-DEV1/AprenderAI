import { useParams, Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/axios';
import { toast } from 'sonner';

export default function AdminChatLogs() {
    const { id } = useParams<{ id: string }>();

    const fetchChatLogs = async () => {
        try {
            const { data } = await api.get(`/api/v1/admin/chat-logs/${id}`);
            return data;
        } catch (error: any) {
            // The global interceptor will handle 401, 403, 500
            // But we can throw or handle 404 here
            if (error.response?.status === 404) {
                toast.error('Log de conversa não encontrado.');
            }
            throw error;
        }
    };

    const { data: logData, isLoading, isError } = useQuery({
        queryKey: ['adminChatLogs', id],
        queryFn: fetchChatLogs,
        enabled: !!id,
    });

    if (isLoading) {
        return (
            <div className="flex justify-center items-center h-64">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
            </div>
        );
    }

    if (isError || !logData) {
        return (
            <div className="p-6 bg-red-50 text-red-700 rounded-lg">
                <h3 className="font-bold">Erro ao carregar o log de conversação.</h3>
                <Link to="/admin/dashboard" className="text-red-600 underline text-sm mt-2 block">Voltar ao Painel</Link>
            </div>
        );
    }

    const { question, user, messages, created_at } = logData.data;

    return (
        <div className="max-w-4xl mx-auto py-8 px-4">
            <div className="mb-6 flex justify-between items-center bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                <div>
                    <h1 className="text-2xl font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        🛡️ Auditoria do Tutor IA
                    </h1>
                    <p className="text-slate-500 dark:text-slate-400 mt-1 text-sm">
                        Sessão de {new Date(created_at).toLocaleString()}
                    </p>
                </div>
                <Link to="/admin/dashboard" className="px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                    Voltar
                </Link>
            </div>

            {/* Context Header */}
            <div className="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-5 mb-6">
                <div className="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span className="font-semibold text-slate-600 dark:text-slate-400">Aluno:</span>
                        <div className="font-medium text-slate-800 dark:text-slate-200 mt-1">{user?.name} ({user?.email})</div>
                    </div>
                    <div>
                        <span className="font-semibold text-slate-600 dark:text-slate-400">ID da Questão Base:</span>
                        <div className="font-medium text-indigo-600 dark:text-indigo-400 mt-1">#{question?.id}</div>
                    </div>
                </div>
            </div>

            {/* Conversation Thread */}
            <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div className="p-4 bg-slate-100 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
                    <h3 className="font-bold text-slate-700 dark:text-slate-300">Transcrição Completa</h3>
                </div>
                <div className="p-6 space-y-6 max-h-[600px] overflow-y-auto">
                    {messages && messages.length > 0 ? (
                        messages.map((msg: any, idx: number) => (
                            <div key={idx} className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                                <div className={`max-w-[80%] rounded-2xl p-4 ${msg.role === 'user'
                                    ? 'bg-indigo-600 text-white rounded-br-none'
                                    : 'bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-200 rounded-bl-none'
                                    }`}>
                                    <div className="text-xs font-bold mb-1 opacity-75 uppercase tracking-wide">
                                        {msg.role === 'user' ? 'Aluno (Prompt)' : 'Tutor IA (Resposta)'}
                                    </div>
                                    <div className="whitespace-pre-wrap text-sm leading-relaxed font-sans" dangerouslySetInnerHTML={{ __html: msg.content || msg.text }} />
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="text-center text-slate-500 py-8">
                            Nenhuma mensagem gravada nesta sessão.
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
