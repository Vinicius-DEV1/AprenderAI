import { motion, AnimatePresence } from 'framer-motion';
import { useState, useEffect } from 'react';
import api from '../../../api/axios';
import { renderMd } from '../../../utils/markdown';

interface Props {
    isOpen: boolean;
    onClose: () => void;
    questionId: number | null;
    onNext?: () => void;
    onPrev?: () => void;
}

const apiUrl = import.meta.env.VITE_API_BASE_URL || (import.meta.env.PROD ? '' : 'http://localhost:8000');

export default function AdminQuestionViewModal({ isOpen, onClose, questionId, onNext, onPrev }: Props) {
    const [question, setQuestion] = useState<any>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (isOpen && questionId) {
            fetchQuestion();
        } else {
            setQuestion(null);
            setError(null);
        }
    }, [isOpen, questionId]);

    const fetchQuestion = async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await api.get(`/api/v1/admin/questions/${questionId}`);
            setQuestion(res.data);
        } catch (err: any) {
            console.error('Erro ao buscar questão:', err);
            setError('Não foi possível carregar os dados da questão.');
        } finally {
            setLoading(false);
        }
    };

    if (!isOpen) return null;

    return (
        <AnimatePresence>
            <div className="fixed inset-0 z-[110] flex items-center justify-center p-4">
                <motion.div
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    exit={{ opacity: 0 }}
                    onClick={onClose}
                    className="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
                />

                <motion.div
                    initial={{ opacity: 0, scale: 0.95, y: 20 }}
                    animate={{ opacity: 1, scale: 1, y: 0 }}
                    exit={{ opacity: 0, scale: 0.95, y: 20 }}
                    className="relative w-full max-w-5xl max-h-[90vh] bg-white rounded-3xl shadow-2xl flex flex-col overflow-hidden border border-slate-100"
                >
                    {/* Navigation Arrows */}
                    {onPrev && (
                        <button
                            onClick={(e) => { e.stopPropagation(); onPrev(); }}
                            className="absolute left-4 top-1/2 -translate-y-1/2 z-20 w-12 h-12 bg-white/80 backdrop-blur-sm border border-slate-200 rounded-full flex items-center justify-center text-slate-400 hover:text-indigo-600 hover:border-indigo-200 transition-all shadow-lg group"
                            title="Anterior (Seta Esquerda)"
                        >
                            <span className="text-2xl group-hover:-translate-x-0.5 transition-transform">❮</span>
                        </button>
                    )}
                    {onNext && (
                        <button
                            onClick={(e) => { e.stopPropagation(); onNext(); }}
                            className="absolute right-4 top-1/2 -translate-y-1/2 z-20 w-12 h-12 bg-white/80 backdrop-blur-sm border border-slate-200 rounded-full flex items-center justify-center text-slate-400 hover:text-indigo-600 hover:border-indigo-200 transition-all shadow-lg group"
                            title="Próxima (Seta Direita)"
                        >
                            <span className="text-2xl group-hover:translate-x-0.5 transition-transform">❯</span>
                        </button>
                    )}

                    {/* Header */}
                    <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-white sticky top-0 z-10">
                        <div className="flex items-center gap-3">
                            <div className="w-8 h-8 bg-indigo-50 rounded-lg flex items-center justify-center text-lg shadow-inner">👁️</div>
                            <div>
                                <h2 className="text-lg font-black text-slate-900 leading-none">Visualizar Questão</h2>
                                <p className="text-slate-400 font-bold text-[9px] uppercase tracking-widest mt-1">ID: #{questionId}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            {onPrev && onNext && (
                                <div className="hidden sm:flex items-center gap-1 bg-slate-50 p-1 rounded-xl border border-slate-100 mr-2">
                                    <button onClick={onPrev} className="p-1 px-2 hover:bg-white rounded-lg text-slate-400 hover:text-indigo-600 transition text-xs font-black uppercase">❮ Ant</button>
                                    <span className="w-px h-3 bg-slate-200 mx-1"></span>
                                    <button onClick={onNext} className="p-1 px-2 hover:bg-white rounded-lg text-slate-400 hover:text-indigo-600 transition text-xs font-black uppercase">Próx ❯</button>
                                </div>
                            )}
                            <button
                                onClick={onClose}
                                className="w-8 h-8 rounded-lg bg-slate-50 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition flex items-center justify-center"
                            >
                                <span className="text-xl">&times;</span>
                            </button>
                        </div>
                    </div>

                    {/* Content */}
                    <div className="flex-1 overflow-y-auto p-6 space-y-6 custom-scrollbar">
                        {loading ? (
                            <div className="py-20 flex flex-col items-center justify-center gap-4">
                                <div className="w-10 h-10 border-4 border-slate-200 border-t-indigo-600 rounded-full animate-spin" />
                                <span className="text-xs font-black text-slate-400 uppercase tracking-widest">Buscando detalhes...</span>
                            </div>
                        ) : error ? (
                            <div className="p-6 bg-red-50 border border-red-100 rounded-2xl text-red-600 text-sm font-bold flex items-center gap-3">
                                <span>❌</span> {error}
                            </div>
                        ) : question ? (
                            <>
                                {/* Metadata Badges */}
                                <div className="flex flex-wrap gap-2">
                                    <Badge color="blue" label="Dificuldade" value={question.difficulty || 'N/A'} isUpper />
                                    <Badge color="indigo" label="Tipo" value={question.type || 'N/A'} isUpper />
                                    <Badge color="slate" label="Formato" value={question.format || 'N/A'} isUpper />
                                    <Badge color="emerald" label="Status" value={question.review_status || 'N/A'} isUpper />
                                </div>

                                {/* Statement */}
                                <section>
                                    <h3 className="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                        <span className="w-1 h-1 bg-indigo-400 rounded-full"></span>
                                        Enunciado
                                    </h3>
                                    <div 
                                        className="prose prose-slate max-w-none text-slate-800 bg-slate-50 p-5 rounded-2xl border border-slate-100 shadow-sm leading-relaxed text-sm markdown-content"
                                        dangerouslySetInnerHTML={renderMd(question.statement || '')}
                                    />
                                </section>

                                {/* Alternatives */}
                                {question.alternatives && question.alternatives.length > 0 && (
                                    <section>
                                        <h3 className="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                            <span className="w-1 h-1 bg-indigo-400 rounded-full"></span>
                                            Alternativas
                                        </h3>
                                        <div className="grid grid-cols-1 gap-2.5">
                                            {question.alternatives.map((alt: any) => {
                                                const isCorrect = alt.label === question.correct_answer || alt.is_correct;
                                                return (
                                                    <div
                                                        key={alt.id || alt.label}
                                                        className={`p-3.5 rounded-2xl border transition-all flex gap-3 ${isCorrect
                                                            ? 'bg-emerald-50 border-emerald-200 shadow-sm'
                                                            : 'bg-white border-slate-100 hover:border-slate-200'
                                                            }`}
                                                    >
                                                        <div className={`w-7 h-7 rounded-lg flex items-center justify-center font-black text-xs flex-shrink-0 ${isCorrect ? 'bg-emerald-500 text-white shadow-lg' : 'bg-slate-100 text-slate-400'
                                                            }`}>
                                                            {alt.label}
                                                        </div>
                                                        <div className="flex-1 text-xs font-medium text-slate-700 leading-relaxed pt-1 flex flex-col gap-2">
                                                            <div 
                                                                className="prose prose-sm max-w-none text-slate-700 dark:text-slate-300 markdown-content"
                                                                dangerouslySetInnerHTML={renderMd(alt.content || '')}
                                                            />
                                                            {alt.image_path && (
                                                                <img src={alt.image_path.startsWith('http') ? alt.image_path : `${apiUrl}/storage/${alt.image_path.replace(/^\//, '').replace(/^storage\//, '')}`.replace(/([^:])\/\//g, '$1/')} className="max-h-24 rounded-lg mt-2 border border-slate-100 self-start" />
                                                            )}
                                                        </div>
                                                        {isCorrect && (
                                                            <span className="text-emerald-500 font-black text-[9px] uppercase tracking-widest bg-emerald-100/50 px-2 py-0.5 rounded-lg self-start mt-1">Gabarito</span>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </section>
                                )}

                                {/* Explanation */}
                                {question.explanation && (
                                    <section className="bg-amber-50 rounded-2xl p-6 border border-amber-100 relative overflow-hidden group">
                                        <div className="absolute top-0 right-0 p-4 opacity-5 group-hover:scale-110 transition-transform text-4xl">📝</div>
                                        <h3 className="text-[9px] font-black text-amber-500 uppercase tracking-widest mb-3 flex items-center gap-2">
                                            <span className="w-1 h-1 bg-amber-400 rounded-full animate-pulse"></span>
                                            Explicação Recomendada
                                        </h3>
                                        <div 
                                            className="prose prose-amber max-w-none text-amber-900/80 font-medium leading-relaxed text-xs markdown-content"
                                            dangerouslySetInnerHTML={renderMd(question.explanation)}
                                        />
                                    </section>
                                )}

                                {/* Details Grid */}
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div className="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                                        <span className="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Matérias</span>
                                        <div className="flex flex-wrap gap-1.5">
                                            {question.subjects?.map((s: any) => (
                                                <span key={s.id} className="bg-white border border-slate-200 text-slate-600 px-2 py-0.5 rounded-full text-[10px] font-bold shadow-xs">
                                                    {s.name}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                    <div className="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                                        <span className="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Assuntos</span>
                                        <div className="flex flex-wrap gap-1.5">
                                            {question.topics?.map((t: any) => (
                                                <span key={t.id} className="bg-white border border-slate-200 text-indigo-600 px-2 py-0.5 rounded-full text-[10px] font-bold shadow-xs">
                                                    {t.name}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            </>
                        ) : null}
                    </div>

                    {/* Footer */}
                    <div className="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end">
                        <button
                            onClick={onClose}
                            className="px-6 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-slate-50 transition shadow-sm"
                        >
                            Fechar Visualização
                        </button>
                    </div>
                </motion.div>
            </div>
        </AnimatePresence>
    );
}

function Badge({ label, value, color, isUpper }: any) {
    const colors: any = {
        blue: 'bg-blue-50 text-blue-600 border-blue-100',
        indigo: 'bg-indigo-50 text-indigo-600 border-indigo-100',
        emerald: 'bg-emerald-50 text-emerald-600 border-emerald-100',
        slate: 'bg-slate-50 text-slate-600 border-slate-100',
        amber: 'bg-amber-50 text-amber-600 border-amber-100',
    };

    return (
        <div className={`px-4 py-2 rounded-xl border flex flex-col items-start ${colors[color] || colors.slate}`}>
            <span className="text-[8px] font-black uppercase tracking-widest opacity-60 leading-none mb-1">{label}</span>
            <span className={`text-xs font-black leading-none ${isUpper ? 'uppercase' : ''}`}>{value}</span>
        </div>
    );
}
