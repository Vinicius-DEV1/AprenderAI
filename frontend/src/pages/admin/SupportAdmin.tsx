import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { adminGetTickets, adminGetTicket, adminReply, adminUpdateStatus } from '../../api/support';
import { toast } from 'sonner';

export default function SupportAdmin() {
    const [statusFilter, setStatusFilter] = useState('');
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [replyText, setReplyText] = useState('');
    const queryClient = useQueryClient();

    const { data: listData, isLoading } = useQuery({
        queryKey: ['admin-tickets', statusFilter],
        queryFn: () => adminGetTickets(statusFilter).then(r => r.data),
    });

    const { data: ticketData, refetch: refetchTicket } = useQuery({
        queryKey: ['admin-ticket', selectedId],
        queryFn: () => adminGetTicket(selectedId!).then(r => r.data.ticket),
        enabled: !!selectedId,
    });

    const statusMutation = useMutation({
        mutationFn: ({ id, status }: { id: number; status: string }) => adminUpdateStatus(id, status),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-tickets'] });
            refetchTicket();
            toast.success('Status atualizado');
        },
    });

    const replyMutation = useMutation({
        mutationFn: () => adminReply(selectedId!, replyText),
        onSuccess: () => {
            setReplyText('');
            refetchTicket();
            queryClient.invalidateQueries({ queryKey: ['admin-tickets'] });
            toast.success('Resposta enviada');
        },
    });

    const tickets = listData?.tickets ?? [];

    const statusColors: Record<string, string> = {
        new:           'bg-blue-100 text-blue-700',
        waiting_admin: 'bg-yellow-100 text-yellow-700',
        waiting_user:  'bg-green-100 text-green-700',
        resolved:      'bg-slate-100 text-slate-600',
        closed:        'bg-slate-100 text-slate-400',
    };

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100">Suporte (Tickets)</h1>
                <select
                    value={statusFilter}
                    onChange={e => setStatusFilter(e.target.value)}
                    className="border border-slate-300 dark:border-slate-700 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100"
                >
                    <option value="">Todos os status</option>
                    <option value="new">Novos</option>
                    <option value="waiting_admin">Aguardando Admin</option>
                    <option value="waiting_user">Aguardando Usuário</option>
                    <option value="resolved">Resolvidos</option>
                    <option value="closed">Fechados</option>
                </select>
            </div>

            <div className="flex gap-6 relative">
                {/* List */}
                <div className={`flex-1 bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden ${selectedId ? 'hidden lg:block lg:w-1/3 flex-none' : ''}`}>
                    {isLoading ? (
                        <div className="p-8 text-center text-slate-500">Carregando tickets...</div>
                    ) : tickets.length === 0 ? (
                        <div className="p-8 text-center text-slate-500">Nenhum ticket encontrado.</div>
                    ) : (
                        <div className="divide-y divide-slate-100 dark:divide-slate-800 h-[600px] overflow-y-auto">
                            {tickets.map((t: any) => (
                                <button
                                    key={t.id}
                                    onClick={() => setSelectedId(t.id)}
                                    className={`w-full text-left p-4 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors ${selectedId === t.id ? 'bg-blue-50 dark:bg-blue-900/20' : ''}`}
                                >
                                    <div className="flex justify-between items-start mb-2">
                                        <div className="flex-1 min-w-0 pr-2">
                                            <p className="font-semibold text-slate-900 dark:text-slate-100 truncate">{t.subject || 'Sem Assunto'}</p>
                                            <p className="text-xs text-slate-500 truncate">{t.user?.name} ({t.user?.email})</p>
                                        </div>
                                        <span className={`text-[10px] font-bold px-2 py-0.5 rounded-md self-start flex-shrink-0 ${statusColors[t.status] || 'bg-slate-100 text-slate-500'}`}>
                                            {t.status_label}
                                        </span>
                                    </div>
                                    {t.latest_message && (
                                        <p className="text-sm text-slate-600 dark:text-slate-400 line-clamp-2">
                                            {t.latest_message.sender_type === 'admin' ? '↳ ' : ''}{t.latest_message.body || '[Anexo]'}
                                        </p>
                                    )}
                                    <p className="text-[10px] text-slate-400 mt-2 text-right">
                                        {new Date(t.last_message_at).toLocaleString()}
                                    </p>
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                {/* Detail View */}
                {selectedId && ticketData && (
                    <div className="flex-1 bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 flex flex-col h-[600px]">
                        {/* Header */}
                        <div className="p-4 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800 rounded-t-xl">
                            <div className="flex items-center gap-3">
                                <button onClick={() => setSelectedId(null)} className="lg:hidden text-slate-500">← Voltar</button>
                                <div>
                                    <h2 className="font-bold text-slate-900 dark:text-slate-100">{ticketData.subject || 'Ticket #' + ticketData.id}</h2>
                                    <p className="text-xs text-slate-500">{ticketData.user?.name} ({ticketData.user?.email})</p>
                                </div>
                            </div>
                            <select
                                value={ticketData.status}
                                onChange={e => statusMutation.mutate({ id: ticketData.id, status: e.target.value })}
                                className="text-sm border border-slate-300 dark:border-slate-600 rounded-lg px-2 py-1 bg-white dark:bg-slate-700"
                            >
                                <option value="new">Novo</option>
                                <option value="waiting_admin">Aguardando Admin</option>
                                <option value="waiting_user">Aguardando Usuário</option>
                                <option value="resolved">Resolvido</option>
                                <option value="closed">Fechado</option>
                            </select>
                        </div>

                        {/* Messages */}
                        <div className="flex-1 overflow-y-auto p-6 space-y-4">
                            {ticketData.messages?.map((msg: any) => (
                                <div key={msg.id} className={`flex flex-col ${msg.sender_type === 'admin' ? 'items-end' : 'items-start'}`}>
                                    <div className={`max-w-[70%] rounded-xl px-4 py-3 shadow-sm ${
                                        msg.sender_type === 'admin'
                                            ? 'bg-blue-600 text-white rounded-br-none'
                                            : 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 rounded-bl-none'
                                    }`}>
                                        <p className="text-xs font-bold mb-1 opacity-80">{msg.sender_name}</p>
                                        <p className="whitespace-pre-wrap text-sm">{msg.body}</p>
                                        {msg.attachment_url && (
                                            <a href={msg.attachment_url} target="_blank" rel="noopener noreferrer">
                                                <img src={msg.attachment_url} alt="anexo" className="mt-2 rounded-lg max-h-48 object-cover" />
                                            </a>
                                        )}
                                    </div>
                                    <span className="text-[10px] text-slate-400 mt-1">{new Date(msg.created_at).toLocaleString()}</span>
                                </div>
                            ))}
                        </div>

                        {/* Reply Form */}
                        {ticketData.status !== 'closed' && (
                            <div className="p-4 border-t border-slate-100 dark:border-slate-700 flex gap-2">
                                <textarea
                                    value={replyText}
                                    onChange={e => setReplyText(e.target.value)}
                                    placeholder="Digite sua resposta..."
                                    rows={2}
                                    className="flex-1 border border-slate-300 dark:border-slate-600 rounded-lg p-2 text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100"
                                />
                                <button
                                    onClick={() => replyMutation.mutate()}
                                    disabled={!replyText.trim() || replyMutation.isPending}
                                    className="px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold disabled:opacity-50"
                                >
                                    {replyMutation.isPending ? 'Enviando...' : 'Enviar'}
                                </button>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
