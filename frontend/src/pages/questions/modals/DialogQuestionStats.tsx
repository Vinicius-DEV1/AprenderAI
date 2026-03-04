import { useState, useEffect } from 'react';
import { toast } from 'sonner';
import api from '../../../api/axios';

interface Stats {
    difficulty: string;
    total_responses: number;
    correct_percentage: number;
    incorrect_percentage: number;
    average_time_seconds: number;
    alternative_distribution: Record<string, number>;
}

interface Props {
    isOpen: boolean;
    onClose: () => void;
    questionId: number;
}

export default function DialogQuestionStats({ isOpen, onClose, questionId }: Props) {
    const [stats, setStats] = useState<Stats | null>(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (isOpen) loadStats();
    }, [isOpen, questionId]);

    const loadStats = async () => {
        setLoading(true);
        try {
            const res = await api.get(`/api/v1/questions/${questionId}/stats`);
            setStats(res.data);
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Estatísticas ocultas ou indisponíveis.');
            onClose();
        } finally {
            setLoading(false);
        }
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-0">
            <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onClick={onClose}></div>
            <div className="relative w-full max-w-md rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-xl animate-fade-in-up">
                <div className="flex justify-between items-center mb-5">
                    <h3 className="text-xl font-bold text-gray-900 dark:text-slate-100 flex items-center gap-2">
                        📊 Estatísticas da Questão
                    </h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        ✕
                    </button>
                </div>

                {loading ? (
                    <div className="py-10 text-center text-gray-500 animate-pulse">Carregando dados...</div>
                ) : !stats ? (
                    <div className="py-10 text-center text-gray-500">Sem dados.</div>
                ) : (
                    <div className="space-y-5">
                        <div className="grid grid-cols-2 gap-3">
                            <div className="bg-gray-50 dark:bg-slate-700/50 p-3 rounded-xl text-center border border-gray-100 dark:border-slate-600">
                                <div className="text-[11px] text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider mb-1">Respostas Totais</div>
                                <div className="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{stats.total_responses}</div>
                            </div>
                            <div className="bg-gray-50 dark:bg-slate-700/50 p-3 rounded-xl text-center border border-gray-100 dark:border-slate-600">
                                <div className="text-[11px] text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider mb-1">Tempo Médio</div>
                                <div className="text-2xl font-bold text-slate-700 dark:text-slate-200">{stats.average_time_seconds}s</div>
                            </div>
                        </div>

                        <div className="space-y-1.5 bg-gray-50 dark:bg-slate-700/30 p-4 rounded-xl border border-gray-100 dark:border-slate-700/50">
                            <div className="flex justify-between text-sm font-medium">
                                <span className="text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 px-2 py-0.5 rounded text-xs border border-emerald-100 dark:border-emerald-800/50">Acertos ({stats.correct_percentage}%)</span>
                                <span className="text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 px-2 py-0.5 rounded text-xs border border-red-100 dark:border-red-800/50">Erros ({stats.incorrect_percentage}%)</span>
                            </div>
                            <div className="h-3 w-full bg-red-400 dark:bg-red-500 rounded-full overflow-hidden flex shadow-inner mt-2">
                                <div className="h-full bg-emerald-500 dark:bg-emerald-400 transition-all duration-1000 ease-out relative" style={{ width: `${stats.correct_percentage}%` }}>
                                    <div className="absolute inset-0 bg-white/20"></div>
                                </div>
                            </div>
                        </div>

                        {stats.alternative_distribution && Object.keys(stats.alternative_distribution).length > 0 && (
                            <div className="pt-2">
                                <h4 className="text-[11px] uppercase font-bold text-gray-500 dark:text-gray-400 tracking-wider mb-3 pl-1">Distribuição</h4>
                                <div className="space-y-2.5">
                                    {Object.entries(stats.alternative_distribution).map(([alt, pct]) => (
                                        <div key={alt} className="flex items-center gap-3 text-sm group">
                                            <div className="w-7 h-7 flex items-center justify-center rounded-full bg-gray-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold text-xs group-hover:bg-indigo-100 dark:group-hover:bg-indigo-900/50 group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition-colors">{alt}</div>
                                            <div className="flex-1 bg-gray-100 dark:bg-slate-700 h-2.5 rounded-full overflow-hidden shadow-inner cursor-default" title={`${pct}% selecionaram a alternativa ${alt}`}>
                                                <div className="h-full bg-indigo-500/80 dark:bg-indigo-400/80 rounded-full transition-all duration-500 ease-out" style={{ width: `${pct}%` }}></div>
                                            </div>
                                            <div className="w-12 text-right text-gray-500 dark:text-gray-400 font-medium text-xs font-mono">{pct}%</div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
