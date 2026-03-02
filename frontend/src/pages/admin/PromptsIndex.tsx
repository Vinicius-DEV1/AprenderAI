import { toast } from 'sonner';
import { useQuery, useMutation } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import api from '../../api/axios';
import { AdminPageSkeleton } from './components/AdminSkeletons';

export default function PromptsIndex() {

    const { data: prompts, isLoading } = useQuery({
        queryKey: ['admin-prompts'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/prompts');
            return res.data;
        }
    });

    const clearCacheMutation = useMutation({
        mutationFn: async (slug: string) => {
            await api.post(`/api/v1/admin/prompts/${slug}/clear-cache`);
        },
        onSuccess: () => {
            toast.success('Cache limpo com sucesso!');
        },
        onError: () => {
            toast.error('Erro ao limpar cache.');
        }
    });

    if (isLoading) return <AdminPageSkeleton />;

    return (
        <div className="py-6 px-4 md:px-6 w-full">
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100 uppercase tracking-tighter">Gerenciador de Prompts</h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400">Gerencie as instruções enviadas para a IA em tempo real.</p>
                    </div>
                </div>

                <div className="bg-white dark:bg-slate-900 shadow-sm rounded-3xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
                                    <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Identificador (Slug)</th>
                                    <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Título</th>
                                    <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Descrição</th>
                                    <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
                                {prompts?.length > 0 ? (
                                    prompts.map((prompt: any) => (
                                        <tr key={prompt.id || prompt.slug} className="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                                            <td className="px-6 py-4">
                                                <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black bg-indigo-50 text-indigo-700 border border-indigo-100 uppercase tracking-wider">
                                                    {prompt.slug}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-sm font-black text-slate-900 dark:text-slate-100 group-hover:text-indigo-600 transition-colors">
                                                {prompt.title}
                                            </td>
                                            <td className="px-6 py-4 text-xs font-medium text-slate-500 dark:text-slate-400">
                                                {prompt.description || 'Sem descrição'}
                                            </td>
                                            <td className="px-6 py-4 text-right space-x-3">
                                                <button
                                                    title="Limpar Cache"
                                                    onClick={() => clearCacheMutation.mutate(prompt.slug)}
                                                    className="inline text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 transition-all hover:scale-110">
                                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                    </svg>
                                                </button>
                                                <Link to={`/admin/prompts/${prompt.slug}/edit`} className="inline text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-all hover:scale-110">
                                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </Link>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={4} className="px-6 py-12 text-center text-slate-500 font-black text-xs uppercase tracking-widest">
                                            Nenhum prompt encontrado.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    );
}
