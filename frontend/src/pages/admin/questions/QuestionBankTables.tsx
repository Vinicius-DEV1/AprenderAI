import { Link } from 'react-router-dom';
import { DifficultyBadge, TriageAction } from './components/Common';

export function AllQuestionsTable({ questions, adminActions, mainActiveMenu, setMainActiveMenu, setDeleteModal }: any) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-left">
                <thead>
                    <tr className="bg-gray-50/80">
                        <th className="px-4 py-3 text-[10px] font-black text-gray-400 uppercase w-16">ID</th>
                        <th className="px-4 py-3 text-[10px] font-black text-gray-400 uppercase">Enunciado / Disciplina</th>
                        <th className="px-4 py-3 text-[10px] font-black text-gray-400 uppercase w-28">Dificuldade</th>
                        <th className="px-4 py-3 text-[10px] font-black text-gray-400 uppercase text-center w-24">Ações</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    {questions.data.map((q: any) => (
                        <tr key={q.id} className="hover:bg-gray-50/50 transition-colors">
                            <td className="px-4 py-3 text-[11px] font-black text-gray-300 font-mono">#{q.id}</td>
                            <td className="px-4 py-3">
                                <div className="flex flex-col">
                                    <div dangerouslySetInnerHTML={{ __html: q.statement }} className="text-[11px] font-bold text-gray-700 line-clamp-1 max-w-[500px]" />
                                    <div className="flex flex-wrap gap-2 mt-1.5 items-center">
                                        <div className="flex gap-1 flex-wrap">
                                            {(q.subjects || []).map((s: any) => (
                                                <span key={s.id} className="text-[9px] font-black bg-indigo-50 text-indigo-600 px-1.5 py-0.5 rounded uppercase tracking-tighter shadow-sm border border-indigo-100">{s.name}</span>
                                            ))}
                                            {(!q.subjects || q.subjects.length === 0) && <span className="text-[9px] font-black text-indigo-400 uppercase">Sem Matéria</span>}
                                        </div>
                                        <span className="text-[9px] font-black text-gray-300 uppercase leading-none self-center">•</span>
                                        <div className="flex gap-1 flex-wrap">
                                            {(q.topics || []).map((t: any) => (
                                                <span key={t.id} className="text-[9px] font-black bg-emerald-50 text-emerald-600 px-1.5 py-0.5 rounded uppercase tracking-tighter shadow-sm border border-emerald-100">{t.name}</span>
                                            ))}
                                        </div>
                                        <span className="text-[9px] font-black text-gray-300 uppercase leading-none self-center">•</span>
                                        <span className="text-[9px] font-black text-gray-400 uppercase">{q.organization || 'AprenderAI'}</span>
                                        <span className="text-[10px] font-black text-gray-300 uppercase leading-none self-center">•</span>
                                        {q.tipo_questao === 'Redação' ? (
                                            <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-orange-100 text-orange-700 uppercase tracking-tighter">✍️ Redação</span>
                                        ) : q.tipo_questao === 'Discursiva' ? (
                                            <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-blue-100 text-blue-700 uppercase tracking-tighter">🎓 Discursiva</span>
                                        ) : q.format === 'true_false' ? (
                                            <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-purple-100 text-purple-700 uppercase tracking-tighter">⚖️ Certo/Errado</span>
                                        ) : (
                                            <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-gray-100 text-gray-600 uppercase tracking-tighter">📝 Múltipla Escolha</span>
                                        )}
                                    </div>
                                </div>
                            </td>
                            <td className="px-4 py-3">
                                <DifficultyBadge level={q.difficulty} />
                            </td>
                            <td className="px-4 py-3 text-center relative">
                                <button
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        setMainActiveMenu(mainActiveMenu === q.id ? null : q.id);
                                    }}
                                    className={`w-8 h-8 mx-auto rounded-full flex items-center justify-center hover:bg-gray-100 transition-all ${mainActiveMenu === q.id ? 'bg-indigo-600 text-white shadow-md' : 'text-gray-400'}`}
                                >
                                    <span className="text-xl leading-none">⋮</span>
                                </button>

                                {mainActiveMenu === q.id && (
                                    <>
                                        <div
                                            className="fixed inset-0 z-[60]"
                                            onClick={() => setMainActiveMenu(null)}
                                        ></div>
                                        <div className="absolute right-8 top-1/2 -translate-y-1/2 z-[70] bg-white rounded-2xl shadow-2xl border border-gray-100 p-2 min-w-[200px] animate-in zoom-in-95 duration-200">
                                            <div className="flex flex-col gap-1 text-left">
                                                <div className="px-3 py-1.5 mb-1 border-b border-gray-50 flex justify-between items-center">
                                                    <span className="text-[10px] font-black text-gray-300 uppercase tracking-widest">Ações da Questão</span>
                                                </div>

                                                <Link to={`/admin/questions/${q.id}/edit`} className="px-3 py-2 text-[10px] rounded-xl font-black transition flex items-center gap-2 bg-blue-50 text-blue-700 hover:bg-blue-100 uppercase tracking-wider">
                                                    ✏️ Editar Manual
                                                </Link>

                                                <div className="h-0.5 bg-gray-50 my-1"></div>

                                                <TriageAction
                                                    icon="⚡ Avaliar IA"
                                                    onClick={() => adminActions.mutate({ id: q.id, action: 'evaluate-difficulty' })}
                                                    pending={adminActions.isPending && adminActions.variables?.id === q.id && adminActions.variables?.action === 'evaluate-difficulty'}
                                                    variant="blade-purple"
                                                />
                                                <TriageAction
                                                    icon="🔄 Reprocessar (Reset)"
                                                    onClick={() => adminActions.mutate({ id: q.id, action: 'retry-evaluation' })}
                                                    pending={adminActions.isPending && adminActions.variables?.id === q.id && adminActions.variables?.action === 'retry-evaluation'}
                                                    variant="blade-gray"
                                                />

                                                <div className="h-0.5 bg-gray-50 my-1"></div>

                                                <button
                                                    onClick={() => {
                                                        setDeleteModal({ isOpen: true, id: q.id });
                                                        setMainActiveMenu(null);
                                                    }}
                                                    className="px-3 py-2 text-[10px] rounded-xl font-black transition flex items-center gap-2 bg-red-50 text-red-600 hover:bg-red-100 uppercase tracking-wider"
                                                >
                                                    🗑️ Excluir
                                                </button>
                                            </div>
                                        </div>
                                    </>
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export function ReportedQuestionsTable({ reportsData, reportActions }: any) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-left">
                <thead>
                    <tr className="bg-red-50/50">
                        <th className="px-4 py-3 text-[10px] font-black text-red-500 uppercase w-16">ID / Qtd</th>
                        <th className="px-4 py-3 text-[10px] font-black text-red-500 uppercase">Questão & Motivos</th>
                        <th className="px-4 py-3 text-[10px] font-black text-red-500 uppercase text-right">Ações de Curadoria</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    {reportsData?.data?.length === 0 ? (
                        <tr>
                            <td colSpan={3} className="px-4 py-12 text-center text-gray-400 font-bold">Nenhuma questão denunciada! 🎉</td>
                        </tr>
                    ) : reportsData?.data?.map((q: any) => (
                        <tr key={q.id} className="hover:bg-red-50/30 transition-colors">
                            <td className="px-4 py-3">
                                <div className="flex flex-col items-start gap-1">
                                    <span className="text-[11px] font-black text-gray-400 font-mono">#{q.id}</span>
                                    <span className="bg-red-100 text-red-700 text-[10px] font-bold px-2 py-0.5 rounded-md flex items-center gap-1">
                                        <span>🚩</span> {q.reports_count} reports
                                    </span>
                                </div>
                            </td>
                            <td className="px-4 py-3">
                                <div className="flex flex-col gap-2">
                                    <div dangerouslySetInnerHTML={{ __html: q.statement }} className="text-[11px] font-bold text-gray-700 line-clamp-2 max-w-[500px]" />

                                    <div className="flex flex-col gap-1 mt-1 border-l-2 border-red-200 pl-3">
                                        {q.reports?.map((rep: any) => (
                                            <div key={rep.id} className="text-[10px] text-gray-600 flex flex-col">
                                                <span className="font-bold text-red-600">"{rep.reason}"</span>
                                                <span className="text-gray-400">Por {rep.user?.name || 'Aluno'} em {new Date(rep.created_at).toLocaleDateString()}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </td>
                            <td className="px-4 py-3 text-right">
                                <div className="flex flex-col justify-end gap-2 items-end">
                                    <Link to={`/admin/questions/${q.id}/edit`} className="w-full text-center px-3 py-1.5 bg-blue-100 text-blue-700 text-[10px] rounded-lg hover:bg-blue-200 font-black uppercase tracking-tighter">
                                        ✏️ Editar Questão
                                    </Link>
                                    <button
                                        onClick={() => {
                                            if (window.confirm('Marcar todos os relatos como resolvidos sem desativar a questão?')) {
                                                if (q.reports.length > 0) {
                                                    reportActions.mutate({ id: q.reports[0].id, action: 'resolve' });
                                                }
                                            }
                                        }}
                                        className="w-full text-center px-3 py-1.5 bg-green-100 text-green-700 text-[10px] rounded-lg hover:bg-green-200 font-black uppercase tracking-tighter"
                                    >
                                        ✅ Descartar e Resolver
                                    </button>
                                    <button
                                        onClick={() => {
                                            if (window.confirm('ATENÇÃO: Isso vai ocultar a questão dos simulados e alunos, e resolver as denúncias pendentes. Confirmar?')) {
                                                reportActions.mutate({ id: q.id, action: 'deactivate' });
                                            }
                                        }}
                                        className="w-full text-center px-3 py-1.5 bg-red-100 text-red-700 text-[10px] rounded-lg hover:bg-red-200 font-black uppercase tracking-tighter"
                                    >
                                        🚫 Desativar Questão
                                    </button>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export function TrashedQuestionsTable({ trashedData, trashedActions }: any) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-left">
                <thead>
                    <tr className="bg-gray-50/80">
                        <th className="px-4 py-3 text-[10px] font-black text-gray-400 uppercase w-16">ID</th>
                        <th className="px-4 py-3 text-[10px] font-black text-gray-400 uppercase">Questão</th>
                        <th className="px-4 py-3 text-[10px] font-black text-gray-400 uppercase w-32">Matéria</th>
                        <th className="px-4 py-3 text-[10px] font-black text-gray-400 uppercase w-48 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                    {(!trashedData?.questions?.data || trashedData.questions.data.length === 0) && (
                        <tr><td colSpan={4} className="p-8 text-center text-gray-400 font-bold">A lixeira está vazia.</td></tr>
                    )}
                    {trashedData?.questions?.data?.map((q: any) => (
                        <tr key={q.id} className="hover:bg-gray-50/50 transition opacity-80">
                            <td className="px-4 py-4 text-xs font-mono font-bold text-gray-400">#{q.id}</td>
                            <td className="px-4 py-4">
                                <div className="text-sm text-gray-600 line-clamp-2" dangerouslySetInnerHTML={{ __html: q.statement }}></div>
                                <div className="mt-1 flex items-center gap-2">
                                    <span className="text-[10px] px-2 py-0.5 bg-red-50 text-red-500 rounded font-bold uppercase">Deletada em {new Date(q.deleted_at).toLocaleDateString()}</span>
                                </div>
                            </td>
                            <td className="px-4 py-4">
                                <span className="text-[10px] font-black bg-gray-100 text-gray-500 px-2 py-1 rounded uppercase tracking-tighter truncate max-w-[120px] inline-block">
                                    {q.subjects?.[0]?.name || 'N/A'}
                                </span>
                            </td>
                            <td className="px-4 py-4">
                                <div className="flex items-center gap-2 justify-end">
                                    <button
                                        onClick={() => {
                                            if (window.confirm('Restaurar esta questão de volta ao banco?')) {
                                                trashedActions.mutate({ id: q.id, action: 'restore' });
                                            }
                                        }}
                                        disabled={trashedActions.isPending}
                                        className="px-3 py-1.5 bg-green-50 text-green-700 text-[10px] font-bold rounded hover:bg-green-100"
                                    >
                                        Restaurar
                                    </button>
                                    <button
                                        onClick={() => {
                                            if (window.confirm('ATENÇÃO: ATITUDE DESTRUTIVA!\nIso removerá a questão permanentemente do banco, perdendo inclusive respostas em simulados conectadas a ela.\n\nTem certeza absoluta?')) {
                                                trashedActions.mutate({ id: q.id, action: 'force' });
                                            }
                                        }}
                                        disabled={trashedActions.isPending}
                                        className="px-3 py-1.5 bg-red-600 text-white text-[10px] font-bold rounded hover:bg-red-700 flex items-center gap-1"
                                    >
                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        Purgar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
