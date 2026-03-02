import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import api from '../../api/axios';
import { AdminPageSkeleton } from './components/AdminSkeletons';

export default function Plans() {
    const { data: plans, isLoading } = useQuery({
        queryKey: ['admin-plans'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/plans');
            return res.data;
        }
    });

    if (isLoading) return <AdminPageSkeleton />;
    if (!plans) return <div className="p-8 text-center text-red-500 font-black uppercase tracking-widest">Erro ao conectar com a API de Planos.</div>;

    return (
        <div className="py-6 px-4 md:px-6 w-full animate-in fade-in duration-500">
            <div className="mb-8 flex justify-between items-center">
                <div>
                    <h1 className="text-3xl font-black text-gray-900 mb-2 tracking-tighter uppercase">Gerenciar Planos</h1>
                    <p className="text-gray-500 font-medium">Configure os planos e preços da plataforma</p>
                </div>
                <Link to="/admin/plans/create" className="bg-indigo-600 text-white px-6 py-3 rounded-xl hover:bg-indigo-700 transition-all shadow-md shadow-indigo-100 flex items-center gap-2 font-black text-xs uppercase tracking-widest">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" /></svg>
                    Novo Plano
                </Link>
            </div>

            {/* Plans Table */}
            <div className="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead className="bg-gray-50/50 border-b border-gray-100">
                            <tr>
                                <th className="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Nome</th>
                                <th className="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Preço</th>
                                <th className="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Limites (Sim./Red./IA)</th>
                                <th className="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Status</th>
                                <th className="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Assinantes</th>
                                <th className="px-6 py-4 text-right text-[10px] font-black text-gray-400 uppercase tracking-widest">Ações</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {plans && plans.length > 0 ? plans.map((plan: any) => (
                                <tr key={plan.id} className="hover:bg-indigo-50/30 transition-colors group">
                                    <td className="px-6 py-5">
                                        <div className="text-sm font-black text-gray-900 group-hover:text-indigo-600 transition-colors">{plan.name}</div>
                                        <div className="text-[10px] text-gray-400 font-mono font-medium">{plan.slug}</div>
                                    </td>
                                    <td className="px-6 py-5">
                                        <div className="text-sm font-black text-indigo-600">
                                            R$ {Number(plan.price || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                                        </div>
                                        <div className="text-[10px] text-gray-400 font-bold uppercase tracking-tighter">/ {plan.interval === 'month' ? 'Mensal' : 'Anual'}</div>
                                    </td>
                                    <td className="px-6 py-5">
                                        <div className="flex flex-col gap-0.5">
                                            <div className="text-xs font-bold text-gray-700 leading-tight">
                                                {plan.simulations_limit === 0 ? '♾️' : plan.simulations_limit} <span className="text-[9px] text-gray-400 uppercase">Simulados</span>
                                            </div>
                                            <div className="text-xs font-bold text-gray-700 leading-tight">
                                                {plan.essays_limit === 0 ? '♾️' : plan.essays_limit} <span className="text-[9px] text-gray-400 uppercase">Redações</span>
                                            </div>
                                            <div className="text-xs font-bold text-indigo-500 leading-tight">
                                                {plan.max_ai_questions === 0 ? '♾️' : plan.max_ai_questions} <span className="text-[9px] text-indigo-300 uppercase italic">IA Feed</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-6 py-5">
                                        {plan.is_active ? (
                                            <span className="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-black bg-green-100 text-green-700 uppercase tracking-widest border border-green-200">
                                                Ativo
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-black bg-gray-100 text-gray-400 uppercase tracking-widest border border-gray-200">
                                                Inativo
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-6 py-5">
                                        <div className="flex flex-col">
                                            <span className="text-sm font-black text-gray-700">{plan.subscriptions_count}</span>
                                            <span className="text-[9px] text-gray-400 font-bold uppercase">Ativos</span>
                                        </div>
                                    </td>
                                    <td className="px-6 py-5 text-right">
                                        <Link to={`/admin/plans/${plan.id}/edit`} className="px-3 py-1.5 bg-white border border-gray-100 rounded-lg text-[10px] font-black text-gray-700 shadow-sm hover:border-indigo-200 hover:text-indigo-600 transition-all uppercase tracking-tighter">
                                            Editar
                                        </Link>
                                    </td>
                                </tr>
                            )) : (
                                <tr>
                                    <td colSpan={6} className="px-6 py-12 text-center text-gray-400 font-black text-xs uppercase tracking-widest">
                                        Nenhum plano cadastrado.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}
