import { toast } from 'sonner';
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { motion, AnimatePresence } from 'framer-motion';
import api from '../../api/axios';
import { useConfigStore } from '../../stores/configStore';

// Modular Components
import { VaultKey, ApiKey, AiLog, AiRanking } from './api-keys/Types';
import ApiAnalyticsDashboard from './api-keys/ApiAnalyticsDashboard';
import VaultManagement from './api-keys/VaultManagement';
import ApiRoutingConfig from './api-keys/ApiRoutingConfig';
import FailoverPriorities from './api-keys/FailoverPriorities';
import ApiAuditLogs from './api-keys/ApiAuditLogs';
import ApiModals from './api-keys/ApiModals';


export function AdminApiKeys() {
    const { aiName } = useConfigStore();
    const queryClient = useQueryClient();

    // UI Local State (New Tab System)
    const [activeTab, setActiveTab] = useState<'dashboard' | 'management' | 'failover' | 'audit'>('dashboard');
    const [activeSubTab, setActiveSubTab] = useState<string>('list');

    // Modals
    const [showModelModal, setShowModelModal] = useState(false);
    const [showLogModal, setShowLogModal] = useState(false);
    const [activeLog, setActiveLog] = useState<AiLog | null>(null);
    const [showRawResponse, setShowRawResponse] = useState(false);
    const [discoveredModels, setDiscoveredModels] = useState<any[]>([]);

    // Form States
    const [vaultForm, setVaultForm] = useState({ nickname: '', provider: 'gemini', key: '' });
    const [editingVaultId, setEditingVaultId] = useState<number | null>(null);
    const [routingForm, setRoutingForm] = useState({ vault_id: '', preferred_model: '', capabilities: [] as string[] });
    const [discoveryLoading, setDiscoveryLoading] = useState(false);

    // Analytics Filters State
    const [filters, setFilters] = useState({
        start_date: new Date(Date.now() - 14 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
        end_date: new Date().toISOString().split('T')[0],
        vault_id: '',
        model: '',
        module: ''
    });

    const { data, isLoading, isError, error, refetch, isFetching } = useQuery({
        queryKey: ['admin-api-keys', filters],
        queryFn: async () => {
            const params = new URLSearchParams();
            if (filters.start_date) params.append('start_date', filters.start_date);
            if (filters.end_date) params.append('end_date', filters.end_date);
            if (filters.vault_id) params.append('vault_id', filters.vault_id);
            if (filters.model) params.append('model', filters.model);
            if (filters.module) params.append('module', filters.module);

            const res = await api.get(`/api/v1/admin/api-keys?${params.toString()}`);
            return res.data;
        },
        retry: 1,
        refetchInterval: 3600000 // 1 hour auto-refresh
    });

    const handleManualRefresh = async () => {
        try {
            toast.loading('Sincronizando dados com provedores...', { id: 'api-sync' });
            await api.get('/api/v1/admin/api-keys?refresh=1');
            await refetch();
            toast.success('Dados atualizados com sucesso!', { id: 'api-sync' });
        } catch (err) {
            toast.error('Erro ao sincronizar dados.', { id: 'api-sync' });
        }
    };

    // Mutations
    const addVaultMutation = useMutation({
        mutationFn: async (payload: any) => {
            if (editingVaultId) {
                return api.put(`/api/v1/admin/api-keys/vault/${editingVaultId}`, payload);
            }
            return api.post('/api/v1/admin/api-keys/vault', payload);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-api-keys'] });
            setVaultForm({ nickname: '', provider: 'gemini', key: '' });
            setEditingVaultId(null);
            toast.success(editingVaultId ? 'Chave atualizada com sucesso!' : 'Chave guardada no cofre!');
        },
        onError: (err: any) => {
            toast.error(err.response?.data?.message || 'Erro ao salvar chave no cofre.');
        }
    });

    const deleteVaultMutation = useMutation({
        mutationFn: async (id: number) => api.delete(`/api/v1/admin/api-keys/vault/${id}`),
        onSuccess: (res) => {
            queryClient.invalidateQueries({ queryKey: ['admin-api-keys'] });
            toast.success(res.data.message || 'Chave removida do cofre.');
        },
        onError: (err: any) => {
            toast.error(err.response?.data?.message || 'Erro ao remover chave.');
        }
    });

    const discoverModelsMutation = useMutation({
        mutationFn: async (vaultId: string) => api.post('/api/v1/admin/api-keys/discover', { vault_id: vaultId }),
        onSuccess: (res) => {
            if (res.data.is_valid && res.data.models) {
                setDiscoveredModels(res.data.models);
                setShowModelModal(true);
            } else {
                toast.info(res.data.error || 'Falha na descoberta de modelos.');
            }
        },
        onError: (err: any) => {
            const msg = err.response?.data?.error || err.message || 'Erro desconhecido na API.';
            toast.error('Erro Crítico: ' + msg);
        },
        onSettled: () => setDiscoveryLoading(false)
    });

    const activateRoutingMutation = useMutation({
        mutationFn: async (payload: any) => api.post('/api/v1/admin/api-keys', payload),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-api-keys'] });
            queryClient.invalidateQueries({ queryKey: ['admin-triage-active-keys'] });
            setRoutingForm({ vault_id: '', preferred_model: '', capabilities: [] });
            toast.success('Roteamento ativado com sucesso!');
        },
        onError: (err: any) => {
            const msg = err.response?.data?.message || err.response?.data?.error || err.message || 'Erro desconhecido ao ativar roteamento.';
            toast.error('Falha ao ativar roteamento: ' + msg);
        }
    });

    const updatePriorityMutation = useMutation({
        mutationFn: async (payload: { capability: string, ordered_ids: number[] }) =>
            api.post('/api/v1/admin/api-keys/priority', payload),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin-api-keys'] })
    });

    const retestMutation = useMutation({
        mutationFn: async (id: number) => api.post(`/api/v1/admin/api-keys/${id}/retest`),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin-api-keys'] })
    });

    const deleteApiKeyMutation = useMutation({
        mutationFn: async (id: number) => api.delete(`/api/v1/admin/api-keys/${id}`),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin-api-keys'] })
    });

    const handleDiscover = () => {
        if (!routingForm.vault_id) return;
        setDiscoveryLoading(true);
        discoverModelsMutation.mutate(routingForm.vault_id);
    };

    const handlePriorityReorder = (capability: string, newOrder: ApiKey[]) => {
        const orderedIds = newOrder
            .map(item => item?.pivot?.id)
            .filter(id => id !== undefined && id !== null) as number[];

        if (orderedIds.length > 0) {
            updatePriorityMutation.mutate({ capability, ordered_ids: orderedIds });
        }
    };

    if (isLoading) return (
        <div className="p-12 flex flex-col items-center justify-center min-h-[400px] text-slate-500 gap-4">
            <div className="w-10 h-10 border-4 border-indigo-100 border-t-indigo-600 rounded-full animate-spin"></div>
            <p className="font-bold tracking-tight animate-pulse">Invocando infraestrutura SRE...</p>
        </div>
    );

    if (isError) return (
        <div className="p-12 flex flex-col items-center justify-center min-h-[400px] text-red-500 gap-4 bg-red-50 rounded-3xl m-8">
            <span className="text-4xl">🚫</span>
            <div className="text-center">
                <p className="font-black text-xl mb-2">Falha Crítica na Comunicação</p>
                <p className="text-sm font-medium opacity-70">{(error as any)?.response?.data?.message || (error as any)?.message || 'Ocorreu um erro ao carregar as chaves de API.'}</p>
            </div>
            <button
                onClick={() => queryClient.invalidateQueries({ queryKey: ['admin-api-keys'] })}
                className="mt-4 px-6 py-2 bg-red-600 text-white font-bold rounded-xl hover:bg-red-700 transition-all shadow-lg shadow-red-100"
            >
                Tentar Novamente
            </button>
        </div>
    );

    if (!data) return null;

    const vaultKeys: VaultKey[] = data?.vault_keys || [];
    const availableCapabilities: Record<string, string> = data?.available_capabilities || {};
    const capabilitiesGrid: Record<string, ApiKey[]> = data?.capabilities_grid || {};
    const aiLogs: AiLog[] = data?.ai_logs || [];
    const aiRanking: AiRanking[] = data?.ai_ranking || [];

    const capabilityMeta: Record<string, { icon: string, description: string }> = {
        chat_tutor: { icon: '💬', description: 'Responde dúvidas dos alunos sobre questões resolvidas.' },
        questions: { icon: '✍️', description: 'Gera questões inéditas, explicações e gabaritos comentados.' },
        triage: { icon: '⚙️', description: 'Classifica e modera questões durante o processamento em lote.' },
        search: { icon: '🔍', description: 'Interpreta buscas em linguagem natural na barra de pesquisa.' }
    };

    return (
        <div className="p-4 md:p-6 w-full space-y-6 bg-slate-50/30 min-h-screen">
            {/* Header */}
            <div className="flex flex-col md:flex-row justify-between items-start gap-4">
                <div>
                    <h1 className="text-3xl font-black text-slate-800 tracking-tight">SRE Dashboard: APIs 🤖</h1>
                    <div className="flex items-center gap-4 mt-1">
                        <p className="text-slate-500 font-medium">Monitoramento e controle de provedores de IA para o {aiName}.</p>
                        <button
                            onClick={handleManualRefresh}
                            disabled={isFetching}
                            className={`flex items-center gap-2 px-3 py-1 bg-white border border-slate-200 rounded-full text-[10px] font-black uppercase tracking-widest text-indigo-600 hover:bg-slate-50 transition-all shadow-sm ${isFetching ? 'opacity-50 cursor-not-allowed' : ''}`}>
                            <svg className={`w-3 h-3 ${isFetching ? 'animate-spin' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                            {isFetching ? 'Sincronizando...' : 'Sincronizar Dados'}
                        </button>
                    </div>
                </div>
                {data.has_recent_errors && (
                    <motion.div
                        initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }}
                        className="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-xl shadow-sm animate-pulse">
                        <div className="flex items-center gap-3">
                            <span className="text-red-500 text-xl font-bold">⚠️</span>
                            <p className="text-sm font-bold text-red-700">ALERTA CRÍTICO: Erros detectados nas últimas 6h.</p>
                        </div>
                    </motion.div>
                )}
            </div>

            {/* Premium Tab Navigation */}
            <div className="bg-white p-1.5 rounded-2xl shadow-sm border border-slate-100 flex gap-1 sticky top-4 z-40 backdrop-blur-xl bg-white/80">
                {[
                    { id: 'dashboard', label: 'Dashboard', icon: '📊' },
                    { id: 'management', label: 'Gerenciamento', icon: '⚙️' },
                    { id: 'failover', label: 'Failover & Prioridade', icon: '🛡️' },
                    { id: 'audit', label: 'Auditoria & Logs', icon: '📋' },
                ].map((tab) => (
                    <button
                        key={tab.id}
                        onClick={() => setActiveTab(tab.id as any)}
                        className={`flex-1 flex items-center justify-center gap-2 py-3 rounded-xl font-bold text-xs transition-all ${
                            activeTab === tab.id
                                ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100 scale-[1.02]'
                                : 'text-slate-400 hover:bg-slate-50 hover:text-slate-600'
                        }`}
                    >
                        <span>{tab.icon}</span>
                        <span className="hidden md:inline">{tab.label}</span>
                    </button>
                ))}
            </div>

            <AnimatePresence mode="wait">
                <motion.div
                    key={activeTab}
                    initial={{ opacity: 0, y: 10 }}
                    animate={{ opacity: 1, y: 0 }}
                    exit={{ opacity: 0, y: -10 }}
                    transition={{ duration: 0.2 }}
                >
                    {activeTab === 'dashboard' && (
                        <ApiAnalyticsDashboard
                            daily={data.analytics_daily || []}
                            modules={data.analytics_modules || []}
                            vaultKeys={vaultKeys}
                            availableCapabilities={availableCapabilities}
                            aiLogs={aiLogs} 
                            aiRanking={aiRanking} 
                            filters={filters}
                            onFilterChange={setFilters}
                            isFetching={isFetching}
                        />
                    )}

                    {activeTab === 'management' && (
                        <VaultManagement
                            activeSubTab={activeSubTab}
                            setActiveSubTab={setActiveSubTab}
                            vaultKeys={vaultKeys}
                            editingVaultId={editingVaultId}
                            setEditingVaultId={setEditingVaultId}
                            vaultForm={vaultForm}
                            setVaultForm={setVaultForm}
                            addVaultMutation={addVaultMutation}
                            deleteVaultMutation={deleteVaultMutation}
                        />
                    )}

                    {activeTab === 'management' && activeSubTab === 'routing' && (
                        <ApiRoutingConfig
                            routingForm={routingForm}
                            setRoutingForm={setRoutingForm}
                            vaultKeys={vaultKeys}
                            availableCapabilities={availableCapabilities}
                            discoveryLoading={discoveryLoading}
                            handleDiscover={handleDiscover}
                            activateRoutingMutation={activateRoutingMutation}
                        />
                    )}

                    {activeTab === 'failover' && (
                        <FailoverPriorities
                            availableCapabilities={availableCapabilities}
                            capabilitiesGrid={capabilitiesGrid}
                            handlePriorityReorder={handlePriorityReorder}
                            retestMutation={retestMutation}
                            deleteApiKeyMutation={deleteApiKeyMutation}
                        />
                    )}

                    {activeTab === 'audit' && (
                        <ApiAuditLogs
                            aiLogs={aiLogs}
                            setActiveLog={setActiveLog}
                            setShowLogModal={setShowLogModal}
                        />
                    )}
                </motion.div>
            </AnimatePresence>

            <ApiModals
                showModelModal={showModelModal}
                setShowModelModal={setShowModelModal}
                discoveredModels={discoveredModels}
                routingForm={routingForm}
                setRoutingForm={setRoutingForm}
                showLogModal={showLogModal}
                setShowLogModal={setShowLogModal}
                activeLog={activeLog}
                showRawResponse={showRawResponse}
                setShowRawResponse={setShowRawResponse}
            />
        </div>
    );
}

export default AdminApiKeys;
