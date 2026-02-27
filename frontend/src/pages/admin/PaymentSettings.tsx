import { useState, useEffect } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import api from '../../api/axios';

export default function PaymentSettings() {
    const [formState, setFormState] = useState({
        asaas_api_key: '',
        asaas_webhook_token: '',
        asaas_sandbox: false,
        payment_active: false
    });

    const [copied, setCopied] = useState(false);
    const [validationErrors, setValidationErrors] = useState<any>({});

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
                asaas_api_key: settings.asaas_api_key || '',
                asaas_webhook_token: settings.asaas_webhook_token || '',
                asaas_sandbox: settings.asaas_sandbox ?? false,
                payment_active: settings.payment_active ?? false
            });
        }
    }, [settings]);

    const updateMutation = useMutation({
        mutationFn: async (payload: any) => {
            const res = await api.put('/api/v1/admin/payment-settings', payload);
            return res.data;
        },
        onSuccess: () => {
            alert('Configurações salvas com sucesso!');
        },
        onError: (error: any) => {
            if (error.response?.data?.errors) {
                setValidationErrors(error.response.data.errors);
            } else {
                alert('Erro ao salvar as configurações.');
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

    return (
        <div className="py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
            {/* Header */}
            <div className="mb-8">
                <div className="flex items-center gap-4">
                    <h1 className="text-3xl font-bold text-gray-800 mb-2">Configurações de Pagamento</h1>
                    {/* Status Badge */}
                    {!formState.payment_active ? (
                        <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-red-100 text-red-700">
                            🔴 Desativado
                        </span>
                    ) : formState.asaas_sandbox ? (
                        <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-amber-100 text-amber-700">
                            🟡 Sandbox (Testes)
                        </span>
                    ) : (
                        <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-green-100 text-green-700">
                            🟢 Produção Ativa
                        </span>
                    )}
                </div>
                <p className="text-gray-600">Gerencie a integração com o gateway Asaas e status dos pagamentos.</p>
            </div>

            {/* PAINEL: URL DO WEBHOOK */}
            <div className="bg-blue-50 border border-blue-200 rounded-2xl p-6 mb-6">
                <div className="flex items-start gap-4">
                    <div className="flex-shrink-0 mt-0.5">
                        <svg className="h-6 w-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                    </div>
                    <div className="flex-1 min-w-0">
                        <h3 className="text-base font-semibold text-blue-800 mb-1">URL do Webhook Asaas</h3>
                        <p className="text-sm text-blue-700 mb-3">
                            Copie a URL abaixo e cole no painel do Asaas em
                            <strong> Minha Conta → Integrações → Webhooks</strong>.
                            Isso permite que o Asaas notifique o sistema quando um pagamento for confirmado.
                        </p>
                        <div className="flex items-center gap-3">
                            <code className="flex-1 bg-white border border-blue-200 rounded-lg px-4 py-2.5 text-sm font-mono text-blue-900 truncate">
                                {webhookUrl}
                            </code>
                            <button
                                type="button"
                                onClick={copyWebhookUrl}
                                className={`flex-shrink-0 inline-flex items-center gap-2 px-4 py-2.5 text-white text-sm font-medium rounded-lg transition-colors duration-200 ${copied ? 'bg-green-600 hover:bg-green-700' : 'bg-blue-600 hover:bg-blue-700'}`}>
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

            {/* Main Card */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div className="p-6 border-b border-gray-100">
                    <h2 className="text-xl font-semibold text-gray-800">Integração Asaas</h2>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-8">
                    {/* API Key Section */}
                    <div className="space-y-2">
                        <label htmlFor="asaas_api_key" className="block text-sm font-medium text-gray-700">
                            Chave da API (API Key)
                        </label>
                        <div className="relative rounded-md shadow-sm">
                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg className="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                </svg>
                            </div>
                            <input
                                type="text"
                                id="asaas_api_key"
                                value={formState.asaas_api_key}
                                onChange={(e) => setFormState({ ...formState, asaas_api_key: e.target.value })}
                                className={`focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 sm:text-sm border-gray-300 rounded-lg p-2.5 ${getError('asaas_api_key') ? 'border-red-300' : ''}`}
                                placeholder="Ex: $aact_..."
                                autoComplete="off"
                            />
                        </div>
                        {getError('asaas_api_key') && <p className="text-sm text-red-600">{getError('asaas_api_key')}</p>}
                        <p className="text-sm text-gray-500">
                            Obtida no painel do Asaas em <strong>Minha Conta → Integração</strong>.
                            Use a chave <em>Sandbox</em> para testes e a de <em>Produção</em> para cobranças reais.
                        </p>
                    </div>

                    {/* Webhook Token Section */}
                    <div className="space-y-2">
                        <label htmlFor="asaas_webhook_token" className="block text-sm font-medium text-gray-700">
                            Token de Segurança do Webhook
                        </label>
                        <div className="relative rounded-md shadow-sm">
                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg className="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <input
                                type="text"
                                id="asaas_webhook_token"
                                value={formState.asaas_webhook_token}
                                onChange={(e) => setFormState({ ...formState, asaas_webhook_token: e.target.value })}
                                className={`focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 sm:text-sm border-gray-300 rounded-lg p-2.5 ${getError('asaas_webhook_token') ? 'border-red-300' : ''}`}
                                placeholder="Token definido no painel do Asaas ao cadastrar o Webhook"
                                autoComplete="off"
                            />
                        </div>
                        {getError('asaas_webhook_token') && <p className="text-sm text-red-600">{getError('asaas_webhook_token')}</p>}
                        <p className="text-sm text-gray-500">
                            Ao cadastrar o webhook no Asaas, defina um <strong>Access Token</strong> e cole-o aqui.
                            O sistema irá rejeitar qualquer chamada de webhook que não apresente este token no header
                            <code className="bg-gray-100 px-1 rounded">asaas-access-token</code>.
                        </p>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                        {/* Sandbox Mode */}
                        <div className="relative flex items-start p-4 bg-gray-50 rounded-xl border border-gray-200">
                            <div className="flex items-center h-5">
                                <input
                                    id="asaas_sandbox"
                                    type="checkbox"
                                    checked={formState.asaas_sandbox}
                                    onChange={(e) => setFormState({ ...formState, asaas_sandbox: e.target.checked })}
                                    className="focus:ring-blue-500 h-5 w-5 text-blue-600 border-gray-300 rounded transition-colors duration-200"
                                />
                            </div>
                            <div className="ml-3 text-sm">
                                <label htmlFor="asaas_sandbox" className="font-medium text-gray-700 block mb-1">
                                    Modo Sandbox (Testes)
                                </label>
                                <p className="text-gray-500">
                                    Quando ativado, todas as transações são feitas no ambiente de homologação.
                                    O checkout funciona normalmente, mas exibe um banner de aviso para o usuário.
                                    <span className="block mt-1 text-amber-600 font-medium">⚠️ Não use dados reais neste modo.</span>
                                </p>
                            </div>
                        </div>

                        {/* Payment Active */}
                        <div className="relative flex items-start p-4 bg-gray-50 rounded-xl border border-gray-200">
                            <div className="flex items-center h-5">
                                <input
                                    id="payment_active"
                                    type="checkbox"
                                    checked={formState.payment_active}
                                    onChange={(e) => setFormState({ ...formState, payment_active: e.target.checked })}
                                    className="focus:ring-green-500 h-5 w-5 text-green-600 border-gray-300 rounded transition-colors duration-200"
                                />
                            </div>
                            <div className="ml-3 text-sm">
                                <label htmlFor="payment_active" className="font-medium text-gray-700 block mb-1">
                                    Sistema de Pagamentos Ativo
                                </label>
                                <p className="text-gray-500">
                                    Desative para entrar em modo <strong>"Manutenção Total"</strong>.
                                    Nenhum usuário conseguirá realizar novas assinaturas.
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Warning Alert if Sandbox is ON */}
                    {formState.asaas_sandbox && (
                        <div className="bg-amber-50 border-l-4 border-amber-400 p-4 rounded-r-lg">
                            <div className="flex">
                                <div className="flex-shrink-0">
                                    <svg className="h-5 w-5 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                                    </svg>
                                </div>
                                <div className="ml-3">
                                    <p className="text-sm text-amber-700">
                                        O sistema está em <strong>Modo Sandbox</strong>. Transações financeiras reais não serão
                                        processadas. Lembre-se de usar a chave API de <em>Sandbox</em> do Asaas (não a de Produção).
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="pt-6 border-t border-gray-100 flex items-center justify-end">
                        <button
                            type="submit"
                            disabled={updateMutation.isPending}
                            className="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200 disabled:opacity-50">
                            <svg className="w-5 h-5 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                            </svg>
                            {updateMutation.isPending ? 'Salvando...' : 'Salvar Configurações'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
