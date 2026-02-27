import { toast } from 'sonner';
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import api from '../../api/axios';
import { AdminPageSkeleton } from './components/AdminSkeletons';

export default function Coupons() {
    const queryClient = useQueryClient();
    const [page, setPage] = useState(1);

    const { data, isLoading } = useQuery({
        queryKey: ['admin-coupons', page],
        queryFn: async () => {
            const res = await api.get(`/api/v1/admin/coupons?page=${page}`);
            return res.data;
        }
    });

    const deleteMutation = useMutation({
        mutationFn: async (id: number) => {
            await api.delete(`/api/v1/admin/coupons/${id}`);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-coupons'] });
            toast.info('Cupom removido!');
        }
    });

    const handleDelete = (id: number) => {
        if (window.confirm('Confirmar exclusão definitiva deste cupom?')) {
            deleteMutation.mutate(id);
        }
    };

    if (isLoading) return <AdminPageSkeleton />;

    const coupons = data?.data || [];
    const meta = data?.meta || { last_page: 1, current_page: 1 };

    return (
        <div className="py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto animate-in fade-in duration-500">
            <div className="mb-8 flex justify-between items-center text-left">
                <div>
                    <h1 className="text-3xl font-black text-gray-900 mb-2 tracking-tighter uppercase">Cupons de Desconto</h1>
                    <p className="text-gray-500 font-medium">Gestão de ofertas e campanhas promocionais</p>
                </div>
                <Link to="/admin/coupons/create"
                    className="bg-indigo-600 text-white px-6 py-3 rounded-xl hover:bg-indigo-700 transition-all shadow-md shadow-indigo-100 flex items-center gap-2 font-black text-xs uppercase tracking-widest">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" /></svg>
                    Novo Cupom
                </Link>
            </div>

            {/* Coupons Table */}
            <div className="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left">
                        <thead className="bg-gray-50/50 border-b border-gray-100">
                            <tr>
                                <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Código</th>
                                <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Desconto</th>
                                <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Uso Atual</th>
                                <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Expiração</th>
                                <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Status</th>
                                <th className="px-6 py-4 text-right text-[10px] font-black text-gray-400 uppercase tracking-widest">Ações</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {coupons.length > 0 ? coupons.map((coupon: any) => {
                                const isExpired = coupon.expires_at ? new Date(coupon.expires_at) < new Date() : false;
                                return (
                                    <tr key={coupon.id} className="hover:bg-indigo-50/30 transition-colors group">
                                        <td className="px-6 py-5">
                                            <span className="font-mono font-black text-indigo-600 bg-indigo-50 px-3 py-1.5 rounded-lg border border-indigo-100 uppercase tracking-widest text-xs">
                                                {coupon.code}
                                            </span>
                                        </td>
                                        <td className="px-6 py-5 text-sm font-black text-gray-900">
                                            {coupon.type === 'percent'
                                                ? `${Number(coupon.value).toFixed(0)}% OFF`
                                                : `R$ ${Number(coupon.value).toLocaleString('pt-BR', { minimumFractionDigits: 2 })} OFF`
                                            }
                                        </td>
                                        <td className="px-6 py-5">
                                            <div className="flex items-center gap-1 text-sm font-black text-gray-700">
                                                {coupon.used_count || 0}
                                                {coupon.max_uses && <span className="text-gray-400 font-medium">/ {coupon.max_uses}</span>}
                                            </div>
                                        </td>
                                        <td className="px-6 py-5 text-xs font-medium text-gray-500">
                                            {coupon.expires_at ? (
                                                <span className={isExpired ? 'text-red-500 font-black' : ''}>
                                                    {new Date(coupon.expires_at).toLocaleDateString('pt-BR')}
                                                </span>
                                            ) : (
                                                <span className="text-gray-300 italic uppercase text-[9px]">Sem Limite</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-5">
                                            {coupon.is_active && !isExpired ? (
                                                <span className="inline-flex items-center px-3 py-1 rounded-lg text-[9px] font-black bg-green-100 text-green-700 uppercase tracking-widest">
                                                    Ativo
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center px-3 py-1 rounded-lg text-[9px] font-black bg-red-100 text-red-700 uppercase tracking-widest">
                                                    {isExpired ? 'Expirado' : 'Inativo'}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-6 py-5 text-right space-x-2">
                                            <Link to={`/admin/coupons/${coupon.id}/edit`} className="inline-block px-3 py-1.5 bg-white border border-gray-100 rounded-lg text-[10px] font-black text-gray-700 shadow-sm hover:border-indigo-200 hover:text-indigo-600 transition-all">
                                                EDITAR
                                            </Link>
                                            <button
                                                onClick={() => handleDelete(coupon.id)}
                                                className="inline-block px-3 py-1.5 bg-white border border-red-50 rounded-lg text-[10px] font-black text-red-600 shadow-sm hover:bg-red-600 hover:text-white transition-all">
                                                EXCLUIR
                                            </button>
                                        </td>
                                    </tr>
                                );
                            }) : (
                                <tr>
                                    <td colSpan={6} className="px-6 py-12 text-center text-gray-400 font-black text-xs uppercase tracking-widest">
                                        Nenhum cupom disponível.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Paginação Premium */}
                {meta.last_page > 1 && (
                    <div className="px-8 py-5 border-t border-gray-50 flex items-center justify-between bg-gray-50/30">
                        <span className="text-[10px] font-black text-gray-400 uppercase tracking-widest">
                            Página {meta.current_page} de {meta.last_page}
                        </span>
                        <div className="flex gap-2">
                            <button
                                disabled={page === 1}
                                onClick={() => setPage(page - 1)}
                                className="px-4 py-2 bg-white border border-gray-100 rounded-xl text-[10px] font-black text-gray-700 hover:border-indigo-200 disabled:opacity-30 transition-all">
                                ANTERIOR
                            </button>
                            <button
                                disabled={page === meta.last_page}
                                onClick={() => setPage(page + 1)}
                                className="px-4 py-2 bg-white border border-gray-100 rounded-xl text-[10px] font-black text-gray-700 hover:border-indigo-200 disabled:opacity-30 transition-all">
                                PRÓXIMA
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
