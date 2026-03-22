import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import api from '../../api/axios';
import { AdminPageSkeleton } from './components/AdminSkeletons';

interface Campaign {
    id: number;
    name: string;
    slug: string;
    utm_source: string;
    utm_medium: string | null;
    utm_campaign: string | null;
    utm_term: string | null;
    utm_content: string | null;
    base_url: string;
    is_active: boolean;
    tracking_url: string;
    created_at: string;
}

export default function CampaignManager() {
    const queryClient = useQueryClient();
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingCampaign, setEditingCampaign] = useState<Campaign | null>(null);

    const { data: campaigns, isLoading } = useQuery<Campaign[]>({
        queryKey: ['admin-campaigns'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/campaigns');
            return res.data;
        }
    });

    const upsertMutation = useMutation({
        mutationFn: async (payload: any) => {
            if (editingCampaign) {
                return await api.put(`/api/v1/admin/campaigns/${editingCampaign.id}`, payload);
            }
            return await api.post('/api/v1/admin/campaigns', payload);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-campaigns'] });
            toast.success(editingCampaign ? 'Campanha atualizada!' : 'Campanha criada!');
            setIsModalOpen(false);
            setEditingCampaign(null);
        },
        onError: () => {
            toast.error('Erro ao salvar campanha.');
        }
    });

    const deleteMutation = useMutation({
        mutationFn: async (id: number) => {
            await api.delete(`/api/v1/admin/campaigns/${id}`);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-campaigns'] });
            toast.info('Campanha removida.');
        }
    });

    const toggleMutation = useMutation({
        mutationFn: async (id: number) => {
            await api.post(`/api/v1/admin/campaigns/${id}/toggle`);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-campaigns'] });
        }
    });

    const copyToClipboard = (url: string) => {
        navigator.clipboard.writeText(url);
        toast.success('Link copiado para a área de transferência!');
    };

    if (isLoading) return <AdminPageSkeleton />;

    return (
        <div className="py-6 px-4 md:px-6 w-full animate-in fade-in duration-500">
            <div className="mb-8 flex justify-between items-center text-left">
                <div>
                    <h1 className="text-3xl font-black text-gray-900 mb-2 tracking-tighter uppercase">Gestão de Campanhas</h1>
                    <p className="text-gray-500 font-medium">Crie e gerencie links de rastreamento (UTM) oficiais</p>
                </div>
                <button
                    onClick={() => {
                        setEditingCampaign(null);
                        setIsModalOpen(true);
                    }}
                    className="bg-indigo-600 text-white px-6 py-3 rounded-xl hover:bg-indigo-700 transition-all shadow-md shadow-indigo-100 flex items-center gap-2 font-black text-xs uppercase tracking-widest"
                >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" /></svg>
                    Nova Campanha
                </button>
            </div>

            <div className="mb-8 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="bg-indigo-50 border border-indigo-100 p-4 rounded-2xl flex gap-3 shadow-sm">
                    <div className="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center shrink-0 text-white shadow-md shadow-indigo-100">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <div>
                        <h4 className="text-[10px] font-black text-indigo-900 uppercase tracking-widest mb-1">O que são UTMs?</h4>
                        <p className="text-[11px] text-indigo-700 leading-relaxed font-medium">Parâmetros adicionados à URL que permitem ao Google Analytics e à nossa plataforma saber exatamente de onde veio cada novo usuário.</p>
                    </div>
                </div>
                <div className="bg-blue-50 border border-blue-100 p-4 rounded-2xl flex gap-3 shadow-sm">
                    <div className="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center shrink-0 text-white shadow-md shadow-blue-100">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                    </div>
                    <div>
                        <h4 className="text-[10px] font-black text-blue-900 uppercase tracking-widest mb-1">Por que usar?</h4>
                        <p className="text-[11px] text-blue-700 leading-relaxed font-medium">Para medir o Retorno sobre Investimento (ROI) de cada canal de marketing (Instagram, Google Ads, E-mail) individualmente.</p>
                    </div>
                </div>
                <div className="bg-purple-50 border border-purple-100 p-4 rounded-2xl flex gap-3 shadow-sm">
                    <div className="w-10 h-10 bg-purple-600 rounded-xl flex items-center justify-center shrink-0 text-white shadow-md shadow-purple-100">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" /></svg>
                    </div>
                    <div>
                        <h4 className="text-[10px] font-black text-purple-900 uppercase tracking-widest mb-1">Como usar?</h4>
                        <p className="text-[11px] text-purple-700 leading-relaxed font-medium">Crie uma nova campanha, copie o link gerado e use-o em seus anúncios ou posts. O sistema fará o rastreio automático.</p>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6">
                <div className="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead className="bg-gray-50/50 border-b border-gray-100">
                                <tr>
                                    <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Campanha</th>
                                    <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">UTM Source/Medium</th>
                                    <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">URL de Destino</th>
                                    <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Status</th>
                                    <th className="px-6 py-4 text-right text-[10px] font-black text-gray-400 uppercase tracking-widest">Ações</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {campaigns && campaigns.length > 0 ? campaigns.map((c) => (
                                    <tr key={c.id} className="hover:bg-indigo-50/30 transition-colors group">
                                        <td className="px-6 py-5">
                                            <div className="font-black text-gray-900 text-sm">{c.name}</div>
                                            <div className="text-[10px] text-gray-400 font-mono uppercase mt-0.5">{c.utm_campaign || 'N/A'}</div>
                                        </td>
                                        <td className="px-6 py-5">
                                            <div className="flex items-center gap-1.5 text-xs">
                                                <span className="bg-blue-50 text-blue-700 px-2 py-0.5 rounded font-bold border border-blue-100">{c.utm_source}</span>
                                                <span className="text-gray-300">/</span>
                                                <span className="bg-gray-50 text-gray-600 px-2 py-0.5 rounded font-medium border border-gray-100">{c.utm_medium || 'N/A'}</span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-5 min-w-[200px]">
                                            <div className="flex items-center gap-2">
                                                <div className="text-[10px] font-mono text-gray-500 truncate max-w-[150px]" title={c.tracking_url}>
                                                    {c.tracking_url}
                                                </div>
                                                <button
                                                    onClick={() => copyToClipboard(c.tracking_url)}
                                                    className="p-1.5 text-indigo-600 hover:bg-indigo-100 rounded-lg transition-all"
                                                    title="Copiar Link de Rastreamento"
                                                >
                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" /></svg>
                                                </button>
                                            </div>
                                        </td>
                                        <td className="px-6 py-5">
                                            <button
                                                onClick={() => toggleMutation.mutate(c.id)}
                                                className={`inline-flex items-center px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all ${c.is_active ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'}`}
                                            >
                                                {c.is_active ? 'Ativa' : 'Inativa'}
                                            </button>
                                        </td>
                                        <td className="px-6 py-5 text-right space-x-2">
                                            <button
                                                onClick={() => {
                                                    setEditingCampaign(c);
                                                    setIsModalOpen(true);
                                                }}
                                                className="inline-block px-3 py-1.5 bg-white border border-gray-100 rounded-lg text-[10px] font-black text-gray-700 shadow-sm hover:border-indigo-200 hover:text-indigo-600 transition-all">
                                                EDITAR
                                            </button>
                                            <button
                                                onClick={() => {
                                                    if (window.confirm('Excluir esta campanha permanentemente?')) {
                                                        deleteMutation.mutate(c.id);
                                                    }
                                                }}
                                                className="inline-block px-3 py-1.5 bg-white border border-red-50 rounded-lg text-[10px] font-black text-red-600 shadow-sm hover:bg-red-600 hover:text-white transition-all">
                                                EXCLUIR
                                            </button>
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan={5} className="px-6 py-12 text-center text-gray-400 font-black text-xs uppercase tracking-widest">
                                            Nenhuma campanha cadastrada.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Modal for Create/Edit */}
            {isModalOpen && (
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm animate-in fade-in duration-300">
                    <div className="bg-white rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden border border-gray-100 scale-in-95 animate-in slide-in-from-bottom-4 duration-300">
                        <div className="p-6 border-b border-gray-100 flex justify-between items-center">
                            <h2 className="text-xl font-black text-gray-900 uppercase tracking-tight">
                                {editingCampaign ? 'Editar Campanha' : 'Nova Campanha'}
                            </h2>
                            <button onClick={() => setIsModalOpen(false)} className="text-gray-400 hover:text-gray-600">
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <form onSubmit={(e) => {
                            e.preventDefault();
                            const formData = new FormData(e.currentTarget);
                            const payload = Object.fromEntries(formData.entries());
                            upsertMutation.mutate({
                                ...payload,
                                is_active: editingCampaign ? editingCampaign.is_active : true
                            });
                        }} className="p-6 space-y-4">
                            <div className="bg-indigo-50/50 p-4 rounded-2xl border border-indigo-100 flex gap-3 mb-2">
                                <div className="text-indigo-600">
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </div>
                                <p className="text-[10px] text-indigo-800 leading-snug font-medium">
                                    <strong className="block mb-0.5 uppercase tracking-tighter">Dica de Ouro:</strong>
                                    Use apenas <span className="underline">letras minúsculas</span> e substitua espaços por <span className="bg-white px-1 py-0.5 rounded border border-indigo-200 font-mono">_</span> (sublinhados) para evitar quebras no rastreamento.
                                </p>
                            </div>

                            <div>
                                <label className="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Nome Interno (Painel)</label>
                                <input name="name" defaultValue={editingCampaign?.name} required className="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all font-bold text-gray-800" placeholder="ex: Lançamento Mentor ENEM - Março 2024" />
                                <p className="text-[9px] text-gray-400 mt-1 italic">Como você vai identificar este link na lista abaixo. Ex: Anúncio do Instagram Story 01.</p>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">UTM Source (Origem)</label>
                                    <input name="utm_source" defaultValue={editingCampaign?.utm_source} required className="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all font-bold text-gray-800" placeholder="ex: facebook, google, newsletter" />
                                    <p className="text-[9px] text-gray-400 mt-1 italic">O site ou rede social onde o link será postado. (Ex: facebook, tiktok, bio_instagram).</p>
                                </div>
                                <div>
                                    <label className="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">UTM Medium (Canal)</label>
                                    <input name="utm_medium" defaultValue={editingCampaign?.utm_medium || ''} className="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all font-bold text-gray-800" placeholder="ex: cpc, story, email" />
                                    <p className="text-[9px] text-gray-400 mt-1 italic">O tipo de formato usado. (Ex: cpc para anúncios pagos, story para posts, organic para fixos).</p>
                                </div>
                            </div>
                            <div>
                                <label className="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">UTM Campaign (Identificador)</label>
                                <input name="utm_campaign" defaultValue={editingCampaign?.utm_campaign || ''} className="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all font-bold text-gray-800" placeholder="ex: venda_direta_pascoa" />
                                <p className="text-[9px] text-gray-400 mt-1 italic">O nome comercial da sua promoção ou oferta específica. (Ex: promo_agosto, lancamento_v2).</p>
                            </div>
                            <div>
                                <label className="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">URL de Destino (No sistema)</label>
                                <input name="base_url" defaultValue={editingCampaign?.base_url || '/'} className="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all font-bold text-gray-800" placeholder="ex: /register ou /planos" />
                                <p className="text-[9px] text-gray-400 mt-1 italic">Para qual página o usuário será enviado. (Ex: / para a home, /register para cadastro direto).</p>
                            </div>
                            <div className="pt-4 flex gap-3">
                                <button type="button" onClick={() => setIsModalOpen(false)} className="flex-1 py-3 px-6 bg-gray-50 text-gray-500 font-black text-xs uppercase tracking-widest rounded-xl hover:bg-gray-100 transition-all">Cancelar</button>
                                <button type="submit" disabled={upsertMutation.isPending} className="flex-1 py-3 px-6 bg-indigo-600 text-white font-black text-xs uppercase tracking-widest rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100 disabled:opacity-50">
                                    {upsertMutation.isPending ? 'Salvando...' : 'Salvar Campanha'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
