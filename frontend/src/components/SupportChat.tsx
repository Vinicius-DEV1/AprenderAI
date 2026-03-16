import { useState, useEffect, useRef } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { getTickets, openTicket, getTicket, sendMessage } from '../api/support';

interface Message {
    id: number;
    sender_type: 'user' | 'admin';
    sender_name: string;
    body: string | null;
    attachment_url: string | null;
    created_at: string;
}

/**
 * SupportChat — fixed support button + slide-in mini-chat panel.
 *
 * Rendered at the AppLayout level so it persists across all authenticated pages.
 * Opens as a vertical chat widget anchored to the bottom-right corner.
 */
export default function SupportChat() {
    const [isOpen, setIsOpen] = useState(false);
    const [activeTicketId, setActiveTicketId] = useState<number | null>(null);
    const [messageText, setMessageText] = useState('');
    const [attachment, setAttachment] = useState<File | null>(null);
    const [isSending, setIsSending] = useState(false);
    const [view, setView] = useState<'list' | 'chat' | 'new'>('list');
    const [newSubject, setNewSubject] = useState('');
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const queryClient = useQueryClient();

    // List of user's tickets
    const { data: ticketsData, isLoading: loadingTickets } = useQuery({
        queryKey: ['support-tickets'],
        queryFn: () => getTickets().then(r => r.data.tickets),
        enabled: isOpen,
        refetchInterval: isOpen ? 15000 : false,
    });

    // Active ticket messages
    const { data: ticketData, refetch: refetchTicket } = useQuery({
        queryKey: ['support-ticket', activeTicketId],
        queryFn: () => getTicket(activeTicketId!).then(r => r.data.ticket),
        enabled: !!activeTicketId && isOpen,
        refetchInterval: activeTicketId && isOpen ? 5000 : false,
    });

    // Scroll to bottom when messages load
    useEffect(() => {
        if (messagesEndRef.current) {
            messagesEndRef.current.scrollIntoView({ behavior: 'smooth' });
        }
    }, [ticketData?.messages]);

    const handleSend = async () => {
        if (!messageText.trim() && !attachment) return;
        setIsSending(true);

        const formData = new FormData();
        if (messageText.trim()) formData.append('message', messageText);
        if (attachment) formData.append('attachment', attachment);

        try {
            if (view === 'new') {
                // Open a new ticket
                formData.append('subject', newSubject || 'Suporte');
                const res = await openTicket(formData);
                const ticketId = res.data.ticket.id;
                queryClient.invalidateQueries({ queryKey: ['support-tickets'] });
                setActiveTicketId(ticketId);
                setView('chat');
            } else if (activeTicketId) {
                await sendMessage(activeTicketId, formData);
                refetchTicket();
            }
            setMessageText('');
            setAttachment(null);
        } catch (err) {
            console.error('Support send error:', err);
        } finally {
            setIsSending(false);
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
    };

    const formatTime = (iso: string) =>
        new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

    const statusColors: Record<string, string> = {
        new:           'bg-blue-100 text-blue-700',
        waiting_admin: 'bg-yellow-100 text-yellow-700',
        waiting_user:  'bg-green-100 text-green-700',
        resolved:      'bg-slate-100 text-slate-600',
        closed:        'bg-slate-100 text-slate-400',
    };

    return (
        <>
            {/* ── Floating Support Button ─────────────────────────────────────── */}
            <button
                onClick={() => setIsOpen(o => !o)}
                className="fixed bottom-6 right-6 z-50 w-14 h-14 bg-gradient-to-br from-blue-600 to-indigo-600 text-white rounded-full shadow-2xl flex items-center justify-center hover:scale-110 active:scale-95 transition-transform duration-200 border-2 border-white"
                title="Abrir suporte"
                aria-label="Suporte"
            >
                {isOpen ? (
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                ) : (
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                            d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                )}
            </button>

            {/* ── Chat Panel ──────────────────────────────────────────────────── */}
            {isOpen && (
                <div className="fixed bottom-24 right-6 z-50 w-80 sm:w-96 bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 flex flex-col overflow-hidden"
                    style={{ maxHeight: '520px' }}>

                    {/* Header */}
                    <div className="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-blue-600 to-indigo-600">
                        <div className="flex items-center gap-2">
                            {(view === 'chat' || view === 'new') && (
                                <button
                                    onClick={() => { setView('list'); setActiveTicketId(null); }}
                                    className="text-white/70 hover:text-white mr-1"
                                >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                                    </svg>
                                </button>
                            )}
                            <div className="w-2 h-2 rounded-full bg-green-400 shadow-sm"></div>
                            <span className="text-white font-semibold text-sm">Suporte</span>
                        </div>
                        {view === 'list' && (
                            <button
                                onClick={() => setView('new')}
                                className="text-white/80 hover:text-white text-xs font-medium bg-white/10 hover:bg-white/20 px-2 py-1 rounded-lg transition-colors"
                            >
                                + Nova conversa
                            </button>
                        )}
                    </div>

                    {/* Body */}
                    <div className="flex-1 overflow-y-auto">
                        {/* ── VIEW: List ── */}
                        {view === 'list' && (
                            <div>
                                {/* Welcome message */}
                                <div className="m-3 p-3 bg-slate-50 dark:bg-slate-800 rounded-xl border border-slate-100 dark:border-slate-700 text-sm text-slate-600 dark:text-slate-300">
                                    <p className="font-semibold mb-1">Olá! 👋</p>
                                    <p>Qual sua dúvida? Envie sua mensagem e um atendente responderá o mais rápido possível.</p>
                                </div>

                                {loadingTickets ? (
                                    <div className="flex justify-center py-6">
                                        <div className="animate-spin w-5 h-5 border-2 border-blue-500 border-t-transparent rounded-full"></div>
                                    </div>
                                ) : ticketsData?.length === 0 ? (
                                    <div className="px-4 py-3 text-center text-xs text-slate-400">
                                        Nenhuma conversa ainda.
                                    </div>
                                ) : (
                                    <div className="divide-y divide-slate-100 dark:divide-slate-800">
                                        {ticketsData?.map((t: any) => (
                                            <button
                                                key={t.id}
                                                className="w-full text-left px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                                                onClick={() => { setActiveTicketId(t.id); setView('chat'); }}
                                            >
                                                <div className="flex items-center justify-between mb-1">
                                                    <span className="text-sm font-medium text-slate-800 dark:text-slate-200 truncate">
                                                        {t.subject}
                                                    </span>
                                                    <span className={`text-[10px] font-semibold px-1.5 py-0.5 rounded-md ml-2 flex-shrink-0 ${statusColors[t.status] || 'bg-slate-100 text-slate-500'}`}>
                                                        {t.status_label}
                                                    </span>
                                                </div>
                                                {t.latest_message && (
                                                    <p className="text-xs text-slate-400 dark:text-slate-500 truncate">
                                                        {t.latest_message.sender_type === 'admin' ? '↩ ' : ''}{t.latest_message.body}
                                                    </p>
                                                )}
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}

                        {/* ── VIEW: New ticket ── */}
                        {view === 'new' && (
                            <div className="p-4 space-y-3">
                                <p className="text-sm font-medium text-slate-700 dark:text-slate-300">Nova conversa</p>
                                <input
                                    type="text"
                                    placeholder="Assunto (opcional)"
                                    value={newSubject}
                                    onChange={e => setNewSubject(e.target.value)}
                                    className="w-full text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 outline-none"
                                />
                            </div>
                        )}

                        {/* ── VIEW: Chat messages ── */}
                        {view === 'chat' && (
                            <div className="flex flex-col gap-3 p-4">
                                {ticketData?.messages?.map((msg: Message) => (
                                    <div key={msg.id} className={`flex flex-col ${msg.sender_type === 'user' ? 'items-end' : 'items-start'}`}>
                                        <div className={`max-w-[75%] rounded-2xl px-3 py-2 text-sm shadow-sm ${
                                            msg.sender_type === 'user'
                                                ? 'bg-blue-600 text-white rounded-br-none'
                                                : 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 rounded-bl-none'
                                        }`}>
                                            {msg.body && <p className="whitespace-pre-wrap break-words">{msg.body}</p>}
                                            {msg.attachment_url && (
                                                <img src={msg.attachment_url} alt="anexo" className="mt-1 rounded-lg max-w-full cursor-pointer" onClick={() => window.open(msg.attachment_url!, '_blank')} />
                                            )}
                                        </div>
                                        <span className="text-[10px] text-slate-400 mt-1 px-1">{formatTime(msg.created_at)}</span>
                                    </div>
                                ))}
                                <div ref={messagesEndRef} />
                            </div>
                        )}
                    </div>

                    {/* Input Area */}
                    {(view === 'chat' || view === 'new') && (
                        <div className="border-t border-slate-100 dark:border-slate-700 p-3 space-y-2">
                            {attachment && (
                                <div className="flex items-center gap-2 text-xs text-slate-500 bg-slate-50 dark:bg-slate-800 rounded-lg px-2 py-1.5">
                                    <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                    </svg>
                                    <span className="truncate">{attachment.name}</span>
                                    <button onClick={() => setAttachment(null)} className="ml-auto text-slate-400 hover:text-red-500">✕</button>
                                </div>
                            )}
                            <div className="flex items-end gap-2">
                                <button
                                    onClick={() => fileInputRef.current?.click()}
                                    className="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-950 transition-colors"
                                    title="Anexar imagem"
                                >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </button>
                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    accept="image/*"
                                    className="hidden"
                                    onChange={e => setAttachment(e.target.files?.[0] || null)}
                                />
                                <textarea
                                    value={messageText}
                                    onChange={e => setMessageText(e.target.value)}
                                    onKeyDown={handleKeyDown}
                                    placeholder="Digite sua mensagem..."
                                    rows={1}
                                    className="flex-1 resize-none text-sm border border-slate-200 dark:border-slate-600 rounded-xl px-3 py-2 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 outline-none max-h-24 overflow-y-auto"
                                    style={{ minHeight: '36px' }}
                                />
                                <button
                                    onClick={handleSend}
                                    disabled={isSending || (!messageText.trim() && !attachment)}
                                    className="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-xl bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                                >
                                    {isSending ? (
                                        <div className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                                    ) : (
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                        </svg>
                                    )}
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            )}
        </>
    );
}
