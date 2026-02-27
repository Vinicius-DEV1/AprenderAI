import { useState } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import api from '../../api/axios';
import { AdminPageSkeleton } from './components/AdminSkeletons';

export default function CacheSettings() {
    const [siteName, setSiteName] = useState('');
    const [aiName, setAiName] = useState('Xavier');
    const [aiStreamingEnabled, setAiStreamingEnabled] = useState(false);

    const { data: settingsData, isLoading } = useQuery({
        queryKey: ['admin-settings'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/settings');
            if (res.data) {
                setSiteName(res.data.site_name || '');
                setAiName(res.data.ai_name || 'Xavier');
                setAiStreamingEnabled(res.data.ai_streaming_enabled === 'true' || res.data.ai_streaming_enabled === true);
            }
            return res.data;
        }
    });

    const updateMutation = useMutation({
        mutationFn: async (payload: any) => {
            const res = await api.post('/api/v1/admin/settings', payload);
            return res.data;
        },
        onSuccess: () => {
            alert('Configurações salvas com sucesso!');
        },
        onError: () => {
            alert('Erro ao salvar as configurações.');
        }
    });

    const clearCacheMutation = useMutation({
        mutationFn: async (type: string = 'all') => {
            const res = await api.post('/api/v1/admin/settings/clear-cache', { type });
            return res.data;
        },
        onSuccess: (data) => {
            alert(data.message || 'Cache limpo com sucesso!');
        },
        onError: () => {
            alert('Erro ao tentar limpar o cache do servidor.');
        }
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        updateMutation.mutate({
            site_name: siteName,
            ai_name: aiName,
            ai_streaming_enabled: aiStreamingEnabled ? 'true' : 'false'
        });
    };

    if (isLoading) return <AdminPageSkeleton />;

    return (
        <div className="max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8 animate-in fade-in duration-500">
            <div className="mb-10 flex justify-between items-end">
                <div>
                    <h1 className="text-3xl font-black text-gray-900 mb-2 uppercase tracking-tighter">Configurações de Sistema</h1>
                    <p className="text-gray-500 font-medium">Controle global de marca, IA e performance</p>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-8">
                {/* Brand & AI Config */}
                <div className="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div className="p-6 border-b border-gray-50 bg-gray-50/30">
                        <h3 className="text-xs font-black text-gray-900 uppercase tracking-widest">Identidade & Inteligência</h3>
                    </div>
                    <form onSubmit={handleSubmit} className="p-8 space-y-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="space-y-1 text-left">
                                <label className="text-[10px] font-black text-gray-400 uppercase ml-1">Nome da Plataforma</label>
                                <input
                                    type="text"
                                    value={siteName}
                                    onChange={(e) => setSiteName(e.target.value)}
                                    className="w-full rounded-xl border-gray-100 bg-gray-50 font-bold text-sm focus:ring-indigo-500 focus:border-indigo-500 px-4 py-3"
                                    placeholder="Ex: aprenderAI"
                                />
                            </div>

                            <div className="space-y-1 text-left">
                                <label className="text-[10px] font-black text-gray-400 uppercase ml-1">Nome da IA ({aiName})</label>
                                <input
                                    type="text"
                                    value={aiName}
                                    onChange={(e) => setAiName(e.target.value)}
                                    className="w-full rounded-xl border-gray-100 bg-gray-50 font-bold text-sm focus:ring-indigo-500 focus:border-indigo-500 px-4 py-3"
                                />
                            </div>
                        </div>

                        <div className="p-6 bg-indigo-50/50 rounded-2xl border border-indigo-100 flex items-center justify-between text-left">
                            <div>
                                <h4 className="text-sm font-black text-indigo-900 uppercase tracking-tight">Efeito Typewriter (Streaming)</h4>
                                <p className="text-xs text-indigo-700 font-medium">Faz a IA responder letra por letra em tempo real.</p>
                            </div>
                            <label className="relative inline-flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={aiStreamingEnabled}
                                    onChange={(e) => setAiStreamingEnabled(e.target.checked)}
                                    className="sr-only peer"
                                />
                                <div className="w-14 h-7 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[4px] after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>

                        <div className="flex justify-end pt-4">
                            <button
                                type="submit"
                                disabled={updateMutation.isPending}
                                className="bg-indigo-600 text-white px-10 py-3 rounded-xl hover:bg-indigo-700 transition-all font-black text-xs uppercase tracking-widest shadow-md shadow-indigo-100 disabled:opacity-50">
                                {updateMutation.isPending ? 'SALVANDO...' : 'SALVAR ALTERAÇÕES'}
                            </button>
                        </div>
                    </form>
                </div>

                {/* Infrastructure & Cache */}
                <div className="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div className="p-6 border-b border-gray-50 bg-gray-50/30 flex justify-between items-center">
                        <h3 className="text-xs font-black text-gray-900 uppercase tracking-widest">Infraestrutura & Performance</h3>
                        <div className="flex gap-2">
                            <span className="text-[9px] font-black bg-gray-100 text-gray-500 px-2 py-0.5 rounded uppercase font-mono">v{settingsData?.version || '1.0.0'}</span>
                            <span className="text-[9px] font-black bg-blue-100 text-blue-600 px-2 py-0.5 rounded uppercase">{settingsData?.environment || 'PROD'}</span>
                        </div>
                    </div>

                    <div className="p-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div className="bg-gray-50 p-4 rounded-2xl border border-gray-100 text-left">
                            <span className="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-2">Driver de Cache</span>
                            <span className="text-sm font-black text-gray-800 font-mono uppercase">{settingsData?.cache_driver || 'redis'}</span>
                        </div>

                        <div className="md:col-span-2 space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <button
                                    onClick={() => clearCacheMutation.mutate('all')}
                                    disabled={clearCacheMutation.isPending}
                                    className="p-4 bg-orange-50 border border-orange-100 text-orange-700 rounded-2xl hover:bg-orange-100 transition-all text-left group">
                                    <div className="text-[10px] font-black uppercase tracking-widest mb-1 group-hover:translate-x-1 transition-transform">Limpeza Geral</div>
                                    <div className="text-xs font-medium opacity-70">Limpa Application, View, Config e Routes</div>
                                </button>

                                <button
                                    onClick={() => clearCacheMutation.mutate('view')}
                                    disabled={clearCacheMutation.isPending}
                                    className="p-4 bg-blue-50 border border-blue-100 text-blue-700 rounded-2xl hover:bg-blue-100 transition-all text-left group">
                                    <div className="text-[10px] font-black uppercase tracking-widest mb-1 group-hover:translate-x-1 transition-transform">Views & Templates</div>
                                    <div className="text-xs font-medium opacity-70">Força recompilação do Blade</div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
