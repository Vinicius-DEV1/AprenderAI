import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link, useSearchParams } from 'react-router-dom';
import api from '../../api/axios';
import { toast } from 'sonner';

export default function ImportReviewIndex() {
    const [searchParams, setSearchParams] = useSearchParams();
    const queryClient = useQueryClient();

    const importId = searchParams.get('import_id') || '';
    const organization = searchParams.get('organization') || '';
    const issue = searchParams.get('issue') || '';
    const quality = searchParams.get('quality') || '';
    const page = searchParams.get('page') || '1';

    const { data: summaryStats } = useQuery({
        queryKey: ['admin-import-review-summary'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/import/review/summary');
            return res.data;
        }
    });

    const { data, isLoading } = useQuery({
        queryKey: ['admin-import-review-list', importId, organization, issue, quality, page],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/import/review', {
                params: { import_id: importId, organization, issue, quality, page }
            });
            return res.data;
        }
    });

    const approveMutation = useMutation({
        mutationFn: async (id: number) => {
            return await api.post(`/api/v1/admin/import/review/${id}/approve`);
        },
        onSuccess: () => {
            toast.success('Questão aprovada com sucesso!');
            queryClient.invalidateQueries({ queryKey: ['admin-import-review-list'] });
        },
        onError: () => toast.error('Falha ao aprovar questão.')
    });

    const revertMutation = useMutation({
        mutationFn: async (id: number) => {
            return await api.post(`/api/v1/admin/import/review/${id}/revert`);
        },
        onSuccess: () => {
            toast.success('Questão revertida para revisão.');
            queryClient.invalidateQueries({ queryKey: ['admin-import-review-list'] });
        },
        onError: () => toast.error('Falha ao reverter questão.')
    });

    if (isLoading) return <div className="p-8">Carregando painel de revisão...</div>;

    const { pendingQuestions, imports, organizations, recentActions } = data || {
        pendingQuestions: { data: [], links: [] },
        imports: [],
        organizations: [],
        recentActions: []
    };

    const handleFilterChange = (e: React.ChangeEvent<HTMLSelectElement | HTMLInputElement>) => {
        const { name, value } = e.target;
        const newParams = new URLSearchParams(searchParams);
        if (value) newParams.set(name, value);
        else newParams.delete(name);
        newParams.set('page', '1'); // Reset to page 1 on filter
        setSearchParams(newParams);
    };

    const clearFilters = () => {
        setSearchParams({});
    };

    return (
        <div className="py-6 bg-gray-50 min-h-screen">
            <div className="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex justify-between items-center mb-6">
                    <div className="flex items-center gap-4">
                        <Link to="/admin/import" className="text-gray-400 hover:text-gray-600">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7"></path></svg>
                        </Link>
                        <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                            🔍 Painel de Revisão de Questões
                        </h2>
                        {pendingQuestions.total > 0 ? (
                            <span className="px-3 py-1 bg-yellow-100 text-yellow-700 text-sm font-semibold rounded-full">
                                {pendingQuestions.total} pendentes
                            </span>
                        ) : (
                            <span className="px-3 py-1 bg-green-100 text-green-700 text-sm font-semibold rounded-full">
                                ✅ Tudo revisado
                            </span>
                        )}
                    </div>
                </div>

                {/* Summary Cards */}
                {summaryStats && (
                    <div className="grid grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-3 mb-6">
                        {[
                            { id: 'no_alternatives', icon: '⚠️', label: 'Sem Alts', color: 'bg-red-50 text-red-700 border-red-200 hover:bg-red-100' },
                            { id: 'no_statement', icon: '📝', label: 'Sem Enunciado', color: 'bg-orange-50 text-orange-700 border-orange-200 hover:bg-orange-100' },
                            { id: 'wrong_answer', icon: '❌', label: 'Gabarito Errado', color: 'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100' },
                            { id: 'has_image', icon: '🖼️', label: 'Tem Imagem', color: 'bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100' },
                            { id: 'missing_image', icon: '❓', label: 'Img Faltando', color: 'bg-purple-50 text-purple-700 border-purple-200 hover:bg-purple-100' },
                            { id: 'missing_support_text', icon: '📄', label: 'Texto Faltando', color: 'bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100' },
                            { id: 'low_quality', icon: '👎', label: 'Baixa Qualid.', color: 'bg-stone-50 text-stone-700 border-stone-200 hover:bg-stone-100', isQuality: true },
                        ].map(card => {
                            const count = summaryStats[card.id] || 0;
                            const isSelected = card.isQuality ? quality === 'low' : issue === card.id;

                            return (
                                <button
                                    key={card.id}
                                    onClick={() => {
                                        const newParams = new URLSearchParams(searchParams);
                                        if (isSelected) {
                                            if (card.isQuality) newParams.delete('quality');
                                            else newParams.delete('issue');
                                        } else {
                                            if (card.isQuality) {
                                                newParams.set('quality', 'low');
                                                newParams.delete('issue');
                                            } else {
                                                newParams.set('issue', card.id);
                                                newParams.delete('quality');
                                            }
                                        }
                                        newParams.set('page', '1');
                                        setSearchParams(newParams);
                                    }}
                                    className={`flex flex-col items-center justify-center p-3 rounded-xl border transition-all ${card.color} ${isSelected ? 'ring-2 ring-offset-1 ring-gray-400 shadow-sm scale-105' : 'opacity-80'}`}
                                >
                                    <div className="text-xl mb-1">{card.icon}</div>
                                    <div className="font-bold text-[10px] uppercase tracking-wider mb-1 text-center leading-none">{card.label}</div>
                                    <div className="text-lg font-black">{count}</div>
                                </button>
                            );
                        })}
                    </div>
                )}

                <div className="grid grid-cols-1 xl:grid-cols-4 gap-6 items-start">
                    {/* LEFT: Question List */}
                    <div className="xl:col-span-3 space-y-4">
                        {pendingQuestions.data.length > 0 ? (
                            <>
                                {pendingQuestions.data.map((question: any) => (
                                    <div key={question.id} className="bg-white rounded-lg shadow-sm overflow-hidden border-l-4 border-yellow-400 hover:shadow-md transition-shadow p-5">
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="flex-1 min-w-0">
                                                <div className="mb-3">
                                                    <div className="flex flex-wrap gap-2 mb-2">
                                                        <span className="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded font-medium">#{question.id}</span>
                                                        {question.organization && (
                                                            <span className="px-2 py-0.5 bg-indigo-100 text-indigo-700 text-xs rounded font-semibold">{question.organization}</span>
                                                        )}
                                                        {question.year && (
                                                            <span className="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded font-medium">{question.year}</span>
                                                        )}
                                                    </div>
                                                    {question.role && (
                                                        <h4 className="text-gray-800 font-bold text-sm leading-tight mb-2">{question.role}</h4>
                                                    )}
                                                    <div className="flex flex-wrap gap-1.5">
                                                        {question.subjects?.slice(0, 3).map((s: any) => (
                                                            <span key={s.id} className="px-2 py-0.5 bg-green-50 text-green-700 border border-green-200 text-[11px] uppercase tracking-wider rounded">{s.name}</span>
                                                        ))}
                                                    </div>
                                                </div>

                                                <p className="text-gray-800 text-sm leading-relaxed line-clamp-3">
                                                    {question.statement}
                                                </p>

                                                <div className="flex flex-wrap gap-2 mt-3">
                                                    {question.image_path && (
                                                        <span className="px-2 py-0.5 bg-orange-100 text-orange-700 text-[10px] font-bold uppercase tracking-wider rounded border border-orange-200 flex items-center gap-1">
                                                            🖼️ Tem imagem
                                                        </span>
                                                    )}
                                                    {(!question.alternatives || question.alternatives.length === 0) ? (
                                                        <span className="px-2 py-0.5 bg-red-100 text-red-700 text-[10px] font-bold uppercase tracking-wider rounded border border-red-200">⚠️ Sem alternativas</span>
                                                    ) : (
                                                        <span className="px-2 py-0.5 bg-gray-100 text-gray-600 text-[10px] font-bold uppercase tracking-wider rounded border border-gray-200">{question.alternatives.length} alternativas</span>
                                                    )}

                                                    {/* Mostrar Issues Mais Recentes */}
                                                    {question.triage_logs && question.triage_logs.length > 0 && (
                                                        <>
                                                            {question.triage_logs[0].issues_detected?.map((iss: string) => (
                                                                <span key={iss} className="px-2 py-0.5 bg-red-50 text-red-700 text-[10px] font-bold uppercase tracking-wider rounded border border-red-200">
                                                                    🚫 {iss.replace(/_/g, ' ')}
                                                                </span>
                                                            ))}
                                                            {question.triage_logs[0].quality_score < 60 && (
                                                                <span className="px-2 py-0.5 bg-pink-50 text-pink-700 text-[10px] font-bold uppercase tracking-wider rounded border border-pink-200">
                                                                    👎 Score: {question.triage_logs[0].quality_score}
                                                                </span>
                                                            )}
                                                        </>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="flex flex-col gap-2 flex-shrink-0 min-w-[140px]">
                                                <Link to={`/admin/import/review/${question.id}?${searchParams.toString()}`}
                                                    className="flex items-center justify-center gap-2 px-3 py-2 bg-indigo-50 text-indigo-700 border border-indigo-200 text-sm rounded-md hover:bg-indigo-100 font-medium transition-colors w-full">
                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                                    Inspecionar
                                                </Link>
                                                <button
                                                    onClick={() => { if (confirm('Aprovar questão sem inspeção visual?')) approveMutation.mutate(question.id) }}
                                                    className="flex items-center justify-center gap-2 w-full px-3 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700 font-medium transition-colors">
                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path></svg>
                                                    Aprovar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                ))}

                                {/* Pagination */}
                                {pendingQuestions.last_page > 1 && (
                                    <div className="flex justify-center gap-2 mt-6">
                                        {Array.from({ length: pendingQuestions.last_page }, (_, i) => i + 1).map(p => (
                                            <button
                                                key={p}
                                                onClick={() => {
                                                    const newParams = new URLSearchParams(searchParams);
                                                    newParams.set('page', p.toString());
                                                    setSearchParams(newParams);
                                                }}
                                                className={`px-3 py-1 rounded ${page === p.toString() ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50'}`}
                                            >
                                                {p}
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </>
                        ) : (
                            <div className="bg-white rounded-lg shadow-sm p-12 text-center">
                                <div className="text-5xl mb-4">🎉</div>
                                <h3 className="text-lg font-semibold text-gray-800 mb-2">Nenhuma questão pendente!</h3>
                                <p className="text-gray-500 text-sm mb-6">Todas as questões importadas foram revisadas.</p>
                                <Link to="/admin/import" className="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">
                                    Importar Novo Lote
                                </Link>
                            </div>
                        )}
                    </div>

                    {/* RIGHT: Filters & History */}
                    <div className="space-y-6">
                        <div className="bg-white rounded-lg shadow-sm p-5">
                            <h3 className="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                                Filtros
                            </h3>
                            <div className="space-y-4">
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-1">Lote de Importação</label>
                                    <select name="import_id" value={importId} onChange={handleFilterChange} className="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Todos</option>
                                        {imports.map((imp: any) => (
                                            <option key={imp.id} value={imp.id}>#{imp.id} — {imp.batch_name.substring(0, 25)}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-1">Banca Organizadora</label>
                                    <select name="organization" value={organization} onChange={handleFilterChange} className="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Todas</option>
                                        {organizations.map((org: string) => (
                                            <option key={org} value={org}>{org}</option>
                                        ))}
                                    </select>
                                </div>
                                <div className="grid grid-cols-2 gap-2">
                                    <div>
                                        <label className="block text-[10px] font-bold uppercase text-gray-600 mb-1">Issue</label>
                                        <select name="issue" value={issue} onChange={handleFilterChange} className="w-full text-xs border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="">Todas</option>
                                            <option value="no_alternatives">Sem Alternativas</option>
                                            <option value="no_statement">Sem Enunciado</option>
                                            <option value="wrong_answer">Gabarito Errado</option>
                                            <option value="has_image">Tem Imagem</option>
                                            <option value="missing_image">Imagem Faltando</option>
                                            <option value="missing_support_text">Texto Base Faltando</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-[10px] font-bold uppercase text-gray-600 mb-1">Qualidade</label>
                                        <select name="quality" value={quality} onChange={handleFilterChange} className="w-full text-xs border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="">Todas</option>
                                            <option value="low">Abaixo de 60</option>
                                        </select>
                                    </div>
                                </div>
                                <button onClick={clearFilters} className="w-full text-center px-4 py-2 bg-gray-100 text-gray-700 text-sm rounded-md hover:bg-gray-200 font-medium">
                                    Limpar Filtros
                                </button>
                            </div>
                        </div>

                        <div className="bg-white rounded-lg shadow-sm overflow-hidden border-t border-gray-100">
                            <div className="px-5 py-4 border-b border-gray-100">
                                <h3 className="text-sm font-semibold text-gray-700 flex items-center gap-2">
                                    📋 Últimas Revisões
                                </h3>
                            </div>
                            <div className="divide-y divide-gray-50 max-h-[500px] overflow-y-auto">
                                {recentActions.length > 0 ? (
                                    recentActions.map((item: any) => (
                                        <div key={item.id} className="p-4 hover:bg-gray-50">
                                            <div className="flex items-start justify-between gap-2">
                                                <div className="flex-1 min-w-0">
                                                    <p className="text-xs font-medium text-gray-700 truncate">
                                                        {item.reverted_at ? '↩️' : '✅'} Questão #{item.question_id}
                                                    </p>
                                                    <p className="text-xs text-gray-500 truncate mt-0.5">
                                                        {item.question?.statement || '...'}
                                                    </p>
                                                    <p className="text-[10px] text-gray-400 mt-1">
                                                        por <strong>{item.approver?.name || 'Sistema'}</strong> • {new Date(item.updated_at).toLocaleDateString()}
                                                    </p>
                                                </div>
                                                {item.question?.review_status === 'approved' && (
                                                    <button
                                                        onClick={() => { if (confirm('Retornar esta questão para revisão?')) revertMutation.mutate(item.question_id) }}
                                                        title="Retornar para revisão"
                                                        className="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded hover:bg-orange-100 hover:text-orange-700 transition-colors">
                                                        ↩️
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <p className="p-8 text-center text-xs text-gray-400">Nenhuma revisão ainda.</p>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
