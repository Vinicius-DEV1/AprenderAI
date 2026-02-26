import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useEssays } from '../../hooks/useEssays';
import { useAuthStore } from '../../stores/authStore';

export default function EssayList() {
    const [page, setPage] = useState(1);
    const { data, isLoading } = useEssays(page);
    const { user } = useAuthStore();

    if (isLoading) {
        return (
            <div className="flex justify-center py-20">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            </div>
        );
    }

    const essays = data?.data || [];
    const meta = data?.meta || {};
    const canCreate = data?.essayLimit?.can_create ?? true;
    const limit = data?.essayLimit?.total ?? 0;
    const used = data?.essayLimit?.remaining !== undefined && data?.essayLimit?.total !== undefined
        ? data.essayLimit.total - data.essayLimit.remaining
        : 0;

    const handlePageChange = (newPage: number) => {
        if (newPage >= 1 && newPage <= (meta.last_page || 1)) {
            setPage(newPage);
        }
    };

    const getStatusInfo = (status: string) => {
        const map: Record<string, { label: string, classes: string }> = {
            'pending': { label: 'Pendente', classes: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300' },
            'in_progress': { label: 'Rascunho', classes: 'bg-gray-100 dark:bg-slate-700 text-gray-800 dark:text-slate-300' },
            'evaluating': { label: 'Avaliando', classes: 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300' },
            'completed': { label: 'Corrigida', classes: 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300' },
            'error': { label: 'Erro', classes: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300' },
        };
        return map[status] || { label: status, classes: 'bg-gray-100 dark:bg-slate-700 text-gray-800 dark:text-slate-300' };
    };


    const planName = user?.plan?.name || '';
    const isPlus = planName.toLowerCase().includes('plus');
    const isBasic = planName.toLowerCase().includes('básico') || planName.toLowerCase().includes('basico');

    return (
        <div className="py-12">
            <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">

                {/* Limit Card */}
                <div className="mb-6 bg-white dark:bg-slate-900 overflow-hidden shadow-sm dark:shadow-none dark:border dark:border-slate-700 sm:rounded-lg">
                    <div className="p-6 text-gray-900 dark:text-slate-200 flex justify-between items-center flex-wrap gap-4">
                        <div>
                            <h3 className="text-lg font-medium">Limite Mensal</h3>
                            <p className="text-sm text-gray-500 dark:text-slate-400">
                                Você usou <span className="font-bold">{used}</span> de <span className="font-bold">{limit === 0 ? '0 (Free)' : limit}</span> redações este mês.
                            </p>
                        </div>
                        {canCreate ? (
                            <Link
                                to="/essays/create"
                                className="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 active:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 transition ease-in-out duration-150"
                            >
                                Nova Redação
                            </Link>
                        ) : (
                            (isPlus || isBasic) ? (
                                <Link
                                    to="/recharge"
                                    className="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-500 active:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 transition ease-in-out duration-150 mr-2"
                                >
                                    Recarregar (+{isPlus ? 15 : 2}) - R$ {isPlus ? '20,00' : '5,00'}
                                </Link>
                            ) : (
                                <button
                                    type="button"
                                    onClick={() => alert('Modal Limite Atingido')}
                                    className="inline-flex items-center px-4 py-2 bg-gray-400 dark:bg-slate-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition ease-in-out duration-150"
                                >
                                    Limite Atingido
                                </button>
                            )
                        )}
                    </div>
                    {!canCreate && limit === 0 && (
                        <div className="px-6 pb-6">
                            <p className="text-red-500 dark:text-red-400 text-sm">Faça upgrade para o plano Basic ou Plus para enviar redações!</p>
                            <Link to="/plans" className="text-blue-500 dark:text-blue-400 hover:underline text-sm">Ver Planos</Link>
                        </div>
                    )}
                </div>

                {/* List */}
                <div className="bg-white dark:bg-slate-900 overflow-hidden shadow-sm dark:shadow-none dark:border dark:border-slate-700 sm:rounded-lg">
                    <div className="p-6 text-gray-900 dark:text-slate-200">
                        {essays.length === 0 ? (
                            <div className="text-center py-10">
                                <p className="text-gray-500 dark:text-slate-400">Nenhuma redação encontrada.</p>
                            </div>
                        ) : (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                                        <thead className="bg-gray-50 dark:bg-slate-800">
                                            <tr>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Data</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Tema</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Tipo</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Nota</th>
                                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white dark:bg-slate-900 divide-y divide-gray-200 dark:divide-slate-700">
                                            {essays.map((essay: any) => {
                                                const statusInfo = getStatusInfo(essay.status);
                                                return (
                                                    <tr key={essay.id} className="hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors">
                                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-slate-400">
                                                            {essay.created_at || essay.formatted_date}
                                                        </td>
                                                        <td className="px-6 py-4 text-sm text-gray-900 dark:text-slate-200">
                                                            {String(essay.title).length > 40 ? String(essay.title).substring(0, 40) + '...' : essay.title}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-slate-400">
                                                            <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300">
                                                                {String(essay.type).toUpperCase()}
                                                            </span>
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-sm">
                                                            <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusInfo.classes}`}>
                                                                {statusInfo.label}
                                                            </span>
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-slate-200 font-bold">
                                                            {essay.score ?? '-'}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                            <Link to={`/essays/${essay.id}`} className="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">
                                                                Abrir
                                                            </Link>
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                                {meta.last_page > 1 && (
                                    <div className="mt-4 flex justify-between items-center text-sm">
                                        <button
                                            onClick={() => handlePageChange(page - 1)}
                                            disabled={page === 1}
                                            className="px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-md disabled:opacity-50 text-gray-700 dark:text-gray-300"
                                        >
                                            Anterior
                                        </button>
                                        <span className="text-gray-600 dark:text-gray-400">Página {page} de {meta.last_page}</span>
                                        <button
                                            onClick={() => handlePageChange(page + 1)}
                                            disabled={page === meta.last_page}
                                            className="px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-md disabled:opacity-50 text-gray-700 dark:text-gray-300"
                                        >
                                            Próxima
                                        </button>
                                    </div>
                                )}
                            </>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
