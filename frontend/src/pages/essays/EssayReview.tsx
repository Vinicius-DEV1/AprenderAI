import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getEssay } from '../../api/essays';

export default function EssayReview() {
    const { id } = useParams<{ id: string }>();
    const [tab, setTab] = useState<'general' | 'points' | 'corrections' | 'improved'>('general');
    const [competenciesOpen, setCompetenciesOpen] = useState(false);

    const { data: response, isLoading, isError } = useQuery({
        queryKey: ['essay', id],
        queryFn: () => getEssay(id!),
        refetchInterval: (query) => {
            const status = query.state.data?.data?.status;
            return status === 'evaluating' ? 5000 : false;
        },
        enabled: !!id
    });

    if (isLoading) {
        return (
            <div className="flex justify-center py-20">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            </div>
        );
    }

    if (isError || !response?.data) {
        return (
            <div className="flex justify-center py-20 text-red-600">
                Erro ao carregar os dados da redação.
            </div>
        );
    }

    const essay = response.data;
    const feedback = essay.feedback_json || {};

    // Status Classes & Message
    let statusClasses = 'bg-gray-100 text-gray-800 border-gray-200';
    let statusMessage = essay.status;

    if (essay.status === 'completed') {
        statusClasses = 'bg-green-100 text-green-800 border-green-200';
        statusMessage = 'Redação Corrigida';
    } else if (essay.status === 'evaluating') {
        statusClasses = 'bg-purple-100 text-purple-800 border-purple-200 animate-pulse';
        statusMessage = 'Avaliando... A inteligência artificial está lendo seu texto.';
    } else if (essay.status === 'error') {
        statusClasses = 'bg-red-100 text-red-800 border-red-200';
        statusMessage = 'Erro na Correção';
    } else if (['pending', 'in_progress'].includes(essay.status)) {
        statusClasses = 'bg-yellow-100 text-yellow-800 border-yellow-200';
        statusMessage = 'Aguardando Avaliação';
    }

    // Competencies Logic
    const renderCompetencies = () => {
        if (essay.status !== 'completed') return null;

        const essayType = essay.type;
        const totalScore = parseInt(essay.score || '0');
        const aiCompetencies = feedback.competencies;

        let competencies: any[] = [];
        // let maxTotal = essayType === 'enem' ? 1000 : 100;

        const defaultLabelsEnem = {
            'C1': 'Domínio da Norma Culta',
            'C2': 'Compreensão do Tema e Repertório',
            'C3': 'Argumentação',
            'C4': 'Coesão e Coerência',
            'C5': 'Proposta de Intervenção',
        };

        const defaultLabelsConcurso = {
            'C1': 'Domínio da Norma Culta',
            'C2': 'Clareza Argumentativa',
            'C3': 'Estrutura Textual',
            'C4': 'Adequação ao Tema',
            'C5': 'Objetividade',
        };

        const defaultLabels = essayType === 'enem' ? defaultLabelsEnem : defaultLabelsConcurso;
        const isAiObject = aiCompetencies && typeof aiCompetencies === 'object' && !Array.isArray(aiCompetencies);

        if (isAiObject) {
            competencies = Object.keys(defaultLabels).map(code => {
                const key = code.toLowerCase();
                const c = aiCompetencies[key] || {};
                return {
                    code,
                    label: (defaultLabels as any)[code],
                    score: Math.min(essayType === 'enem' ? 200 : 20, Math.max(0, parseInt(c.score || 0))),
                    max: essayType === 'enem' ? 200 : 20,
                    justification: c.justification || c.comment || '',
                };
            });
        } else {
            // Fallback
            const base = Math.floor(totalScore / 5);
            const remainder = totalScore % 5;
            competencies = Object.keys(defaultLabels).map((code, idx) => ({
                code,
                label: (defaultLabels as any)[code],
                score: Math.min(essayType === 'enem' ? 200 : 20, base + (idx < remainder ? 1 : 0)),
                max: essayType === 'enem' ? 200 : 20,
                justification: '',
            }));
        }

        return (
            <div className="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg mb-6 border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div className="flex items-center justify-between px-6 py-4">
                    <span className="text-sm font-semibold text-gray-700 dark:text-gray-200 tracking-wide">
                        Nota por Competência
                    </span>
                    <button
                        type="button"
                        onClick={() => setCompetenciesOpen(!competenciesOpen)}
                        className="text-xs font-medium text-blue-500 hover:text-blue-600 dark:text-blue-400 dark:hover:text-blue-500 transition-colors duration-150 cursor-pointer select-none focus:outline-none"
                    >
                        {competenciesOpen ? 'ver menos' : 'ver mais'}
                    </button>
                </div>

                {competenciesOpen && (
                    <div className="border-t border-gray-100 dark:border-gray-700 px-6 py-4 space-y-3 animate-fade-in-up">
                        {competencies.map((comp, idx) => (
                            <div key={idx} className="flex items-start gap-3">
                                <div className="flex-shrink-0 flex items-center gap-2 min-w-[110px]">
                                    <span className="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">
                                        {comp.code}
                                    </span>
                                    <span className="inline-flex items-center bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-xs font-semibold px-2 py-0.5 rounded-full">
                                        {comp.score}<span className="text-blue-300 dark:text-blue-600 font-normal">/{comp.max}</span>
                                    </span>
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="text-xs font-semibold text-gray-700 dark:text-gray-200 leading-snug">
                                        {comp.label}
                                    </p>
                                    {comp.justification && (
                                        <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 leading-relaxed">
                                            {comp.justification}
                                        </p>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        );
    };

    return (
        <div className="py-12">
            <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">

                {/* Status Alert */}
                <div className={`mb-6 p-4 rounded-lg text-center font-bold text-lg border ${statusClasses}`}>
                    {statusMessage}
                </div>

                <div className="mb-4 text-center">
                    <Link to="/essays" className="inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                        &larr; Voltar para minhas redações
                    </Link>
                </div>

                {essay.status === 'completed' && (
                    <>
                        {/* Score Card */}
                        <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                            <div className="p-6 text-gray-900 dark:text-gray-100 text-center">
                                <h3 className="text-sm uppercase tracking-widest text-gray-500 font-bold mb-2">Nota Xavier</h3>
                                <div className="text-6xl font-extrabold text-blue-600 dark:text-blue-400">
                                    {essay.score || 0}
                                    <span className="text-2xl text-gray-400 font-normal">
                                        /{essay.type === 'enem' ? '1000' : '100'}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Competencies */}
                        {renderCompetencies()}

                        {/* Tabs Navigation */}
                        <div className="flex space-x-2 mb-6 overflow-x-auto pb-2">
                            {[
                                { id: 'general', label: 'Resumo' },
                                { id: 'points', label: 'Pontos Fortes/Fracos' },
                                { id: 'corrections', label: 'Correções' },
                                { id: 'improved', label: 'Versão Melhorada' }
                            ].map(t => (
                                <button
                                    key={t.id}
                                    onClick={() => setTab(t.id as any)}
                                    className={`px-4 py-2 rounded-md font-bold shadow transition whitespace-nowrap ${tab === t.id
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-white text-gray-700 dark:bg-gray-700 dark:text-gray-300'
                                        }`}
                                >
                                    {t.label}
                                </button>
                            ))}
                        </div>

                        {/* Tabs Content */}
                        <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg min-h-[400px] mb-6 animate-fade-in">
                            <div className="p-6 text-gray-900 dark:text-gray-100">
                                {/* General */}
                                {tab === 'general' && (
                                    <div>
                                        <h3 className="text-xl font-bold mb-4">Resumo da Avaliação</h3>
                                        <p className="mb-4 text-lg leading-relaxed dark:text-gray-300">
                                            {feedback.summary || essay.feedback || 'Sem resumo disponível.'}
                                        </p>

                                        {feedback.checklist && (
                                            <>
                                                <h4 className="font-bold mt-6 mb-2">Checklist Rápido</h4>
                                                <ul className="space-y-2">
                                                    {feedback.checklist.map((item: any, idx: number) => (
                                                        <li key={idx} className="flex items-center dark:text-gray-300">
                                                            {String(item.status).toLowerCase() === 'ok' ? (
                                                                <span className="text-green-500 mr-2">✔</span>
                                                            ) : (
                                                                <span className="text-yellow-500 mr-2">⚠</span>
                                                            )}
                                                            <span className="font-semibold mr-2">{item.item}:</span> {item.status}
                                                        </li>
                                                    ))}
                                                </ul>
                                            </>
                                        )}
                                    </div>
                                )}

                                {/* Points */}
                                {tab === 'points' && (
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <h3 className="text-green-600 dark:text-green-400 font-bold text-lg mb-3">Pontos Fortes</h3>
                                            <ul className="list-disc pl-5 space-y-1 dark:text-gray-300">
                                                {(feedback.strengths || []).map((s: string, i: number) => <li key={i}>{s}</li>)}
                                                {!(feedback.strengths?.length > 0) && <li className="text-gray-500 italic">Nenhum ponto forte destacado.</li>}
                                            </ul>
                                        </div>
                                        <div>
                                            <h3 className="text-red-500 dark:text-red-400 font-bold text-lg mb-3">A Melhorar</h3>
                                            <ul className="list-disc pl-5 space-y-1 dark:text-gray-300">
                                                {(feedback.weaknesses || []).map((w: string, i: number) => <li key={i}>{w}</li>)}
                                                {!(feedback.weaknesses?.length > 0) && <li className="text-gray-500 italic">Nenhum ponto de melhoria destacado.</li>}
                                            </ul>
                                        </div>
                                    </div>
                                )}

                                {/* Corrections */}
                                {tab === 'corrections' && (
                                    <div>
                                        <h3 className="text-xl font-bold mb-4">Correções Pontuais</h3>
                                        <div className="space-y-4">
                                            {(feedback.corrections || []).map((c: any, i: number) => (
                                                <div key={i} className="border-l-4 border-yellow-400 pl-4 py-2 bg-gray-50 dark:bg-gray-700 dark:border-yellow-500 rounded-r">
                                                    <p className="font-mono text-sm text-red-600 dark:text-red-400 mb-1">"{c.excerpt || 'Trecho'}"</p>
                                                    <p className="font-bold text-gray-800 dark:text-gray-200">{c.issue || 'Problema'}</p>
                                                    <p className="text-green-600 dark:text-green-400 italic mt-1">Sugestão: {c.suggestion || ''}</p>
                                                </div>
                                            ))}
                                            {!(feedback.corrections?.length > 0) && <p className="text-gray-500 italic">Nenhuma correção pontual destacada.</p>}
                                        </div>
                                    </div>
                                )}

                                {/* Improved Version */}
                                {tab === 'improved' && (
                                    <div>
                                        <h3 className="text-xl font-bold mb-4">Versão Sugerida por Xavier</h3>
                                        <div className="prose dark:prose-invert max-w-none bg-gray-50 dark:bg-gray-900 p-6 rounded-lg border border-gray-200 dark:border-gray-700 whitespace-pre-line">
                                            {feedback.improved_version || 'Versão melhorada indisponível.'}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </>
                )}

                {/* Original Text */}
                <div className="mt-8 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div className="p-6">
                        <h3 className="font-bold text-gray-500 uppercase tracking-widest text-sm mb-4">Seu Texto Original</h3>
                        <div className="prose dark:prose-invert max-w-none whitespace-pre-line text-gray-700 dark:text-gray-300 font-serif text-lg leading-relaxed">
                            {essay.content}
                        </div>
                    </div>
                </div>

            </div>
        </div>
    );
}
