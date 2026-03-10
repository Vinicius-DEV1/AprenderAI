import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useNavigate, Link } from 'react-router-dom';
import api from '../../api/axios';
import { toast } from 'sonner';
import Cropper from 'react-cropper';
import 'cropperjs/dist/cropper.css';
import ReactMarkdown from 'react-markdown';

export default function ImportReview() {
    const { id } = useParams();
    const navigate = useNavigate();
    const [activeTarget, setActiveTarget] = useState<string>('statement');
    const [saving, setSaving] = useState(false);
    const [cropper, setCropper] = useState<any>();
    const queryClient = useQueryClient();

    const { data, isLoading } = useQuery({
        queryKey: ['admin-import-review', id],
        queryFn: async () => {
            const res = await api.get(`/api/v1/admin/import/review/${id}`);
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
            return await api.post(`/api/v1/admin/import/review/${id}/approve`);
        },
        onSuccess: () => navigate('/admin/import/review')
    });

    const revertMutation = useMutation({
        mutationFn: async () => {
            return await api.post(`/api/v1/admin/import/review/${id}/revert`);
        },
        onSuccess: () => navigate('/admin/import/review')
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

    const urlTransform = (uri: string) => {
        if (uri.startsWith('/storage')) {
            return `${apiUrl}${uri}`;
        }
        return uri;
    };

    if (isLoading) return <div className="p-8">Carregando revisão...</div>;
    if (!data?.question) return <div className="p-8 text-red-500">Questão não encontrada.</div>;

    const { question, importItem } = data;

    const hasImageModels = question?.images?.length > 0;
    const hasEmbeddedImages = /<img|!\[.*?\]\(.*?\)/i.test(question?.statement || '') ||
        /<img/i.test(question?.statement_html || '') ||
        question?.image_path ||
        question?.alternatives?.some((a: any) => /<img|!\[.*?\]\(.*?\)/i.test(a.content || '') || a.image_path);

    return (
        <div className="py-6 px-4 md:px-6 w-full">
            <div className="w-full">
                <div className="flex items-center gap-4 mb-6">
                    <Link to="/admin/import/review" className="text-gray-400 hover:text-gray-600">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7"></path></svg>
                    </Link>
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                        Questão #{question.id} — Inspeção Visual
                    </h2>
                    <span className={`px-3 py-1 rounded-full text-sm font-medium ${question.review_status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'}`}>
                        {question.review_status === 'pending' ? '⏳ Pendente' : '✅ Aprovada'}
                    </span>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div className="space-y-4">
                        <div className="bg-white rounded-lg shadow-sm p-5">
                            <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-3">Metadados</h3>
                            <dl className="space-y-2 text-sm">
                                {question.organization && (
                                    <div className="flex gap-2"><dt className="text-gray-500 w-20 flex-shrink-0">Banca</dt><dd className="font-medium text-gray-800">{question.organization}</dd></div>
                                )}
                                {question.institution && (
                                    <div className="flex gap-2"><dt className="text-gray-500 w-20 flex-shrink-0">Órgão</dt><dd className="text-gray-700">{question.institution}</dd></div>
                                )}
                                {question.role && (
                                    <div className="flex gap-2"><dt className="text-gray-500 w-20 flex-shrink-0">Cargo</dt><dd className="text-gray-700">{question.role}</dd></div>
                                )}
                                {question.year && (
                                    <div className="flex gap-2"><dt className="text-gray-500 w-20 flex-shrink-0">Ano</dt><dd className="text-gray-700">{question.year}</dd></div>
                                )}
                                {question.subjects?.length > 0 && (
                                    <div className="flex gap-2 flex-wrap">
                                        <dt className="text-gray-500 w-20 flex-shrink-0">Matérias</dt>
                                        <dd className="flex flex-wrap gap-1">
                                            {question.subjects.map((s: any) => (
                                                <span key={s.id || s.name} className="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded font-medium">{s.name}</span>
                                            ))}
                                        </dd>
                                    </div>
                                )}
                                {importItem && (
                                    <div className="flex gap-2"><dt className="text-gray-500 w-20 flex-shrink-0">Lote</dt><dd className="text-gray-600 text-xs">{importItem.import?.batch_name ?? '—'}</dd></div>
                                )}
                                {importItem?.import?.error_message && (
                                    <div className="mt-3 p-3 bg-red-50 border border-red-100 rounded text-red-700 text-xs">
                                        <p className="font-bold mb-1">Log do Lote:</p>
                                        <p>{importItem.import.error_message}</p>
                                    </div>
                                )}
                            </dl>
                        </div>

                        <div className="bg-white rounded-lg shadow-sm p-5">
                            <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-3">Enunciado</h3>
                            <div className="prose prose-indigo max-w-none text-gray-800 text-sm leading-relaxed">
                                <ReactMarkdown
                                    urlTransform={urlTransform}
                                    components={{
                                        img: ({ ...props }) => <img {...props} className="max-w-full h-auto rounded-lg my-4 mx-auto block shadow-sm" />
                                    }}>
                                    {question.statement}
                                </ReactMarkdown>
                            </div>

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

                        <div className="bg-white rounded-lg shadow-sm p-5" id="alternatives-card">
                            <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-3">Alternativas</h3>
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
                                                    <div className="prose prose-indigo max-w-none text-sm text-gray-700 alternatives-markdown">
                                                        <ReactMarkdown
                                                            urlTransform={urlTransform}
                                                            components={{
                                                                img: ({ ...props }) => <img {...props} className="max-w-full h-auto rounded-lg border border-gray-200 block my-2" />
                                                            }}>
                                                            {alt.content}
                                                        </ReactMarkdown>
                                                    </div>
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

                        <div className="bg-white rounded-lg shadow-sm p-5 space-y-3">
                            <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-3">Ações</h3>
                            {question.review_status === 'pending' ? (
                                <button
                                    onClick={() => approveMutation.mutate()}
                                    disabled={approveMutation.isPending}
                                    className="w-full px-4 py-3 bg-green-600 text-white rounded-md hover:bg-green-700 font-medium text-sm flex items-center justify-center gap-2">
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Aprovar e Publicar Questão
                                </button>
                            ) : (
                                <button
                                    onClick={() => revertMutation.mutate()}
                                    disabled={revertMutation.isPending}
                                    className="w-full px-4 py-3 bg-orange-500 text-white rounded-md hover:bg-orange-600 font-medium text-sm flex items-center justify-center gap-2">
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                                    Retornar para Revisão
                                </button>
                            )}

                            <Link to={`/admin/questions/${question.id}/edit`} className="w-full px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 font-medium text-sm flex items-center justify-center gap-2">
                                ✍️ Abrir no Editor Completo
                            </Link>

                            <div className="flex gap-2 pt-2 border-t border-gray-100">
                                <Link to="/admin/import/review" className="flex-1 px-3 py-2 bg-gray-100 text-gray-600 rounded-md text-sm text-center hover:bg-gray-200">
                                    ⬅️ Voltar à Lista
                                </Link>
                            </div>
                        </div>

                        {/* Histórico de Triagem */}
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

                                                {/* Issues Detected Array */}
                                                {log.issues_detected && log.issues_detected.length > 0 && (
                                                    <div className="flex flex-wrap gap-1 mt-2">
                                                        {log.issues_detected.map((iss: string) => (
                                                            <span key={iss} className="px-2 py-0.5 bg-red-100 text-red-700 text-[10px] uppercase font-bold tracking-wider rounded">
                                                                🚫 {iss.replace(/_/g, ' ')}
                                                            </span>
                                                        ))}
                                                    </div>
                                                )}

                                                {/* Quality Score */}
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

                    <div>
                        {hasImageModels ? (
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
                                                🗑️ Remover Imagem
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
                                                        <p className="text-xs text-gray-500 mt-0.5">A imagem recortada substitui a imagem do enunciado.</p>
                                                    </div>
                                                    <button
                                                        onClick={() => { setActiveTarget('statement'); handleSaveCrop(); }}
                                                        disabled={saving}
                                                        className="flex-shrink-0 px-4 py-2.5 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-medium text-sm flex items-center gap-2 disabled:opacity-60 transition-colors">
                                                        {saving && activeTarget === 'statement' ? 'Salvando...' : '✂️ Confirmar no Enunciado'}
                                                    </button>
                                                </div>
                                            </div>

                                            <div className={`rounded-lg border-2 p-3 transition-colors ${activeTarget !== 'statement' ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 bg-gray-50'}`}>
                                                <p className="text-xs font-semibold uppercase tracking-wide text-indigo-700 mb-2">🔤 Alternativa Visual</p>
                                                <div className="flex flex-wrap items-center gap-3">
                                                    <div className="flex items-center gap-1.5">
                                                        <span className="text-xs text-gray-600 font-medium">Letra:</span>
                                                        {['A', 'B', 'C', 'D', 'E'].map(ltr => (
                                                            <button
                                                                key={ltr}
                                                                onClick={() => setActiveTarget(ltr)}
                                                                className={`w-9 h-9 rounded-md font-bold text-sm transition-all ${activeTarget === ltr ? 'bg-indigo-600 text-white shadow-md ring-2 ring-indigo-300' : 'bg-white text-gray-600 hover:bg-indigo-100 border border-gray-300'}`}>
                                                                {ltr}
                                                            </button>
                                                        ))}
                                                    </div>
                                                    <button
                                                        onClick={() => handleSaveCrop()}
                                                        disabled={saving || activeTarget === 'statement'}
                                                        className="flex-1 px-4 py-2.5 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 font-medium text-sm flex items-center justify-center gap-2 disabled:opacity-60 transition-colors">
                                                        {saving && activeTarget !== 'statement' ? 'Salvando...' : `Salvar como Alt. ${activeTarget === 'statement' ? 'A' : activeTarget}`}
                                                    </button>
                                                </div>
                                                <p className="text-xs text-gray-400 mt-2">💡 Selecione a letra, ajuste o recorte e salve. O sistema avança para a próxima letra automaticamente.</p>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : hasEmbeddedImages || question.image_path ? (
                            <div className="bg-white rounded-lg shadow-sm p-8 text-center border-2 border-dashed border-green-200">
                                <div className="text-5xl mb-4">🖼️</div>
                                <h3 className="text-lg font-semibold text-gray-700 mb-2">Imagens Processadas Automaticamente</h3>
                                <p className="text-sm text-gray-500 mb-4">O novo importador estruturado (ENEM API) detectou imagens anexas e as tratou inserindo o Markdown correspondente dentro do fluxo do enunciado e alternativas. Verifique se a renderização final e o gabarito fazem sentido.</p>
                                <button
                                    onClick={() => approveMutation.mutate()}
                                    className="px-6 py-2.5 bg-green-600 text-white rounded-md hover:bg-green-700 font-medium">
                                    ✅ Aprovar e Publicar Questão
                                </button>
                            </div>
                        ) : (
                            <div className="bg-white rounded-lg shadow-sm p-8 text-center">
                                <div className="text-5xl mb-4">📝</div>
                                <h3 className="text-lg font-semibold text-gray-700 mb-2">Questão Sem Imagens</h3>
                                <p className="text-sm text-gray-500 mb-4">Revise o texto do enunciado e as alternativas. Se a transcrição estiver certa, clique em aprovar.</p>
                                <button
                                    onClick={() => approveMutation.mutate()}
                                    className="px-6 py-2.5 bg-green-600 text-white rounded-md hover:bg-green-700 font-medium">
                                    ✅ Aprovar e Publicar
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
