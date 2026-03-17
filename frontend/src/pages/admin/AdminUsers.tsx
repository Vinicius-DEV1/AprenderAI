import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import api from '../../api/axios';
import { AdminPageSkeleton } from './components/AdminSkeletons';
import { motion, AnimatePresence } from 'framer-motion';

export default function AdminUsers() {
    const navigate = useNavigate();
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState('');
    const [role, setRole] = useState('');
    const [status, setStatus] = useState('');

    const { data, isLoading } = useQuery({
        queryKey: ['admin-users', page, search, role, status],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/users', {
                params: { page, search, role, status }
            });
            return res.data;
        }
    });

    if (isLoading) return <AdminPageSkeleton />;

    const users = data?.data || [];
    const meta = data || { last_page: 1, current_page: 1, total: 0 };

    return (
        <div className="p-6 max-w-[1600px] mx-auto space-y-8 animate-in fade-in duration-500 bg-gray-50/30 min-h-screen">
            {/* Header */}
            <div className="flex justify-between items-end">
                <div>
                    <h1 className="text-3xl font-black text-gray-900 tracking-tight">Gestão de Alunos</h1>
                    <p className="text-gray-500 font-medium">Controle de acesso, permissões e auditoria de consumo de IA.</p>
                </div>
                <div className="flex gap-4">
                    <div className="bg-white px-4 py-2 rounded-xl shadow-sm border border-gray-100 flex flex-col items-end">
                        <span className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Total de Alunos</span>
                        <span className="text-xl font-black text-indigo-600">{meta.total}</span>
                    </div>
                </div>
            </div>

            {/* Premium Filters Bar */}
            <div className="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex flex-wrap gap-4 items-center">
                <div className="flex-1 min-w-[300px] relative">
                    <span className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
                    <input
                        type="text"
                        placeholder="Buscar por nome, e-mail ou ID..."
                        className="w-full pl-10 pr-4 py-3 bg-gray-50/50 rounded-xl text-sm font-bold border border-gray-100 focus:ring-2 focus:ring-indigo-500 transition-all"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </div>
                <select
                    className="px-4 py-3 bg-gray-50/50 rounded-xl text-sm font-bold border border-gray-100 focus:ring-2 focus:ring-indigo-500"
                    value={role}
                    onChange={e => setRole(e.target.value)}
                >
                    <option value="">Todas as Funções</option>
                    <option value="user">Aluno</option>
                    <option value="admin">Administrador</option>
                    <option value="editor">Editor</option>
                </select>
                <select
                    className="px-4 py-3 bg-gray-50/50 rounded-xl text-sm font-bold border border-gray-100 focus:ring-2 focus:ring-indigo-500"
                    value={status}
                    onChange={e => setStatus(e.target.value)}
                >
                    <option value="">Todos os Status</option>
                    <option value="active">Ativos</option>
                    <option value="banned">Bloqueados</option>
                </select>
            </div>

            {/* Users Table */}
            <div className="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden mb-12">
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
                            <AnimatePresence mode="popLayout">
                                {users.map((u: any) => (
                                    <motion.tr
                                        key={u.id}
                                        layout
                                        initial={{ opacity: 0 }}
                                        animate={{ opacity: 1 }}
                                        exit={{ opacity: 0 }}
                                        className="hover:bg-gray-50 transition-colors"
                                    >
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center">
                                                <img
                                                    src={u.avatar_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(u.name)}`}
                                                    alt={u.name}
                                                    className="w-10 h-10 rounded-full object-cover"
                                                />
                                                <div className="ml-4">
                                                    <div className="text-sm font-semibold text-gray-900">{u.name}</div>
                                                    <div className="text-sm text-gray-500 flex items-center gap-1.5">
                                                        {u.email}
                                                        {u.google_id && (
                                                            <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-white border border-gray-200 text-gray-600 shadow-sm" title="Login via Google">
                                                                <svg className="w-2.5 h-2.5 mr-0.5" viewBox="0 0 24 24" fill="currentColor">
                                                                    <path d="M12.48 10.92v3.28h7.84c-.24 1.84-.908 3.152-1.928 4.176-1.152 1.152-2.92 2.392-5.912 2.392-4.584 0-8.208-3.712-8.208-8.296s3.624-8.296 8.208-8.296c2.488 0 4.296.976 5.64 2.256l2.328-2.328C18.528 2.216 15.84 0 12.48 0 6.48 0 1.6 4.84 1.6 11.04s4.88 11.04 10.88 11.04c3.24 0 5.68-1.072 7.744-3.232 2.12-2.12 2.792-5.112 2.792-7.536 0-.72-.056-1.4-.16-2.024h-10.376z" />
                                                                </svg>
                                                                GOOGLE
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            {u.is_banned ? (
                                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    Banido
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    Ativo
                                                </span>
                                            )}
                                            {u.role === 'admin' && (
                                                <span className="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                    Admin
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className="text-sm text-gray-600">{u.plan?.name || 'Sem Plano'}</span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm text-gray-900 font-bold">
                                                {u.ai_questions_count || 0} <span className="text-gray-400 text-xs font-normal">/ {u.plan?.max_ai_questions || '∞'}</span>
                                            </div>
                                            <div className="w-16 h-1.5 bg-gray-100 rounded-full mt-1 overflow-hidden">
                                                {(() => {
                                                    const percent = (u.plan && u.plan.max_ai_questions > 0)
                                                        ? Math.min(100, ((u.ai_questions_count || 0) / u.plan.max_ai_questions) * 100)
                                                        : 0;
                                                    const colorClass = percent > 90 ? 'bg-red-500' : (percent > 50 ? 'bg-yellow-500' : 'bg-green-500');
                                                    return <div className={`h-full ${colorClass}`} style={{ width: `${percent}%` }}></div>;
                                                })()}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {new Date(u.created_at).toLocaleDateString('pt-BR')}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button
                                                onClick={() => navigate(`/admin/users/${u.id}`)}
                                                className="text-blue-600 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 px-3 py-1 rounded-md transition-colors inline-block"
                                            >
                                                Gerenciar
                                            </button>
                                        </td>
                                    </motion.tr>
                                ))}
                            </AnimatePresence>
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                <div className="p-6 bg-gray-50/30 border-t border-gray-100 flex items-center justify-between">
                    <span className="text-xs font-black text-gray-400 uppercase">Página {meta.current_page} de {meta.last_page}</span>
                    <div className="flex gap-2">
                        <button
                            onClick={() => setPage(p => Math.max(1, p - 1))}
                            disabled={page === 1}
                            className="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-bold disabled:opacity-50 hover:bg-gray-50 transition shadow-sm active:scale-95"
                        >Anterior</button>
                        <button
                            onClick={() => setPage(p => p + 1)}
                            disabled={page === meta.last_page}
                            className="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-bold disabled:opacity-50 hover:bg-gray-50 transition shadow-sm active:scale-95"
                        >Próxima</button>
                    </div>
                </div>
            </div>
        </div>
    );
}
