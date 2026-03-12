import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useNavigate, Link, useSearchParams } from 'react-router-dom';
import api from '../../api/axios';
import { toast } from 'sonner';
import Cropper from 'react-cropper';
import 'cropperjs/dist/cropper.css';
import { renderMd } from '../../utils/markdown';

export default function ImportReview() {
    const { id } = useParams();
    const navigate = useNavigate();
    const [activeTarget, setActiveTarget] = useState<string>('statement');
    const [saving, setSaving] = useState(false);
    const [cropper, setCropper] = useState<any>();
    const [searchParams] = useSearchParams();
    const queryClient = useQueryClient();

    const { data, isLoading } = useQuery({
        queryKey: ['admin-import-review', id, Object.fromEntries(searchParams)],
        queryFn: async () => {
            const res = await api.get(`/api/v1/admin/import/review/${id}`, { params: Object.fromEntries(searchParams) });
            return res.data;
        }
    });

    const { data: historyData } = useQuery({
        queryKey: ['admin-import-question-history', id],
        queryFn: async () => {
            const res = await api.get(`/api/v1/admin/questions/${id}/triage-history`);
            return res.data;
        }
    });

    const approveMutation = useMutation({
        mutationFn: async () => {
            const res = await api.post(`/api/v1/admin/import/review/${id}/approve`, Object.fromEntries(searchParams));
            return res.data;
        },
        onSuccess: (data) => {
            if (data.next_id) {
                navigate(`/admin/import/review/${data.next_id}?${searchParams.toString()}`);
                toast.success('Questão aprovada! Indo para a próxima...');
            } else {
                navigate(`/admin/import/review?${searchParams.toString()}`);
                toast.success('Questão aprovada! Fila concluída.');
            }
        },
        onError: () => toast.error('Falha ao aprovar questão.')
    });

    const revertMutation = useMutation({
        mutationFn: async () => {
            return await api.post(`/api/v1/admin/import/review/${id}/revert`);
        },
        onSuccess: () => {
            toast.success('Questão retornada para revisão.');
            navigate(`/admin/import/review?${searchParams.toString()}`);
        },
        onError: () => toast.error('Falha ao reverter questão.')
    });

    const deleteImageMutation = useMutation({
        mutationFn: async (imageId: number) => {
            return await api.delete(`/api/v1/admin/import/review/${imageId}/image`);
        },
        onSuccess: () => {
            toast.success('Imagem removida.');
            queryClient.invalidateQueries({ queryKey: ['admin-import-review', id] });
        }
    });

    const handleSaveCrop = async () => {
        if (!cropper) return;

        const imageData = cropper.getData(true);
        const currentImage = question.images?.[0] || { id: null };

        if (!currentImage.id) {
            toast.error('Nenhuma imagem encontrada para recortar.');
            return;
        }

        setSaving(true);
        try {
            await api.post(`/api/v1/admin/import/review/${currentImage.id}/crop`, {
                target: activeTarget,
                x: imageData.x,
                y: imageData.y,
                width: imageData.width,
                height: imageData.height
            });
            toast.success('Recorte salvo com sucesso!');

            if (activeTarget !== 'statement') {
                const nextMap: any = { 'A': 'B', 'B': 'C', 'C': 'D', 'D': 'E', 'E': 'E' };
                setActiveTarget(nextMap[activeTarget] || 'A');
            }

            queryClient.invalidateQueries({ queryKey: ['admin-import-review', id] });
        } catch (error) {
            toast.error('Erro ao salvar recorte.');
        } finally {
            setSaving(false);
        }
    };

    const apiUrl = import.meta.env.VITE_API_BASE_URL || (import.meta.env.PROD ? '' : 'http://localhost:8000');


    if (isLoading) return <div className="p-8">Carregando revisão...</div>;
    if (!data?.question) return <div className="p-8 text-red-500">Questão não encontrada.</div>;

    const { question, importItem } = data;

    const hasImageModels = question?.images?.length > 0;
    const hasActiveEditor = hasImageModels;
    const latestAILog = historyData?.find((log: any) => log.triage_type === 'ai_batch');

    const renderActions = (isCompact = false) => (
        <div className={`space-y-3 ${isCompact ? '' : 'p-5 bg-white rounded-xl shadow-sm border border-gray-100'}`}>
            {!isCompact && (
                <h3 className="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                    <span className="w-1 h-1 bg-indigo-400 rounded-full"></span>
                    Painel de Controle
                </h3>
            )}
            {question.review_status === 'pending' || question.review_status === 'review' ? (
                <button
                    onClick={() => approveMutation.mutate()}
                    disabled={approveMutation.isPending}
                    className="w-full px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-bold text-sm flex items-center justify-center gap-2 shadow-sm transition-all active:scale-[0.98]">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    {approveMutation.isPending ? 'Aprovando...' : 'Aprovar e Publicar'}
                </button>
            ) : (
                <button
                    onClick={() => revertMutation.mutate()}
                    disabled={revertMutation.isPending}
                    className="w-full px-4 py-3 bg-orange-500 text-white rounded-lg hover:bg-orange-600 font-bold text-sm flex items-center justify-center gap-2 shadow-sm transition-all active:scale-[0.98]">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                    Retornar para Revisão
                </button>
            )}

            <div className="grid grid-cols-2 gap-2">
                <Link
                    to={`/admin/questions/${question.id}/edit`}
                    className="px-3 py-2 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 font-semibold text-[11px] flex items-center justify-center gap-1.5 transition-colors"
                >
                    ✍️ Editar
                </Link>
                <Link
                    to={`/admin/import/review?${searchParams.toString()}`}
                    className="px-3 py-2 bg-gray-50 text-gray-500 rounded-lg text-[11px] font-semibold text-center hover:bg-gray-100 transition-colors"
                >
                    ⬅️ Sair
                </Link>
            </div>

            {/* Navigation Buttons */}
            <div className="grid grid-cols-2 gap-2 mt-2">
                <button
                    disabled={!data?.prev_id}
                    onClick={() => navigate(`/admin/import/review/${data.prev_id}?${searchParams.toString()}`)}
                    className="px-3 py-2 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed font-bold text-[11px] flex items-center justify-center gap-1.5 transition-colors"
                >
                    ◄ Ant.
                </button>
                <button
                    disabled={!data?.next_id}
                    onClick={() => navigate(`/admin/import/review/${data.next_id}?${searchParams.toString()}`)}
                    className="px-3 py-2 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed font-bold text-[11px] flex items-center justify-center gap-1.5 transition-colors"
                >
                    Próx. ►
                </button>
            </div>

            {/* View Full Exam Button */}
            {(() => {
                const idParam = question.arquivo_origem ? btoa(question.arquivo_origem) : 'null';
                const examParams = new URLSearchParams();
                if (!question.arquivo_origem) {
                    if (question.year) examParams.append('year', question.year.toString());
                    if (question.organization) examParams.append('organization', question.organization);
                    if (question.institution) examParams.append('institution', question.institution);
                    if (question.role) examParams.append('role', question.role);
                }
                const examUrl = `/admin/provas/${idParam}${examParams.toString() ? '?' + examParams.toString() : ''}`;

                return (
                    <Link
                        to={examUrl}
                        target="_blank"
                        className="w-full px-4 py-2 border border-indigo-200 text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 font-bold text-[11px] flex items-center justify-center gap-2 transition-all mt-2"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        📄 Ver Prova Completa
                    </Link>
                );
            })()}
        </div>
    );

    return (
        <div className="py-4 px-2 md:px-4 w-full">
            <div className="w-full">
                {/* Header */}
                <div className="flex items-center gap-3 mb-4">
                    <Link to={`/admin/import/review?${searchParams.toString()}`} className="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7"></path></svg>
                    </Link>
                    <h2 className="font-bold text-lg text-gray-800 leading-tight">
                        Questão #{question.id}
                    </h2>
                    <span className={`px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider ${question.review_status === 'pending' || question.review_status === 'review' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'}`}>
                        {question.review_status === 'pending' || question.review_status === 'review' ? 'Em Revisão' : 'Aprovada'}
                    </span>
                </div>

                {/* Conditional Layout Grid */}
                <div className={`grid grid-cols-1 ${hasActiveEditor ? 'lg:grid-cols-2' : 'lg:grid-cols-[1fr_350px]'} gap-4 items-start`}>

                    {/* Left Column: Data Review */}
                    <div className="space-y-3">
                        {/* Metadados Card */}
                        <div className="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
                            <h3 className="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                <span className="w-1 h-1 bg-indigo-400 rounded-full"></span>
                                Metadados
                            </h3>
                            <div className="grid grid-cols-2 gap-x-4 gap-y-2 text-[11px]">
                                {question.organization && (
                                    <div className="flex flex-col"><dt className="text-gray-400 font-bold uppercase text-[9px]">Banca</dt><dd className="font-bold text-gray-800">{question.organization}</dd></div>
                                )}
                                {question.institution && (
                                    <div className="flex flex-col"><dt className="text-gray-400 font-bold uppercase text-[9px]">Órgão</dt><dd className="text-gray-700 font-medium">{question.institution}</dd></div>
                                )}
                                {question.role && (
                                    <div className="flex flex-col"><dt className="text-gray-400 font-bold uppercase text-[9px]">Cargo</dt><dd className="text-gray-700 font-medium truncate">{question.role}</dd></div>
                                )}
                                {question.year && (
                                    <div className="flex flex-col"><dt className="text-gray-400 font-bold uppercase text-[9px]">Ano</dt><dd className="text-gray-700 font-medium">{question.year}</dd></div>
                                )}
                                {question.subjects?.length > 0 && (
                                    <div className="flex flex-col col-span-2"><dt className="text-gray-400 font-bold uppercase text-[9px] mb-1">Matérias</dt>
                                        <dd className="flex flex-wrap gap-1">
                                            {question.subjects.map((s: any) => (
                                                <span key={s.id || s.name} className="px-1.5 py-0.5 bg-green-50 text-green-700 text-[10px] rounded font-bold border border-green-100">{s.name}</span>
                                            ))}
                                        </dd>
                                    </div>
                                )}
                                {importItem && (
                                    <div className="flex flex-col col-span-2 border-t border-gray-50 pt-2 mt-1">
                                        <dt className="text-gray-400 font-bold uppercase text-[9px]">Lote</dt>
                                        <dd className="text-gray-500 font-medium italic truncate">{importItem.import?.batch_name ?? '—'}</dd>
                                    </div>
                                )}
                                {latestAILog && (
                                    <div className="flex flex-col col-span-2 border-t border-gray-100 pt-3 mt-2">
                                        <div className="flex justify-between items-center mb-1.5">
                                            <dt className="text-indigo-500 font-bold uppercase text-[10px] flex items-center gap-1.5 tracking-wider">
                                                <span>🤖</span> Análise da IA
                                            </dt>
                                            {latestAILog.quality_score !== null && (
                                                <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${latestAILog.quality_score >= 80 ? 'bg-green-100 text-green-700' : latestAILog.quality_score >= 60 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700'}`}>
                                                    Score: {latestAILog.quality_score}/100
                                                </span>
                                            )}
                                        </div>
                                        <dd className="flex flex-wrap gap-1 mt-1">
                                            {latestAILog.issues_detected?.length > 0 ? (
                                                latestAILog.issues_detected.map((iss: string) => (
                                                    <span key={iss} className="px-1.5 py-0.5 bg-red-50 text-red-700 text-[10px] uppercase font-bold tracking-wider rounded border border-red-100 flex items-center gap-1">
                                                        <span>🚫</span> {iss.replace(/_/g, ' ')}
                                                    </span>
                                                ))
                                            ) : (
                                                <span className="px-1.5 py-0.5 bg-green-50 text-green-700 text-[10px] uppercase font-bold tracking-wider rounded border border-green-100 flex items-center gap-1">
                                                    <span>✅</span> Sem problemas
                                                </span>
                                            )}
                                        </dd>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Statement Card */}
                        <div className="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
                            <h3 className="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                <span className="w-1 h-1 bg-indigo-400 rounded-full"></span>
                                Enunciado
                            </h3>
                            <div className="prose prose-indigo max-w-none text-gray-800 text-[13px] leading-relaxed bg-slate-50 p-3 rounded-lg border border-slate-100 dark:text-slate-300" dangerouslySetInnerHTML={renderMd(question.statement)} />

                            {hasImageModels ? (
                                <div className="mt-4 pt-4 border-t border-gray-100">
                                    <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">🖼️ Imagens do Enunciado</p>
                                    <div className="space-y-3">
                                        {question.images.map((img: any) => (
                                            <img key={img.id} src={img.url || (img.path?.startsWith('http') ? img.path : `${apiUrl}/storage/${img.path.replace('storage/', '')}`)} alt="Imagem do Enunciado" className="max-w-full h-auto rounded border border-gray-200" />
                                        ))}
                                    </div>
                                </div>
                            ) : question.image_path ? (
                                <div className="mt-4 pt-4 border-t border-gray-100">
                                    <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">🖼️ Imagem do Enunciado</p>
                                    <img src={question.image_path.startsWith('http') ? question.image_path : `${apiUrl}/storage/${question.image_path.replace('storage/', '')}`} alt="Imagem do Enunciado" className="max-w-full h-auto rounded border border-gray-200" />
                                </div>
                            ) : null}
                        </div>

                        {/* Alternatives Card */}
                        <div className="bg-white rounded-xl shadow-sm p-4 border border-gray-100" id="alternatives-card">
                            <h3 className="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                <span className="w-1 h-1 bg-indigo-400 rounded-full"></span>
                                Alternativas
                            </h3>
                            {question.alternatives?.length > 0 ? (
                                <div className="space-y-3">
                                    {question.alternatives.sort((a: any, b: any) => a.label.localeCompare(b.label)).map((alt: any) => (
                                        <div key={alt.id || alt.label} className={`flex gap-3 p-3 rounded-lg ${alt.is_correct ? 'bg-green-50 border border-green-200' : 'bg-gray-50'}`}>
                                            <span className={`font-bold text-sm w-6 flex-shrink-0 ${alt.is_correct ? 'text-green-700' : 'text-gray-500'}`}>
                                                {alt.label})
                                            </span>
                                            <div className="flex-1 min-w-0">
                                                {(alt.content?.startsWith('questions_images/') || alt.content?.startsWith('storage/')) ? (
                                                    <img src={alt.content.startsWith('http') ? alt.content : `${apiUrl}/storage/${alt.content.replace('storage/', '')}`} alt={`Alternativa ${alt.label}`} className="max-w-full h-auto rounded border border-gray-200" />
                                                ) : (
                                                    <div className="prose prose-indigo max-w-none text-sm text-gray-700 alternatives-markdown dark:text-slate-300" dangerouslySetInnerHTML={renderMd(alt.content)} />
                                                )}
                                            </div>
                                            {alt.is_correct && (
                                                <span className="text-green-600 text-xs font-semibold flex-shrink-0">✓ Gabarito</span>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="bg-orange-50 border border-orange-200 rounded-lg p-4 text-center">
                                    <p className="text-orange-700 text-sm font-medium">⚠️ Nenhuma alternativa textual</p>
                                    <p className="text-orange-600 text-xs mt-1">Use o editor de crop ao lado para recortar as alternativas da imagem.</p>
                                </div>
                            )}
                        </div>

                        {/* Mobile Actions for Text-Only */}
                        {!hasActiveEditor && (
                            <div className="lg:hidden">
                                {renderActions()}
                            </div>
                        )}

                        {/* History Card */}
                        {historyData && historyData.length > 0 && (
                            <div className="bg-white rounded-lg shadow-sm p-5 space-y-4">
                                <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wide flex items-center gap-2">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Histórico de Triagem
                                </h3>
                                <div className="space-y-4 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-slate-200 before:to-transparent">
                                    {historyData.map((log: any) => (
                                        <div key={log.id} className="relative flex items-start gap-3">
                                            <div className="flex items-center justify-center w-8 h-8 rounded-full border-2 border-white bg-indigo-50 text-indigo-600 z-10 shrink-0 ring-1 ring-slate-200">
                                                {log.triage_type === 'ai_batch' ? '🤖' : '👤'}
                                            </div>
                                            <div className="bg-slate-50 border border-slate-100 p-3 rounded-lg flex-1 min-w-0 shadow-sm text-sm">
                                                <div className="flex justify-between items-start mb-1 gap-2">
                                                    <div className="font-semibold text-slate-800 break-words">
                                                        {log.status === 'approved' ? (
                                                            <span className="text-green-600">✅ Aprovado</span>
                                                        ) : (
                                                            <span className="text-orange-600">⚠️ Status: Revisão</span>
                                                        )}
                                                    </div>
                                                    <div className="text-[10px] text-slate-400 font-medium whitespace-nowrap shrink-0">
                                                        {new Date(log.created_at).toLocaleString()}
                                                    </div>
                                                </div>
                                                <div className="text-slate-600 text-xs mb-2">
                                                    Por: <span className="font-medium">{log.processed_by === 'system' ? 'IA Batch Triage' : log.processed_by}</span>
                                                </div>
                                                {log.issues_detected && log.issues_detected.length > 0 && (
                                                    <div className="flex flex-wrap gap-1 mt-2">
                                                        {log.issues_detected.map((iss: string) => (
                                                            <span key={iss} className="px-2 py-0.5 bg-red-100 text-red-700 text-[10px] uppercase font-bold tracking-wider rounded">
                                                                🚫 {iss.replace(/_/g, ' ')}
                                                            </span>
                                                        ))}
                                                    </div>
                                                )}
                                                {log.quality_score !== null && (
                                                    <div className="mt-2 text-xs">
                                                        <span className="font-semibold text-slate-500">Qualidade Pedagógica:</span>
                                                        <span className={`ml-1 font-bold ${log.quality_score >= 80 ? 'text-green-600' : log.quality_score >= 60 ? 'text-amber-600' : 'text-red-600'}`}>
                                                            {log.quality_score}/100
                                                        </span>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Right Column: Editor OR Control Panel */}
                    <div>
                        {hasActiveEditor ? (
                            <div className="space-y-6">
                                {question.images.map((img: any) => (
                                    <div key={img.id} className="bg-white rounded-lg shadow-sm overflow-hidden">
                                        <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                                            <div>
                                                <h3 className="text-sm font-semibold text-gray-800">🖼️ Editor de Recorte (ID: {img.id})</h3>
                                                <p className="text-xs text-gray-500 mt-0.5">Recorte para o <strong>Enunciado</strong> ou para uma <strong>Alternativa</strong>.</p>
                                            </div>
                                            <button
                                                onClick={() => {
                                                    if (window.confirm('Remover esta imagem?')) deleteImageMutation.mutate(img.id);
                                                }}
                                                className="px-3 py-1.5 bg-red-100 text-red-700 text-xs rounded-md hover:bg-red-200 font-medium">
                                                🗑️ Remover
                                            </button>
                                        </div>

                                        <div className="p-0 bg-gray-900 flex justify-center overflow-hidden">
                                            <Cropper
                                                src={img.url || (img.path?.startsWith('http') ? img.path : `${apiUrl}/storage/${img.path.replace('storage/', '')}`)}
                                                style={{ height: 'auto', width: '100%', maxHeight: '600px' }}
                                                initialAspectRatio={undefined}
                                                guides={true}
                                                viewMode={1}
                                                dragMode="move"
                                                autoCropArea={0.5}
                                                onInitialized={(instance: any) => setCropper(instance)}
                                            />
                                        </div>

                                        <div className="p-5 border-t border-gray-100 space-y-4">
                                            <div className={`rounded-lg border-2 p-3 transition-colors ${activeTarget === 'statement' ? 'border-blue-400 bg-blue-50' : 'border-gray-200 bg-gray-50'}`}>
                                                <div className="flex items-center justify-between gap-3">
                                                    <div>
                                                        <p className="text-xs font-semibold uppercase tracking-wide text-blue-700">📄 Enunciado</p>
                                                        <p className="text-xs text-gray-400 mt-0.5">Recorte substitui a imagem do enunciado.</p>
                                                    </div>
                                                    <button
                                                        onClick={() => { setActiveTarget('statement'); handleSaveCrop(); }}
                                                        disabled={saving}
                                                        className="flex-shrink-0 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-bold text-xs flex items-center gap-2 transition-opacity disabled:opacity-50">
                                                        {saving && activeTarget === 'statement' ? 'Salvando...' : '✂️ Confirmar Recorte'}
                                                    </button>
                                                </div>
                                            </div>

                                            <div className={`rounded-lg border-2 p-3 transition-colors ${activeTarget !== 'statement' ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 bg-gray-50'}`}>
                                                <p className="text-xs font-semibold uppercase tracking-wide text-indigo-700 mb-2">🔤 Alternativa Visual</p>
                                                <div className="flex flex-wrap items-center gap-3">
                                                    <div className="flex items-center gap-1.5">
                                                        {['A', 'B', 'C', 'D', 'E'].map(ltr => (
                                                            <button
                                                                key={ltr}
                                                                onClick={() => setActiveTarget(ltr)}
                                                                className={`w-8 h-8 rounded-md font-bold text-xs transition-all ${activeTarget === ltr ? 'bg-indigo-600 text-white shadow-md' : 'bg-white text-gray-500 border border-gray-200 hover:bg-gray-50'}`}>
                                                                {ltr}
                                                            </button>
                                                        ))}
                                                    </div>
                                                    <button
                                                        onClick={() => handleSaveCrop()}
                                                        disabled={saving || activeTarget === 'statement'}
                                                        className="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 font-bold text-xs transition-opacity disabled:opacity-50">
                                                        {saving && activeTarget !== 'statement' ? 'Salvando...' : `Salvar Alt. ${activeTarget}`}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))}

                                {/* Sticky Actions for Editor */}
                                <div className="sticky top-4 z-20">
                                    {renderActions()}
                                </div>
                            </div>
                        ) : (
                            /* Sticky Control Panel for Text-Only */
                            <div className="sticky top-4">
                                {renderActions()}
                            </div>
                        )}
                    </div>

                </div>
            </div>
        </div>
    );
}
