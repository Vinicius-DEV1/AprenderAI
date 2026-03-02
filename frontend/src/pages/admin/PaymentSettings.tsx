import { toast } from 'sonner';
import { useState, useEffect } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import api from '../../api/axios';

export default function PaymentSettings() {
    const [formState, setFormState] = useState({
        asaas_production_api_key: '',
        asaas_production_webhook_token: '',
        asaas_sandbox_api_key: '',
        asaas_sandbox_webhook_token: '',
        asaas_sandbox: false,
        payment_active: false
    });

    const [copied, setCopied] = useState(false);
    const [validationErrors, setValidationErrors] = useState<any>({});
    const [activeTab, setActiveTab] = useState<'production' | 'sandbox'>('production');

    // Simulate webhook URL since we're in react
    const webhookUrl = `${window.location.origin}/api/v1/webhooks/asaas`;

    const { data: settings, isLoading } = useQuery({
        queryKey: ['admin-payment-settings'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/payment-settings');
            return res.data;
        }
    });

    useEffect(() => {
        if (settings) {
            setFormState({
                asaas_production_api_key: settings.asaas_production_api_key || '',
                asaas_production_webhook_token: settings.asaas_production_webhook_token || '',
                asaas_sandbox_api_key: settings.asaas_sandbox_api_key || '',
                asaas_sandbox_webhook_token: settings.asaas_sandbox_webhook_token || '',
                asaas_sandbox: settings.asaas_sandbox ?? false,
                payment_active: settings.payment_active ?? false
            });
            setActiveTab(settings.asaas_sandbox ? 'sandbox' : 'production');
        }
    }, [settings]);

    const updateMutation = useMutation({
        mutationFn: async (payload: any) => {
            const res = await api.put('/api/v1/admin/payment-settings', payload);
            return res.data;
        },
        onSuccess: () => {
            toast.success('Configurações salvas com sucesso!');
        },
        onError: (error: any) => {
            if (error.response?.data?.errors) {
                setValidationErrors(error.response.data.errors);
            } else {
                toast.error('Erro ao salvar as configurações.');
            }
        }
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setValidationErrors({});
        updateMutation.mutate(formState);
    };

    const copyWebhookUrl = () => {
        navigator.clipboard.writeText(webhookUrl).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2500);
        });
    };

    if (isLoading) return <div className="p-8">Carregando configurações...</div>;

    const getError = (field: string) => validationErrors[field] ? validationErrors[field][0] : null;

    const handleToggleEnv = (env: 'production' | 'sandbox') => {
        setFormState(prev => ({ ...prev, asaas_sandbox: env === 'sandbox' }));
        setActiveTab(env);
    };

    return (
        <div className="py-6 px-4 md:px-6 w-full space-y-8">
            {/* Header */}
            <div>
                <div className="flex items-center gap-4">
                    <h1 className="text-3xl font-bold text-gray-800 mb-2">Configurações de Pagamento</h1>
                    {/* Status Badge */}
                    {!formState.payment_active ? (
                        <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-red-100 text-red-700">
                            🔴 Pagamentos Desativados
                        </span>
                    ) : formState.asaas_sandbox ? (
                        <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-amber-100 text-amber-700">
                            🟡 Sistema em Sandbox
                        </span>
                    ) : (
                        <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-green-100 text-green-700">
                            🟢 Sistema em Produção
                        </span>
                    )}
                </div>
                <p className="text-gray-600">Gerencie a integração com o gateway Asaas e o ambiente de operação do sistema.</p>
            </div>

            {/* ENVIRONMENT TOGGLE (GIGANTE) */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 flex flex-col md:flex-row items-center justify-between gap-6">
                <div>
                    <h2 className="text-2xl font-bold text-gray-800 mb-2">Ambiente de Operação Atual</h2>
                    <p className="text-gray-500 max-w-xl">
                        Escolha qual ambiente o sistema deve usar agora. O ambiente selecionado ditará quais credenciais serão usadas no momento das cobranças e assinaturas.
                    </p>
                </div>

                <div className="flex bg-gray-100 p-1 rounded-2xl">
                    <button
                        type="button"
                        onClick={() => handleToggleEnv('production')}
                        className={`flex items-center gap-2 px-6 py-4 rounded-xl font-bold text-lg transition-all duration-200 ${!formState.asaas_sandbox ? 'bg-white text-green-700 shadow-md ring-1 ring-black/5' : 'text-gray-500 hover:text-gray-700'}`}
                    >
                        🚀 Produção
                    </button>
                    <button
                        type="button"
                        onClick={() => handleToggleEnv('sandbox')}
                        className={`flex items-center gap-2 px-6 py-4 rounded-xl font-bold text-lg transition-all duration-200 ${formState.asaas_sandbox ? 'bg-white text-amber-600 shadow-md ring-1 ring-black/5' : 'text-gray-500 hover:text-gray-700'}`}
                    >
                        🛠️ Sandbox
                    </button>
                </div>
            </div>

            {/* PARENT FORM CONTAINER */}
            <form onSubmit={handleSubmit} className="space-y-8">

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    {/* COLUNA DA ESQUERDA: CREDENCIAIS */}
                    <div className="space-y-6">
                        {/* Tab switcher para as credenciais apenas visualização dos forms */}
                        <div className="flex gap-2 border-b border-gray-200 pb-4">
                            <button
                                type="button"
                                onClick={() => setActiveTab('production')}
                                className={`pb-2 px-4 text-sm font-bold border-b-2 transition-colors ${activeTab === 'production' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}
                            >
                                Configurar Produção
                            </button>
                            <button
                                type="button"
                                onClick={() => setActiveTab('sandbox')}
                                className={`pb-2 px-4 text-sm font-bold border-b-2 transition-colors ${activeTab === 'sandbox' ? 'border-amber-500 text-amber-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}
                            >
                                Configurar Sandbox
                            </button>
                        </div>

                        {/* Form de Produção */}
                        <div className={activeTab === 'production' ? 'block' : 'hidden'}>
                            <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-6 ring-1 ring-green-500/10">
                                <div>
                                    <h3 className="text-lg font-bold text-gray-800 flex items-center gap-2">
                                        🚀 Credenciais de Produção
                                    </h3>
                                    <p className="text-sm text-gray-500 mt-1">Obtidas no Asaas em <strong className="text-gray-700">Minha Conta → Integração</strong>.</p>
                                </div>
                                <div className="space-y-4">
                                    {/* API Key Section */}
                                    <div>
                                        <label htmlFor="asaas_production_api_key" className="block text-sm font-semibold text-gray-700 mb-1">
                                            Chave da API (API Key)
                                        </label>
                                        <input
                                            type="text"
                                            id="asaas_production_api_key"
                                            value={formState.asaas_production_api_key}
                                            onChange={(e) => setFormState({ ...formState, asaas_production_api_key: e.target.value })}
                                            className={`focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-lg p-3 bg-gray-50 ${getError('asaas_production_api_key') ? 'border-red-300' : ''}`}
                                            placeholder="Ex: $aact_..."
                                            autoComplete="off"
                                        />
                                        {getError('asaas_production_api_key') && <p className="text-sm text-red-600 mt-1">{getError('asaas_production_api_key')}</p>}
                                    </div>
                                    {/* Webhook Token Section */}
                                    <div>
                                        <label htmlFor="asaas_production_webhook_token" className="block text-sm font-semibold text-gray-700 mb-1">
                                            Token do Webhook
                                        </label>
                                        <input
                                            type="text"
                                            id="asaas_production_webhook_token"
                                            value={formState.asaas_production_webhook_token}
                                            onChange={(e) => setFormState({ ...formState, asaas_production_webhook_token: e.target.value })}
                                            className={`focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-lg p-3 bg-gray-50 ${getError('asaas_production_webhook_token') ? 'border-red-300' : ''}`}
                                            placeholder="Token gerado no painel do Asaas Produção"
                                            autoComplete="off"
                                        />
                                        {getError('asaas_production_webhook_token') && <p className="text-sm text-red-600 mt-1">{getError('asaas_production_webhook_token')}</p>}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Form de Sandbox */}
                        <div className={activeTab === 'sandbox' ? 'block' : 'hidden'}>
                            <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-6 ring-1 ring-amber-500/10">
                                <div>
                                    <h3 className="text-lg font-bold text-gray-800 flex items-center gap-2">
                                        🛠️ Credenciais de Sandbox (Homologação)
                                    </h3>
                                    <p className="text-sm text-gray-500 mt-1">Obtidas no Asaas Sandbox em <strong className="text-gray-700">Minha Conta → Integração</strong>.</p>
                                </div>
                                <div className="space-y-4">
                                    {/* API Key Section */}
                                    <div>
                                        <label htmlFor="asaas_sandbox_api_key" className="block text-sm font-semibold text-gray-700 mb-1">
                                            Chave da API de Teste
                                        </label>
                                        <input
                                            type="text"
                                            id="asaas_sandbox_api_key"
                                            value={formState.asaas_sandbox_api_key}
                                            onChange={(e) => setFormState({ ...formState, asaas_sandbox_api_key: e.target.value })}
                                            className={`focus:ring-amber-500 focus:border-amber-500 block w-full sm:text-sm border-gray-300 rounded-lg p-3 bg-gray-50 ${getError('asaas_sandbox_api_key') ? 'border-red-300' : ''}`}
                                            placeholder="Ex: $aact_..."
                                            autoComplete="off"
                                        />
                                        {getError('asaas_sandbox_api_key') && <p className="text-sm text-red-600 mt-1">{getError('asaas_sandbox_api_key')}</p>}
                                    </div>
                                    {/* Webhook Token Section */}
                                    <div>
                                        <label htmlFor="asaas_sandbox_webhook_token" className="block text-sm font-semibold text-gray-700 mb-1">
                                            Token do Webhook de Teste
                                        </label>
                                        <input
                                            type="text"
                                            id="asaas_sandbox_webhook_token"
                                            value={formState.asaas_sandbox_webhook_token}
                                            onChange={(e) => setFormState({ ...formState, asaas_sandbox_webhook_token: e.target.value })}
                                            className={`focus:ring-amber-500 focus:border-amber-500 block w-full sm:text-sm border-gray-300 rounded-lg p-3 bg-gray-50 ${getError('asaas_sandbox_webhook_token') ? 'border-red-300' : ''}`}
                                            placeholder="Token gerado no painel do Asaas Sandbox"
                                            autoComplete="off"
                                        />
                                        {getError('asaas_sandbox_webhook_token') && <p className="text-sm text-red-600 mt-1">{getError('asaas_sandbox_webhook_token')}</p>}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* COLUNA DA DIREITA: CONFIGURAÇÕES E INSTRUÇÕES */}
                    <div className="space-y-6">

                        {/* PAYMENT ACTIVE TOGGLE */}
                        <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div>
                                <h3 className="text-base font-bold text-gray-800">Sistema de Pagamentos</h3>
                                <p className="text-sm text-gray-500 mt-1">Desative para impedir novas assinaturas e fechar o checkout. Usuários com assinaturas vigentes não serão afetados pelos webhooks de renovação.</p>
                            </div>
                            <label className="relative inline-flex items-center cursor-pointer flex-shrink-0">
                                <input
                                    type="checkbox"
                                    className="sr-only peer"
                                    checked={formState.payment_active}
                                    onChange={(e) => setFormState({ ...formState, payment_active: e.target.checked })}
                                />
                                <div className="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>

                        {/* WEBHOOK URL INSTRUCTIONS */}
                        <div className="bg-blue-50 border border-blue-200 rounded-2xl p-6">
                            <div className="flex items-start gap-4">
                                <div className="flex-shrink-0 mt-0.5">
                                    <svg className="h-6 w-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                    </svg>
                                </div>
                                <div className="flex-1 min-w-0">
                                    <h3 className="text-base font-semibold text-blue-800 mb-1">Configuração de Webhook ({activeTab === 'sandbox' ? 'Sandbox' : 'Produção'})</h3>
                                    <p className="text-sm text-blue-700 mb-4">
                                        Copie a URL abaixo e cole no painel do Asaas
                                        {activeTab === 'sandbox' ? ' Sandbox' : ' Produção'} em
                                        <strong> Minha Conta → Integrações → Webhooks</strong>.
                                    </p>
                                    <div className="flex items-center gap-2">
                                        <code className="flex-1 bg-white border border-blue-200 rounded-lg px-3 py-2 text-sm font-mono text-blue-900 truncate">
                                            {webhookUrl}
                                        </code>
                                        <button
                                            type="button"
                                            onClick={copyWebhookUrl}
                                            className={`flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-2 text-white text-sm font-medium rounded-lg transition-colors duration-200 ${copied ? 'bg-green-600 hover:bg-green-700' : 'bg-blue-600 hover:bg-blue-700'}`}>
                                            {copied ? (
                                                <>
                                                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" /></svg>
                                                    Copiado!
                                                </>
                                            ) : (
                                                <>
                                                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" /></svg>
                                                    Copiar
                                                </>
                                            )}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* SUBMIT BUTTON */}
                        <div className="pt-6">
                            <button
                                type="submit"
                                disabled={updateMutation.isPending}
                                className="w-full flex items-center justify-center px-6 py-4 border border-transparent text-lg font-bold rounded-xl shadow-md text-white bg-gray-900 hover:bg-gray-800 transition-colors duration-200 disabled:opacity-50">
                                {updateMutation.isPending ? 'Salvando...' : 'Salvar Alterações'}
                            </button>
                        </div>
                    </div>
                </div>

            </form>
        </div>
    );
}
