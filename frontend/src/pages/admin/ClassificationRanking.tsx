import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/axios';
import { AdminPageSkeleton } from './components/AdminSkeletons';
import { motion } from 'framer-motion';
import { Link } from 'react-router-dom';

export default function ClassificationRanking() {
    const [type, setType] = useState<'all' | 'enem' | 'concurso'>('all');
    const [publishedOnly, setPublishedOnly] = useState(true);

    const { data, isLoading, isError } = useQuery({
        queryKey: ['classification-ranking', type, publishedOnly],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/questions/stats/classification-ranking', {
                params: { 
                    type: type === 'all' ? '' : type,
                    published_only: publishedOnly ? 1 : 0
                }
            });
            return res.data;
        }
    });

    if (isLoading) return <AdminPageSkeleton />;
    if (isError || !data) return <div className="p-8 text-center text-red-500 font-bold">Erro ao carregar ranking de classificação.</div>;

    const subjects = data.subjects || [];
    const topics = data.topics || [];

    return (
        <div className="p-4 md:p-6 w-full space-y-6 animate-in fade-in duration-500 bg-gray-50/30 min-h-screen">
            {/* Header */}
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div className="flex items-center gap-2 mb-1">
                        <Link to="/admin/questions" className="text-gray-400 hover:text-indigo-600 transition text-sm flex items-center gap-1 font-bold">
                            <span>⬅️</span> Voltar para o Banco
                        </Link>
                    </div>
                    <h1 className="text-3xl font-black text-gray-900 tracking-tight flex items-center gap-3">
                        Ranking de Conteúdo
                        <span className="text-xs bg-indigo-100 text-indigo-700 px-2 py-1 rounded-lg uppercase tracking-widest font-black">Analytics</span>
                    </h1>
                    <p className="text-gray-500 font-medium">Distribuição quantitativa de questões por Disciplina e Assunto.</p>
                </div>

                <div className="flex flex-col md:flex-row items-start md:items-center gap-4">
                    {/* Published Only Toggle */}
                    <button 
                        onClick={() => setPublishedOnly(!publishedOnly)}
                        className={`flex items-center gap-3 px-4 py-2.5 rounded-2xl border transition-all duration-300 font-bold ${
                            publishedOnly 
                                ? 'bg-emerald-50 border-emerald-200 text-emerald-700 shadow-sm shadow-emerald-50' 
                                : 'bg-white border-gray-200 text-gray-400 hover:border-gray-300'
                        }`}
                    >
                        <div className={`w-5 h-5 rounded-lg border-2 flex items-center justify-center transition-all ${
                            publishedOnly ? 'bg-emerald-500 border-emerald-500' : 'border-gray-300'
                        }`}>
                            {publishedOnly && <span className="text-[10px] text-white">✓</span>}
                        </div>
                        <span className="text-[10px] uppercase tracking-widest">Apenas Publicadas</span>
                    </button>

                    {/* Filter Pills */}
                    <div className="flex bg-white p-1.5 rounded-2xl shadow-sm border border-gray-100 self-start md:self-center">
                        <button
                            onClick={() => setType('all')}
                            className={`px-4 py-2 rounded-xl text-xs font-black transition-all uppercase tracking-widest ${type === 'all' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'text-gray-400 hover:text-gray-600'}`}
                        >
                            Todos
                        </button>
                        <button
                            onClick={() => setType('enem')}
                            className={`px-4 py-2 rounded-xl text-xs font-black transition-all uppercase tracking-widest ${type === 'enem' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'text-gray-400 hover:text-gray-600'}`}
                        >
                            ENEM
                        </button>
                        <button
                            onClick={() => setType('concurso')}
                            className={`px-4 py-2 rounded-xl text-xs font-black transition-all uppercase tracking-widest ${type === 'concurso' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'text-gray-400 hover:text-gray-600'}`}
                        >
                            Concurso
                        </button>
                    </div>
                </div>
            </div>

            {/* Grid Ranking */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                
                {/* Disciplinas (Subjects) */}
                <motion.div 
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden flex flex-col"
                >
                    <div className="p-6 border-b border-gray-50 bg-gray-50/30 flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 bg-indigo-600 text-white rounded-xl shadow-lg shadow-indigo-100 flex items-center justify-center text-lg">📚</div>
                            <div>
                                <h2 className="text-lg font-black text-gray-900 leading-none">Disciplinas</h2>
                                <p className="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-1">Ranking por volume</p>
                            </div>
                        </div>
                        <span className="text-xs font-black text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded-lg uppercase">
                            {subjects.length} Total
                        </span>
                    </div>

                    <div className="p-2 overflow-y-auto max-h-[70vh]">
                        <table className="w-full">
                            <tbody className="divide-y divide-gray-50">
                                {subjects.map((s: any, index: number) => (
                                    <tr key={s.id} className="group hover:bg-indigo-50/30 transition-colors">
                                        <td className="px-4 py-4 w-12 text-center">
                                            <span className={`text-xs font-black ${index < 3 ? 'text-indigo-600' : 'text-gray-300'}`}>
                                                #{index + 1}
                                            </span>
                                        </td>
                                        <td className="px-2 py-4">
                                            <div className="flex flex-col">
                                                <span className="text-sm font-bold text-gray-700 group-hover:text-indigo-900 transition">{s.name}</span>
                                                <span className="text-[9px] text-gray-300 font-black uppercase tracking-tighter">ID: {s.id}</span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-4 text-right">
                                            <div className="flex flex-col items-end">
                                                <span className="text-lg font-black text-gray-900">{s.questions_count}</span>
                                                <span className="text-[9px] text-gray-400 font-bold uppercase tracking-widest">Questões</span>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {subjects.length === 0 && (
                                    <tr>
                                        <td colSpan={3} className="p-12 text-center text-gray-400 font-medium italic">Nenhuma disciplina encontrada.</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </motion.div>

                {/* Assuntos (Topics) */}
                <motion.div 
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.1 }}
                    className="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden flex flex-col"
                >
                    <div className="p-6 border-b border-gray-50 bg-gray-50/30 flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 bg-emerald-500 text-white rounded-xl shadow-lg shadow-emerald-100 flex items-center justify-center text-lg">🏷️</div>
                            <div>
                                <h2 className="text-lg font-black text-gray-900 leading-none">Assuntos</h2>
                                <p className="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-1">Ranking por volume</p>
                            </div>
                        </div>
                        <span className="text-xs font-black text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-lg uppercase">
                            {topics.length} Total
                        </span>
                    </div>

                    <div className="p-2 overflow-y-auto max-h-[70vh]">
                        <table className="w-full">
                            <tbody className="divide-y divide-gray-50">
                                {topics.map((t: any, index: number) => (
                                    <tr key={t.id} className="group hover:bg-emerald-50/30 transition-colors">
                                        <td className="px-4 py-4 w-12 text-center">
                                            <span className={`text-xs font-black ${index < 3 ? 'text-emerald-500' : 'text-gray-300'}`}>
                                                #{index + 1}
                                            </span>
                                        </td>
                                        <td className="px-2 py-4">
                                            <div className="flex flex-col">
                                                <span className="text-sm font-bold text-gray-700 group-hover:text-emerald-900 transition">{t.name}</span>
                                                <span className="text-[9px] text-gray-300 font-black uppercase tracking-tighter">ID: {t.id}</span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-4 text-right">
                                            <div className="flex flex-col items-end">
                                                <span className="text-lg font-black text-gray-900">{t.questions_count}</span>
                                                <span className="text-[9px] text-gray-400 font-bold uppercase tracking-widest">Questões</span>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {topics.length === 0 && (
                                    <tr>
                                        <td colSpan={3} className="p-12 text-center text-gray-400 font-medium italic">Nenhum assunto encontrado.</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </motion.div>

            </div>
        </div>
    );
}
