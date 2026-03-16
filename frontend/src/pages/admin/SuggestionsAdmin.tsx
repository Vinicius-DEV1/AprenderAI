import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { adminGetSuggestions, adminUpdateSuggestionStatus } from '../../api/suggestions';
import { toast } from 'sonner';

export default function SuggestionsAdmin() {
    const [statusFilter, setStatusFilter] = useState('');
    const queryClient = useQueryClient();

    const { data, isLoading } = useQuery({
        queryKey: ['admin-suggestions', statusFilter],
        queryFn: () => adminGetSuggestions(statusFilter).then(r => r.data),
    });

    const suggestions = data?.suggestions ?? [];

    const statusMutation = useMutation({
        mutationFn: ({ id, status }: { id: number; status: string }) => adminUpdateSuggestionStatus(id, status),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-suggestions'] });
            toast.success('Status da sugestão atualizado');
        },
    });

    const statusColors: Record<string, string> = {
        pending:      'bg-slate-100 text-slate-600',
        under_review: 'bg-yellow-100 text-yellow-700',
        planned:      'bg-blue-100 text-blue-700',
        done:         'bg-green-100 text-green-700',
        rejected:     'bg-red-100 text-red-700',
    };

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100">Sugestões de Usuários</h1>
                <select
                    value={statusFilter}
                    onChange={e => setStatusFilter(e.target.value)}
                    className="border border-slate-300 dark:border-slate-700 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100"
                >
                    <option value="">Todos os status</option>
                    <option value="pending">Pendentes</option>
                    <option value="under_review">Em Análise</option>
                    <option value="planned">Planejado</option>
                    <option value="done">Implementado</option>
                    <option value="rejected">Rejeitado</option>
                </select>
            </div>

            <div className="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                {isLoading ? (
                    <div className="p-8 text-center text-slate-500">Carregando sugestões...</div>
                ) : suggestions.length === 0 ? (
                    <div className="p-8 text-center text-slate-500">Nenhuma sugestão encontrada.</div>
                ) : (
                    <div className="divide-y divide-slate-100 dark:divide-slate-800">
                        {suggestions.map((s: any) => (
                            <div key={s.id} className="p-6 flex flex-col md:flex-row gap-6">
                                {/* Vote Count Badge */}
                                <div className="flex-shrink-0 flex flex-col items-center justify-center w-16 h-16 bg-slate-50 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700">
                                    <span className="text-xl font-bold text-slate-800 dark:text-slate-200">{s.votes_count}</span>
                                    <span className="text-[10px] text-slate-500 uppercase font-semibold">Votos</span>
                                </div>

                                {/* Content */}
                                <div className="flex-1 min-w-0">
                                    <h3 className="text-lg font-bold text-slate-900 dark:text-slate-100 mb-1">{s.title}</h3>
                                    {s.body && <p className="text-slate-600 dark:text-slate-400 text-sm mb-3">{s.body}</p>}
                                    <div className="text-xs text-slate-500">
                                        Por <span className="font-semibold">{s.user?.name}</span> ({s.user?.email}) em {new Date(s.created_at).toLocaleDateString()}
                                    </div>
                                </div>

                                {/* Actions */}
                                <div className="w-full md:w-48 flex flex-col items-end gap-2 shrink-0">
                                    <span className={`text-[10px] font-bold px-2.5 py-1 rounded-md mb-1 ${statusColors[s.status] || 'bg-slate-100 text-slate-500'}`}>
                                        {s.status_label}
                                    </span>
                                    <select
                                        value={s.status}
                                        onChange={e => statusMutation.mutate({ id: s.id, status: e.target.value })}
                                        className="w-full text-sm border border-slate-300 dark:border-slate-700 rounded-lg px-2 py-1.5 bg-white dark:bg-slate-800"
                                    >
                                        <option value="pending">Pendente</option>
                                        <option value="under_review">Em Análise</option>
                                        <option value="planned">Planejado</option>
                                        <option value="done">Implementado</option>
                                        <option value="rejected">Rejeitar</option>
                                    </select>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
