import { toast } from 'sonner';
import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../api/axios';

export default function Integrations() {
    const queryClient = useQueryClient();

    // State matching Blade's variables
    const [googleLoginEnabled, setGoogleLoginEnabled] = useState(false);
    const [googleClientId, setGoogleClientId] = useState('');
    const [googleClientSecret, setGoogleClientSecret] = useState('');
    const [googleRedirectUri, setGoogleRedirectUri] = useState('');

    const [analyticsEnabled, setAnalyticsEnabled] = useState(false);
    const [analyticsMeasurementId, setAnalyticsMeasurementId] = useState('');
    const [analyticsPropertyId, setAnalyticsPropertyId] = useState('');
    const [analyticsSyncFrequency, setAnalyticsSyncFrequency] = useState('daily');
    const [hasServiceAccount, setHasServiceAccount] = useState(false);

    const [serviceAccountFile, setServiceAccountFile] = useState<File | null>(null);
    const [syncing, setSyncing] = useState(false);

    const handleSync = async () => {
        setSyncing(true);
        try {
            const res = await api.post('/api/v1/admin/analytics/sync');
            toast.success(res.data.message || 'Sincronização iniciada!');
        } catch {
            toast.error('Erro ao iniciar sincronização.');
        } finally {
            setSyncing(false);
        }
    };

    const { data, isLoading } = useQuery({
        queryKey: ['admin-integrations'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/integrations');
            return res.data;
        }
    });

    useEffect(() => {
        if (data) {
            setGoogleLoginEnabled(!!data.google_login_enabled);
            setGoogleClientId(data.google_client_id || '');
            setGoogleRedirectUri(data.google_redirect_uri || '');

            setAnalyticsEnabled(!!data.analytics_enabled);
            setAnalyticsMeasurementId(data.analytics_measurement_id || '');
            setAnalyticsPropertyId(data.analytics_property_id || '');
            setAnalyticsSyncFrequency(data.analytics_sync_frequency || 'daily');
            setHasServiceAccount(!!data.has_service_account);
        }
    }, [data]);

    const updateMutation = useMutation({
        mutationFn: async (formData: FormData) => {
            const res = await api.post('/api/v1/admin/integrations', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            return res.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-integrations'] });
            toast.success('Configurações atualizadas com sucesso!');
        },
        onError: (error: any) => {
            const message = error.response?.data?.message || 'Erro ao atualizar integrações.';
            toast.error(message);
        }
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const formData = new FormData();

        formData.append('google_login_enabled', googleLoginEnabled ? '1' : '0');
        formData.append('google_client_id', googleClientId);
        if (googleClientSecret) formData.append('google_client_secret', googleClientSecret);

        formData.append('analytics_enabled', analyticsEnabled ? '1' : '0');
        formData.append('analytics_measurement_id', analyticsMeasurementId);
        formData.append('analytics_property_id', analyticsPropertyId);
        formData.append('analytics_sync_frequency', analyticsSyncFrequency);

        if (serviceAccountFile) {
            formData.append('analytics_service_account_json', serviceAccountFile);
        }

        updateMutation.mutate(formData);
    };

    if (isLoading) return <div className="p-8">Carregando integrações...</div>;

    return (
        <div className="py-6 px-4 md:px-6 w-full">
            <div className="mb-8">
                <h1 className="text-2xl font-bold text-gray-800">Integrações</h1>
                <p className="text-gray-600">Gerencie as integrações externas do sistema.</p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-8">
                {/* Google Login */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div className="p-6 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            <div className="w-12 h-12 bg-white rounded-lg shadow-sm flex items-center justify-center">
                                <svg className="w-6 h-6 text-gray-700" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12.545,10.239v3.821h5.445c-0.712,2.315-2.647,3.972-5.445,3.972c-3.332,0-6.033-2.701-6.033-6.032s2.701-6.032,6.033-6.032c1.498,0,2.866,0.549,3.921,1.453l2.814-2.814C17.503,2.988,15.139,2,12.545,2C7.021,2,2.543,6.477,2.543,12s4.478,10,10.002,10c8.396,0,10.249-7.85,9.426-11.748L12.545,10.239z" />
                                </svg>
                            </div>
                            <div>
                                <h2 className="text-lg font-semibold text-gray-800">Google Login</h2>
                                <p className="text-sm text-gray-500">Permitir que usuários façam login com suas contas do Google.</p>
                            </div>
                        </div>
                        <label className="relative inline-flex items-center cursor-pointer">
                            <input
                                type="checkbox"
                                checked={googleLoginEnabled}
                                onChange={(e) => setGoogleLoginEnabled(e.target.checked)}
                                className="sr-only peer"
                            />
                            <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>

                    <div className="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">Google Client ID</label>
                            <input
                                type="text"
                                value={googleClientId}
                                onChange={(e) => setGoogleClientId(e.target.value)}
                                className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Ex: 123456789-abcdef..."
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">Google Client Secret</label>
                            <input
                                type="password"
                                value={googleClientSecret}
                                onChange={(e) => setGoogleClientSecret(e.target.value)}
                                className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Deixe em branco para manter o atual"
                            />
                        </div>
                        <div className="md:col-span-2">
                            <label className="block text-sm font-medium text-gray-700 mb-2">Redirect URI</label>
                            <input
                                type="url"
                                value={googleRedirectUri}
                                readOnly
                                className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 bg-gray-50 text-gray-500"
                            />
                            <p className="text-xs text-gray-500 mt-1">Adicione esta URL no console do Google Cloud.</p>
                        </div>
                    </div>
                </div>

                {/* Google Analytics */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div className="p-6 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            <div className="w-12 h-12 bg-white rounded-lg shadow-sm flex items-center justify-center">
                                <svg className="w-6 h-6 text-orange-500" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M4 19h4v-7H4v7zm6 0h4V9h-4v10zm6 0h4v-4h-4v4zm2-16C9.58 3 3 8 3 14c0 1.54.55 2.96 1.48 4.09l1.52-1.28C5.45 15.93 5 15.02 5 14c0-4.42 4.93-8 11-8s11 3.58 11 8c0 1.02-.45 1.93-1 2.81l1.52 1.28C22.45 16.96 23 15.54 23 14c0-6-6.58-11-14-11z" />
                                    <path d="M0 0h24v24H0z" fill="none" />
                                </svg>
                            </div>
                            <div>
                                <h2 className="text-lg font-semibold text-gray-800">Google Analytics 4</h2>
                                <p className="text-sm text-gray-500">Acompanhe o tráfego do site com o Google Analytics 4.</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-3">
                            {analyticsEnabled && hasServiceAccount && (
                                <button
                                    type="button"
                                    onClick={handleSync}
                                    disabled={syncing}
                                    className="px-3 py-1.5 bg-indigo-50 border border-indigo-200 text-indigo-700 text-xs font-bold rounded-lg hover:bg-indigo-100 transition-colors flex items-center gap-2 disabled:opacity-50"
                                >
                                    {syncing ? (
                                        <span className="w-3 h-3 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin" />
                                    ) : (
                                        <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                    )}
                                    Sincronizar Agora
                                </button>
                            )}
                            <label className="relative inline-flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={analyticsEnabled}
                                    onChange={(e) => setAnalyticsEnabled(e.target.checked)}
                                    className="sr-only peer"
                                />
                                <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                    </div>

                    <div className="p-6">
                        {/* Measurement ID */}
                        <div className="mb-4">
                            <label className="block text-sm font-medium text-gray-700 mb-2">Measurement ID (G-XXXXXXXXXX)</label>
                            <input
                                type="text"
                                value={analyticsMeasurementId}
                                onChange={(e) => setAnalyticsMeasurementId(e.target.value)}
                                className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="G-ABC1234567"
                            />
                            <p className="text-xs text-gray-500 mt-1">Usado para rastreamento front-end nas páginas.</p>
                        </div>

                        <hr className="my-6 border-gray-100" />
                        <h3 className="text-md font-medium text-gray-800 mb-4">Módulo de Analytics Avançado (Painel)</h3>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {/* Property ID */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Property ID (GA4)</label>
                                <input
                                    type="text"
                                    value={analyticsPropertyId}
                                    onChange={(e) => setAnalyticsPropertyId(e.target.value)}
                                    className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="Ex: 123456789"
                                />
                                <p className="text-xs text-gray-500 mt-1">ID da Propriedade no Google Analytics 4 (Apenas números).</p>
                            </div>

                            {/* Sync Frequency */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Frequência de Sincronização</label>
                                <select
                                    value={analyticsSyncFrequency}
                                    onChange={(e) => setAnalyticsSyncFrequency(e.target.value)}
                                    className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="daily">Apenas Diária</option>
                                    <option value="hourly">Hora em Hora</option>
                                </select>
                                <p className="text-xs text-gray-500 mt-1">Define com que frequência os jobs rodam em background.</p>
                            </div>

                            {/* Service Account JSON */}
                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-gray-700 mb-2">Service Account (JSON)</label>
                                <div className="flex items-center gap-4">
                                    <input
                                        type="file"
                                        accept=".json"
                                        onChange={(e) => setServiceAccountFile(e.target.files?.[0] || null)}
                                        className="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                    />

                                    {hasServiceAccount ? (
                                        <span className="inline-flex items-center gap-1.5 py-1.5 px-3 rounded-md text-xs font-medium bg-green-50 text-green-700 border border-green-200 whitespace-nowrap">
                                            <svg className="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                            </svg>
                                            Arquivo Configurado
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1.5 py-1.5 px-3 rounded-md text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200 whitespace-nowrap">
                                            Não Configurado
                                        </span>
                                    )}
                                </div>
                                <p className="text-xs text-gray-500 mt-1">Faça upload do JSON de credenciais da Service Account gerada no Google Cloud Consle.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="flex justify-end">
                    <button
                        type="submit"
                        disabled={updateMutation.isPending}
                        className="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-6 rounded-lg transition-colors shadow-sm disabled:opacity-50">
                        {updateMutation.isPending ? 'Salvando...' : 'Salvar Configurações'}
                    </button>
                </div>
            </form>
        </div>
    );
}
