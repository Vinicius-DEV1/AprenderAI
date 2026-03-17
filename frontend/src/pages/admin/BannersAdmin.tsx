import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { adminGetBanners, adminCreateBanner, adminUpdateBanner, adminDeleteBanner } from '../../api/banners';
import { adminSendNotification, adminGetNotifications } from '../../api/notifications';
import { toast } from 'sonner';

export default function BannersAdmin() {
    const [activeTab, setActiveTab] = useState<'banners' | 'notifications'>('banners');
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [editingBanner, setEditingBanner] = useState<any>(null);
    const queryClient = useQueryClient();

    const { data, isLoading } = useQuery({
        queryKey: ['admin-banners'],
        queryFn: () => adminGetBanners().then(r => r.data),
    });

    const deleteMutation = useMutation({
        mutationFn: (id: number) => adminDeleteBanner(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-banners'] });
            toast.success('Comunicado excluído com sucesso');
        },
    });

    const banners = data?.banners ?? [];

    const handleEdit = (banner: any) => {
        setEditingBanner(banner);
        setIsFormOpen(true);
    };

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100">Comunicados & Engajamento</h1>
                {activeTab === 'banners' && (
                    <button
                        onClick={() => { setEditingBanner(null); setIsFormOpen(true); }}
                        className="px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 transition"
                    >
                        + Novo Comunicado
                    </button>
                )}
            </div>

            <div className="flex border-b border-slate-200 dark:border-slate-700">
                <button
                    onClick={() => setActiveTab('banners')}
                    className={`px-6 py-3 font-semibold text-sm transition-colors border-b-2 ${activeTab === 'banners' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700'}`}
                >
                    Banners e Modais
                </button>
                <button
                    onClick={() => setActiveTab('notifications')}
                    className={`px-6 py-3 font-semibold text-sm transition-colors border-b-2 ${activeTab === 'notifications' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700'}`}
                >
                    Disparo de Notificações
                </button>
            </div>

            {activeTab === 'banners' ? (
                <>
                    {isFormOpen && (
                        <BannerForm
                            banner={editingBanner}
                            onClose={() => { setIsFormOpen(false); setEditingBanner(null); }}
                            onSuccess={() => {
                                queryClient.invalidateQueries({ queryKey: ['admin-banners'] });
                                setIsFormOpen(false);
                                setEditingBanner(null);
                                toast.success('Comunicado salvo com sucesso');
                            }}
                        />
                    )}

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {isLoading ? (
                            <div className="col-span-full py-8 text-center text-slate-500">Carregando...</div>
                        ) : banners.map((b: any) => (
                            <div key={b.id} className="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden flex flex-col">
                                {b.image_url ? (
                                    <img src={b.image_url} alt="" className="w-full h-32 object-cover" />
                                ) : (
                                    <div className="w-full h-24 flex items-center justify-center bg-slate-100 dark:bg-slate-800 text-slate-400 font-medium">
                                        Sem imagem ({b.display_type})
                                    </div>
                                )}
                                <div className="p-4 flex-1 flex flex-col">
                                    <div className="flex justify-between items-start mb-2">
                                        <h3 className="font-bold text-slate-900 dark:text-slate-100 truncate flex-1 pr-2">{b.title}</h3>
                                        <div className={`w-2.5 h-2.5 rounded-full mt-1.5 ${b.is_active ? 'bg-green-500' : 'bg-red-500'}`} title={b.is_active ? 'Ativo' : 'Inativo'} />
                                    </div>
                                    <p className="text-xs text-slate-500 mb-4 line-clamp-2">{b.body || 'Sem descrição'}</p>

                                    <div className="mt-auto pt-4 border-t border-slate-100 dark:border-slate-800 grid grid-cols-4 gap-2 text-center">
                                        <div><p className="text-xs text-slate-500">Views</p><p className="font-semibold">{b.views}</p></div>
                                        <div><p className="text-xs text-slate-500">Clicks</p><p className="font-semibold text-blue-600">{b.clicks}</p></div>
                                        <div><p className="text-xs text-slate-500">Closes</p><p className="font-semibold text-red-600">{b.closes}</p></div>
                                        <div><p className="text-xs text-slate-500">CTR</p><p className="font-semibold text-green-600">{b.views ? Math.round((b.clicks / b.views) * 100) : 0}%</p></div>
                                    </div>
                                </div>
                                <div className="bg-slate-50 dark:bg-slate-800/50 p-2 flex gap-2">
                                    <button onClick={() => handleEdit(b)} className="flex-1 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-md">
                                        Editar
                                    </button>
                                    <button onClick={() => { if(confirm('Excluir este banner?')) deleteMutation.mutate(b.id); }} className="px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-100 dark:hover:bg-red-900/40 rounded-md">
                                        Excluir
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </>
            ) : (
                <DirectNotificationForm />
            )}
        </div>
    );
}

function DirectNotificationForm() {
    const [formData, setFormData] = useState({
        title: '',
        body: '',
        type: 'info' as any,
        target_group: 'all' as any,
        action_url: '',
    });

    const queryClient = useQueryClient();

    const { data: logsData, isLoading: logsLoading } = useQuery({
        queryKey: ['admin-notification-logs'],
        queryFn: () => adminGetNotifications().then(r => r.data),
    });

    const mutation = useMutation({
        mutationFn: (data: any) => adminSendNotification(data),
        onSuccess: (res: any) => {
            toast.success(`Notificação enviada para ${res.data.sent_to || 1} usuários!`);
            setFormData({ title: '', body: '', type: 'info', target_group: 'all', action_url: '' });
            queryClient.invalidateQueries({ queryKey: ['admin-notification-logs'] });
        },
        onError: () => toast.error('Falha ao enviar notificação'),
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        mutation.mutate({
            ...formData,
            broadcast: formData.target_group === 'all'
        });
    };

    const sentNotifications = logsData?.notifications ?? [];

    return (
        <div className="space-y-8">
            <div className="bg-white dark:bg-slate-900 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 max-w-2xl">
                <h2 className="text-lg font-bold mb-4">Enviar Notificação Direta</h2>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium mb-1">Público Alvo</label>
                        <select 
                            value={formData.target_group} 
                            onChange={e => setFormData({...formData, target_group: e.target.value})}
                            className="w-full border rounded-lg p-2 dark:bg-slate-800 dark:border-slate-700"
                        >
                            <option value="all">Todos os usuários</option>
                            <option value="basic">Apenas Plano Básico / Free</option>
                            <option value="plus">Apenas Plano Plus</option>
                            <option value="admin">Apenas Administradores</option>
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-medium mb-1">Título *</label>
                        <input type="text" value={formData.title} onChange={e => setFormData({...formData, title: e.target.value})} required className="w-full border rounded-lg p-2 dark:bg-slate-800 dark:border-slate-700" />
                    </div>

                    <div>
                        <label className="block text-sm font-medium mb-1">Mensagem</label>
                        <textarea value={formData.body} onChange={e => setFormData({...formData, body: e.target.value})} rows={3} className="w-full border rounded-lg p-2 dark:bg-slate-800 dark:border-slate-700" />
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <select value={formData.type} onChange={e => setFormData({...formData, type: e.target.value})} className="w-full border rounded-lg p-2 dark:bg-slate-800 dark:border-slate-700">
                            <option value="info">Info</option>
                            <option value="success">Sucesso</option>
                            <option value="warning">Aviso</option>
                            <option value="tip">Dica</option>
                        </select>
                        <input type="text" value={formData.action_url} onChange={e => setFormData({...formData, action_url: e.target.value})} placeholder="URL (ex: /simulados)" className="w-full border rounded-lg p-2 dark:bg-slate-800 dark:border-slate-700" />
                    </div>

                    <div className="pt-4 flex justify-end">
                        <button type="submit" disabled={mutation.isPending || !formData.title} className="px-6 py-2 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {mutation.isPending ? 'Enviando...' : 'Enviar Agora'}
                        </button>
                    </div>
                </form>
            </div>

            <div className="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div className="px-6 py-4 border-b border-slate-100 dark:border-slate-700">
                    <h2 className="font-bold">Log de Notificações Recentes</h2>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-slate-50 dark:bg-slate-800 text-slate-500">
                            <tr>
                                <th className="px-6 py-3 font-semibold">Destinatário</th>
                                <th className="px-6 py-3 font-semibold">Conteúdo</th>
                                <th className="px-6 py-3 font-semibold">Tipo</th>
                                <th className="px-6 py-3 font-semibold">Data</th>
                                <th className="px-6 py-3 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {logsLoading ? (
                                <tr><td colSpan={5} className="px-6 py-8 text-center text-slate-400">Carregando logs...</td></tr>
                            ) : sentNotifications.length === 0 ? (
                                <tr><td colSpan={5} className="px-6 py-8 text-center text-slate-400">Nenhuma notificação enviada ainda.</td></tr>
                            ) : sentNotifications.map((n: any) => (
                                <tr key={n.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                    <td className="px-6 py-4">
                                        {n.user ? (
                                            <div>
                                                <p className="font-medium text-slate-900 dark:text-slate-100">{n.user.name}</p>
                                                <p className="text-xs text-slate-500">{n.user.email}</p>
                                            </div>
                                        ) : <span className="text-slate-400 italic">Broadcast</span>}
                                    </td>
                                    <td className="px-6 py-4 max-w-xs">
                                        <p className="font-semibold truncate">{n.title}</p>
                                        <p className="text-xs text-slate-500 truncate">{n.body}</p>
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${
                                            n.type === 'success' ? 'bg-green-100 text-green-700' :
                                            n.type === 'warning' ? 'bg-yellow-100 text-yellow-700' :
                                            n.type === 'tip' ? 'bg-purple-100 text-purple-700' :
                                            'bg-blue-100 text-blue-700'
                                        }`}>
                                            {n.type}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-slate-500 whitespace-nowrap">
                                        {new Date(n.created_at).toLocaleString('pt-BR')}
                                    </td>
                                    <td className="px-6 py-4">
                                        {n.is_read ? 
                                            <span className="text-green-600 font-medium">Lida</span> : 
                                            <span className="text-slate-400">Não lida</span>
                                        }
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

function BannerForm({ banner, onClose, onSuccess }: { banner?: any, onClose: () => void, onSuccess: () => void }) {
    const [formData, setFormData] = useState({
        title: banner?.title || '',
        body: banner?.body || '',
        display_type: banner?.display_type || 'modal',
        target_segment: banner?.target_segment || 'all',
        frequency: banner?.frequency || 'once',
        background_color: banner?.background_color || '',
        button_text: banner?.button_text || '',
        button_url: banner?.button_url || '',
        is_active: banner ? banner.is_active : true,
    });
    const [image, setImage] = useState<File | null>(null);

    const mutation = useMutation({
        mutationFn: (data: FormData) => banner ? adminUpdateBanner(banner.id, data) : adminCreateBanner(data),
        onSuccess,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const fd = new FormData();
        Object.entries(formData).forEach(([k, v]) => fd.append(k, String(v)));
        if (image) fd.append('image', image);
        mutation.mutate(fd);
    };

    return (
        <div className="bg-white dark:bg-slate-900 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 mb-6">
            <h2 className="text-lg font-bold mb-4">{banner ? 'Editar Comunicado' : 'Novo Comunicado'}</h2>
            <form onSubmit={handleSubmit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="col-span-1 md:col-span-2">
                    <label className="block text-sm font-medium mb-1">Título *</label>
                    <input type="text" value={formData.title} onChange={e => setFormData({...formData, title: e.target.value})} required className="w-full border rounded-lg p-2 dark:bg-slate-800 dark:border-slate-700" />
                </div>
                <div className="col-span-1 md:col-span-2">
                    <label className="block text-sm font-medium mb-1">Corpo (Mensagem)</label>
                    <textarea value={formData.body} onChange={e => setFormData({...formData, body: e.target.value})} rows={3} className="w-full border rounded-lg p-2 dark:bg-slate-800 dark:border-slate-700" />
                </div>
                <div>
                    <label className="block text-sm font-medium mb-1">Tipo de Exibição</label>
                    <select value={formData.display_type} onChange={e => setFormData({...formData, display_type: e.target.value})} className="w-full border rounded-lg p-2 dark:bg-slate-800 dark:border-slate-700">
                        <option value="modal">Modal (Overlay completo)</option>
                        <option value="bar">Barra (Topo da tela)</option>
                        <option value="notification">Notificação (Lateral)</option>
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium mb-1">Segmento Alvo</label>
                    <select value={formData.target_segment} onChange={e => setFormData({...formData, target_segment: e.target.value})} className="w-full border rounded-lg p-2 dark:bg-slate-800 dark:border-slate-700">
                        <option value="all">Todos os usuários</option>
                        <option value="new">Novos (últimos 7 dias)</option>
                        <option value="inactive">Inativos (+7 dias sem login)</option>
                        <option value="free">Sem plano pago</option>
                        <option value="premium">Plano Premium ativo</option>
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium mb-1">Frequência</label>
                    <select value={formData.frequency} onChange={e => setFormData({...formData, frequency: e.target.value})} className="w-full border rounded-lg p-2 dark:bg-slate-800 dark:border-slate-700">
                        <option value="once">Apenas uma vez</option>
                        <option value="until_close">Sempre, até o usuário fechar</option>
                        <option value="always">Sempre mostrar (mesmo fechando)</option>
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium mb-1">Imagem Banner (Opcional)</label>
                    <input type="file" accept="image/*" onChange={e => setImage(e.target.files?.[0] || null)} className="w-full text-sm mt-1" />
                </div>
                <div>
                    <label className="block text-sm font-medium mb-1">Texto do Botão (Opcional)</label>
                    <input type="text" value={formData.button_text} onChange={e => setFormData({...formData, button_text: e.target.value})} className="w-full border rounded-lg p-2 dark:bg-slate-800 dark:border-slate-700" />
                </div>
                <div>
                    <label className="block text-sm font-medium mb-1">URL do Botão (Opcional)</label>
                    <input type="url" value={formData.button_url} onChange={e => setFormData({...formData, button_url: e.target.value})} className="w-full border rounded-lg p-2 dark:bg-slate-800 dark:border-slate-700" />
                </div>
                <div className="col-span-1 md:col-span-2 flex items-center mt-2">
                    <input type="checkbox" checked={formData.is_active} onChange={e => setFormData({...formData, is_active: e.target.checked})} className="mr-2 w-4 h-4" />
                    <label className="text-sm font-medium">Ativar imediatamente</label>
                </div>
                <div className="col-span-1 md:col-span-2 flex justify-end gap-3 pt-4 border-t dark:border-slate-700 mt-2">
                    <button type="button" onClick={onClose} className="px-4 py-2 font-medium text-slate-600 hover:bg-slate-100 rounded-lg">Cancelar</button>
                    <button type="submit" disabled={mutation.isPending} className="px-6 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 disabled:opacity-50">
                        {mutation.isPending ? 'Salvando...' : 'Salvar Comunicado'}
                    </button>
                </div>
            </form>
        </div>
    );
}
