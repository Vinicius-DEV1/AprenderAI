import { useState, useEffect } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { useParams, useNavigate, Link } from 'react-router-dom';
import api from '../../api/axios';

export default function PlanForm() {
    const { id } = useParams();
    const navigate = useNavigate();
    const isEditing = !!id;

    const [formState, setFormState] = useState({
        name: '',
        price: '',
        interval: 'month',
        simulations_limit: '',
        essays_limit: '',
        max_ai_questions: 10,
        is_active: true
    });

    const [validationErrors, setValidationErrors] = useState<any>({});

    const { data: planData, isLoading } = useQuery({
        queryKey: ['admin-plan', id],
        queryFn: async () => {
            const res = await api.get(`/api/v1/admin/plans/${id}`);
            return res.data;
        },
        enabled: isEditing
    });

    useEffect(() => {
        if (planData) {
            setFormState({
                name: planData.name || '',
                price: planData.price !== null && planData.price !== undefined ? String(planData.price) : '',
                interval: planData.interval || 'month',
                simulations_limit: planData.simulations_limit !== null && planData.simulations_limit !== undefined ? String(planData.simulations_limit) : '',
                essays_limit: planData.essays_limit !== null && planData.essays_limit !== undefined ? String(planData.essays_limit) : '',
                max_ai_questions: planData.max_ai_questions !== null && planData.max_ai_questions !== undefined ? Number(planData.max_ai_questions) : 10,
                is_active: planData.is_active ?? true
            });
        }
    }, [planData]);

    const saveMutation = useMutation({
        mutationFn: async (payload: any) => {
            if (isEditing) {
                return await api.put(`/api/v1/admin/plans/${id}`, payload);
            } else {
                return await api.post('/api/v1/admin/plans', payload);
            }
        },
        onSuccess: () => {
            navigate('/admin/plans');
        },
        onError: (error: any) => {
            if (error.response?.data?.errors) {
                setValidationErrors(error.response.data.errors);
            } else {
                alert('Erro ao salvar o plano.');
            }
        }
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setValidationErrors({});

        // Convert string values to numbers where necessary, handle empty strings as 0 assuming 'limit' inputs don't accept empty
        const payload = {
            ...formState,
            price: Number(formState.price),
            simulations_limit: Number(formState.simulations_limit),
            essays_limit: Number(formState.essays_limit),
            max_ai_questions: Number(formState.max_ai_questions),
            is_active: formState.is_active ? 1 : 0
        };

        saveMutation.mutate(payload);
    };

    if (isEditing && isLoading) return <div className="p-8">Carregando dados do plano...</div>;

    const getError = (field: string) => validationErrors[field] ? validationErrors[field][0] : null;

    return (
        <div className="py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
            <div className="mb-6 flex items-center gap-4">
                <Link to="/admin/plans" className="p-2 bg-white rounded-lg shadow-sm hover:shadow-md transition-all text-gray-600">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                </Link>
                <div>
                    <h1 className="text-2xl font-bold text-gray-800">{isEditing ? 'Editar Plano' : 'Novo Plano'}</h1>
                    <p className="text-gray-500">Defina os detalhes e limites do plano</p>
                </div>
            </div>

            {Object.keys(validationErrors).length > 0 && (
                <div className="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r shadow-sm">
                    <div className="flex">
                        <div className="flex-shrink-0">
                            <svg className="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                            </svg>
                        </div>
                        <div className="ml-3">
                            <h3 className="text-sm font-medium text-red-800">Erros encontrados:</h3>
                            <ul className="mt-1 list-disc list-inside text-sm text-red-700">
                                {Object.values(validationErrors).flat().map((error: any, index) => (
                                    <li key={index}>{error}</li>
                                ))}
                            </ul>
                        </div>
                    </div>
                </div>
            )}

            <form onSubmit={handleSubmit}>
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    {/* Main Info */}
                    <div className="lg:col-span-2 space-y-6">
                        <div className="bg-white rounded-2xl shadow-sm p-6">
                            <h3 className="text-lg font-bold text-gray-800 mb-4">Informações Básicas</h3>

                            <div className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Nome do Plano</label>
                                    <input
                                        type="text"
                                        value={formState.name}
                                        onChange={(e) => setFormState({ ...formState, name: e.target.value })}
                                        className={`w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 ${getError('name') ? 'border-red-300' : ''}`}
                                        placeholder="Ex: Premium Mensal"
                                        required
                                    />
                                    {getError('name') && <span className="text-xs text-red-500">{getError('name')}</span>}
                                </div>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Preço (R$)</label>
                                        <div className="relative rounded-md shadow-sm">
                                            <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                                <span className="text-gray-500 sm:text-sm">R$</span>
                                            </div>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value={formState.price}
                                                onChange={(e) => setFormState({ ...formState, price: e.target.value })}
                                                className={`block w-full rounded-lg border-gray-300 pl-10 focus:border-blue-500 focus:ring-blue-500 sm:text-sm ${getError('price') ? 'border-red-300' : ''}`}
                                                placeholder="0.00"
                                                required
                                            />
                                        </div>
                                        {getError('price') && <span className="text-xs text-red-500">{getError('price')}</span>}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Intervalo de Cobrança</label>
                                        <select
                                            value={formState.interval}
                                            onChange={(e) => setFormState({ ...formState, interval: e.target.value })}
                                            className="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500">
                                            <option value="month">Mensal</option>
                                            <option value="year">Anual</option>
                                        </select>
                                        {getError('interval') && <span className="text-xs text-red-500">{getError('interval')}</span>}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white rounded-2xl shadow-sm p-6">
                            <h3 className="text-lg font-bold text-gray-800 mb-4 flex items-center">
                                <svg className="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" /></svg>
                                Limites e Quotas (Mensal)
                            </h3>
                            <p className="text-sm text-gray-500 mb-4">Defina "0" para ilimitado.</p>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Limite de Simulados</label>
                                    <input
                                        type="number"
                                        min="0"
                                        value={formState.simulations_limit}
                                        onChange={(e) => setFormState({ ...formState, simulations_limit: e.target.value })}
                                        className={`w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 ${getError('simulations_limit') ? 'border-red-300' : ''}`}
                                        required
                                    />
                                    {getError('simulations_limit') && <span className="text-xs text-red-500">{getError('simulations_limit')}</span>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Limite de Redações</label>
                                    <input
                                        type="number"
                                        min="0"
                                        value={formState.essays_limit}
                                        onChange={(e) => setFormState({ ...formState, essays_limit: e.target.value })}
                                        className={`w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 ${getError('essays_limit') ? 'border-red-300' : ''}`}
                                        required
                                    />
                                    {getError('essays_limit') && <span className="text-xs text-red-500">{getError('essays_limit')}</span>}
                                </div>

                                <div className="md:col-span-2 border-t pt-4 mt-2">
                                    <label className="block text-sm font-medium text-gray-700 mb-1 flex items-center gap-2">
                                        <svg className="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                        Limite de Perguntas IA (Mensal)
                                    </label>
                                    <input
                                        type="number"
                                        min="0"
                                        value={formState.max_ai_questions}
                                        onChange={(e) => setFormState({ ...formState, max_ai_questions: Number(e.target.value) })}
                                        className={`w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 ${getError('max_ai_questions') ? 'border-red-300' : ''}`}
                                        required
                                    />
                                    {getError('max_ai_questions') && <span className="text-xs text-red-500">{getError('max_ai_questions')}</span>}
                                    <p className="text-xs text-gray-500 mt-1">Quantidade de perguntas que o usuário pode fazer ao chat de IA por mês.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Sidebar Actions */}
                    <div className="space-y-6">
                        <div className="bg-white rounded-2xl shadow-sm p-6">
                            <h3 className="text-lg font-bold text-gray-800 mb-4">Status & Publicação</h3>

                            <div className="flex items-center justify-between mb-4">
                                <span className="text-sm font-medium text-gray-700">Plano Ativo?</span>
                                <label className="relative inline-flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={formState.is_active}
                                        onChange={(e) => setFormState({ ...formState, is_active: e.target.checked })}
                                        className="sr-only peer"
                                    />
                                    <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>
                            {getError('is_active') && <span className="text-xs text-red-500">{getError('is_active')}</span>}
                            <p className="text-xs text-gray-500 mb-6">Planos inativos não aparecem na página de checkout, mas assinaturas existentes continuam funcionando.</p>

                            <button
                                type="submit"
                                disabled={saveMutation.isPending}
                                className="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 font-bold shadow-lg shadow-blue-500/30 transition-all transform hover:-translate-y-0.5 disabled:opacity-50">
                                {saveMutation.isPending ? 'Salvando...' : (isEditing ? 'Atualizar Plano' : 'Criar Plano')}
                            </button>

                            <Link to="/admin/plans" className="block w-full text-center mt-3 text-gray-500 hover:text-gray-700 text-sm">
                                Cancelar
                            </Link>
                        </div>

                        {isEditing && (
                            <div className="bg-blue-50 rounded-2xl p-6 border border-blue-100">
                                <h4 className="font-semibold text-blue-800 mb-2">Dica importante</h4>
                                <p className="text-sm text-blue-700">
                                    Alterar o preço aqui afetará apenas <strong>novas assinaturas</strong>. Assinantes antigos continuarão pagando o valor original contratado.
                                </p>
                            </div>
                        )}
                    </div>
                </div>
            </form>
        </div>
    );
}
