import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/axios';

export default function AdminUsers() {
    const [page] = useState(1);
    const [search, setSearch] = useState('');

    const { data, isLoading } = useQuery({
        queryKey: ['admin-users', page, search],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/users', { params: { page, search } });
            return res.data;
        }
    });

    if (isLoading) return <div className="p-8">Carregando usuários...</div>;

    return (
        <div className="p-4 md:p-8 max-w-7xl mx-auto">
            <header className="mb-10">
                <h1 className="text-3xl font-bold text-gray-800 dark:text-white mb-2">Gestão de Usuários 👥</h1>
                <p className="text-gray-600 dark:text-slate-400">Gerencie permissões e visualize o status dos alunos.</p>
            </header>

            <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
                <div className="p-4 border-b border-slate-100 dark:border-slate-700">
                    <input
                        type="text"
                        placeholder="Buscar por nome ou e-mail..."
                        className="w-full max-w-md px-4 py-2 border rounded-lg dark:bg-slate-900 dark:border-slate-700"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse">
                        <thead className="bg-slate-50 dark:bg-slate-900/50">
                            <tr>
                                <th className="px-6 py-4 text-xs font-bold text-slate-400 uppercase">Usuário</th>
                                <th className="px-6 py-4 text-xs font-bold text-slate-400 uppercase">E-mail</th>
                                <th className="px-6 py-4 text-xs font-bold text-slate-400 uppercase">Função</th>
                                <th className="px-6 py-4 text-xs font-bold text-slate-400 uppercase">Plano</th>
                                <th className="px-6 py-4 text-xs font-bold text-slate-400 uppercase text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                            {data.data.map((u: any) => (
                                <tr key={u.id} className="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                                    <td className="px-6 py-4">
                                        <div className="flex items-center gap-3">
                                            <div className="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-xs">
                                                {u.name.substring(0, 1).toUpperCase()}
                                            </div>
                                            <span className="text-sm font-semibold">{u.name}</span>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 text-sm text-slate-500">{u.email}</td>
                                    <td className="px-6 py-4 text-sm">
                                        <span className={`px-2 py-1 rounded text-[10px] font-bold uppercase ${u.role === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-slate-100 text-slate-700'}`}>
                                            {u.role}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-sm">
                                        <span className="text-blue-600 font-medium">{u.plan?.name || 'Gratuito'}</span>
                                    </td>
                                    <td className="px-6 py-4 text-sm text-right">
                                        <button className="text-slate-400 hover:text-blue-600">
                                            ⚙️ Gerenciar
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}
