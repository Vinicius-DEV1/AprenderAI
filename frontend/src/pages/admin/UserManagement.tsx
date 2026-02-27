import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link, useSearchParams } from 'react-router-dom';
import api from '../../api/axios';

export default function UserManagement() {
    const [searchParams, setSearchParams] = useSearchParams();

    const initialSearch = searchParams.get('search') || '';
    const initialStatus = searchParams.get('status') || '';
    const initialPage = parseInt(searchParams.get('page') || '1', 10);

    const [searchInput, setSearchInput] = useState(initialSearch);
    const [statusFilter, setStatusFilter] = useState(initialStatus);

    const { data, isLoading } = useQuery({
        queryKey: ['admin-users', initialSearch, initialStatus, initialPage],
        queryFn: async () => {
            const params = new URLSearchParams();
            if (initialSearch) params.append('search', initialSearch);
            if (initialStatus) params.append('status', initialStatus);
            if (initialPage > 1) params.append('page', initialPage.toString());

            const res = await api.get(`/api/v1/admin/users?${params.toString()}`);
            return res.data; // Assumes Laravel paginator format { data: [...], current_page, last_page, etc. }
        }
    });

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        const newParams = new URLSearchParams();
        if (searchInput) newParams.append('search', searchInput);
        if (statusFilter) newParams.append('status', statusFilter);
        newParams.append('page', '1');
        setSearchParams(newParams);
    };

    const handleStatusChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        setStatusFilter(e.target.value);
        const newParams = new URLSearchParams();
        if (searchInput) newParams.append('search', searchInput);
        if (e.target.value) newParams.append('status', e.target.value);
        newParams.append('page', '1');
        setSearchParams(newParams);
    };

    const clearFilters = () => {
        setSearchInput('');
        setStatusFilter('');
        setSearchParams(new URLSearchParams());
    };

    const hasFilters = initialSearch || initialStatus;
    const users = data?.data || [];
    const meta = data?.meta || { current_page: 1, last_page: 1 };

    if (isLoading) return <div className="p-8">Carregando usuários...</div>;

    return (
        <div className="py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
            <div className="mb-8 flex justify-between items-center">
                <div>
                    <h1 className="text-3xl font-bold text-gray-800 mb-2">Gerenciar Usuários</h1>
                    <p className="text-gray-600">Listagem completa e gestão de usuários do sistema</p>
                </div>
                <div>
                    {/* Potential "Create User" button could go here */}
                </div>
            </div>

            {/* Filters */}
            <div className="bg-white rounded-xl shadow-sm p-4 mb-6">
                <form onSubmit={handleFilter} className="flex flex-col md:flex-row gap-4">
                    <div className="flex-1">
                        <input
                            type="text"
                            value={searchInput}
                            onChange={(e) => setSearchInput(e.target.value)}
                            placeholder="Buscar por nome ou email..."
                            className="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                        />
                    </div>
                    <div className="w-full md:w-48">
                        <select
                            value={statusFilter}
                            onChange={handleStatusChange}
                            className="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Todos os Status</option>
                            <option value="active">Ativos</option>
                            <option value="banned">Banidos</option>
                        </select>
                    </div>
                    <button type="submit" className="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        Filtrar
                    </button>
                    {hasFilters && (
                        <button type="button" onClick={clearFilters} className="flex items-center justify-center bg-gray-100 text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors">
                            Limpar
                        </button>
                    )}
                </form>
            </div>

            {/* Users Table */}
            <div className="bg-white rounded-xl shadow-sm overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead className="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Usuário</th>
                                <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                                <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Plano</th>
                                <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Uso IA</th>
                                <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Cadastro</th>
                                <th className="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                            {users.length > 0 ? users.map((user: any) => {
                                const maxQuestions = user.plan?.max_ai_questions || 0;
                                const currentQuestions = user.ai_questions_count || 0;
                                const percent = maxQuestions > 0 ? Math.min(100, (currentQuestions / maxQuestions) * 100) : 0;
                                const colorClass = percent > 90 ? 'bg-red-500' : (percent > 50 ? 'bg-yellow-500' : 'bg-green-500');

                                return (
                                    <tr key={user.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center">
                                                <img
                                                    src={user.avatar_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name || 'User')}`}
                                                    alt={user.name}
                                                    className="w-10 h-10 rounded-full object-cover"
                                                />
                                                <div className="ml-4">
                                                    <div className="text-sm font-semibold text-gray-900">{user.name}</div>
                                                    <div className="text-sm text-gray-500">{user.email}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            {user.is_banned ? (
                                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    Banido
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    Ativo
                                                </span>
                                            )}
                                            {user.role === 'admin' && (
                                                <span className="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                    Admin
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className="text-sm text-gray-600">{user.plan?.name || 'Sem Plano'}</span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm text-gray-900 font-bold">
                                                {currentQuestions} <span className="text-gray-400 text-xs font-normal">/ {maxQuestions > 0 ? maxQuestions : '∞'}</span>
                                            </div>
                                            <div className="w-16 h-1.5 bg-gray-100 rounded-full mt-1 overflow-hidden">
                                                <div className={`h-full ${colorClass}`} style={{ width: `${percent}%` }}></div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {user.created_at ? new Date(user.created_at).toLocaleDateString('pt-BR') : 'N/A'}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <Link to={`/admin/users/${user.id}`} className="text-blue-600 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 px-3 py-1 rounded-md transition-colors">
                                                Gerenciar
                                            </Link>
                                        </td>
                                    </tr>
                                );
                            }) : (
                                <tr>
                                    <td colSpan={6} className="px-6 py-12 text-center text-gray-500">
                                        Nenhum usuário encontrado com os filtros atuais.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Paginação */}
                {meta.last_page > 1 && (
                    <div className="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                        <span className="text-sm text-gray-500">
                            Página {meta.current_page} de {meta.last_page}
                        </span>
                        <div className="flex gap-2">
                            <button
                                disabled={initialPage === 1}
                                onClick={() => {
                                    const params = new URLSearchParams(searchParams);
                                    params.set('page', (initialPage - 1).toString());
                                    setSearchParams(params);
                                }}
                                className="px-3 py-1 border rounded text-sm disabled:opacity-50">
                                Anterior
                            </button>
                            <button
                                disabled={initialPage === meta.last_page}
                                onClick={() => {
                                    const params = new URLSearchParams(searchParams);
                                    params.set('page', (initialPage + 1).toString());
                                    setSearchParams(params);
                                }}
                                className="px-3 py-1 border rounded text-sm disabled:opacity-50">
                                Próxima
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
