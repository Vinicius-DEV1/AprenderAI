import { useState, useEffect } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { useParams, useNavigate, Link } from 'react-router-dom';
import api from '../../api/axios';

export default function CouponForm() {
    const { id } = useParams();
    const navigate = useNavigate();
    const isEditing = !!id;

    const [formState, setFormState] = useState({
        code: '',
        is_active: true,
        type: 'percent',
        value: '',
        max_uses: '',
        start_date: '',
        expires_at: ''
    });

    const [validationErrors, setValidationErrors] = useState<any>({});

    const { data: couponData, isLoading } = useQuery({
        queryKey: ['admin-coupon', id],
        queryFn: async () => {
            const res = await api.get(`/api/v1/admin/coupons/${id}`);
            return res.data;
        },
        enabled: isEditing
    });

    useEffect(() => {
        if (couponData) {
            setFormState({
                code: couponData.code || '',
                is_active: couponData.is_active ?? true,
                type: couponData.type || 'percent',
                value: couponData.value !== null && couponData.value !== undefined ? String(couponData.value) : '',
                max_uses: couponData.max_uses !== null && couponData.max_uses !== undefined ? String(couponData.max_uses) : '',
                start_date: couponData.start_date ? new Date(couponData.start_date).toISOString().slice(0, 16) : '',
                expires_at: couponData.expires_at ? new Date(couponData.expires_at).toISOString().slice(0, 16) : ''
            });
        }
    }, [couponData]);

    const saveMutation = useMutation({
        mutationFn: async (payload: any) => {
            if (isEditing) {
                return await api.put(`/api/v1/admin/coupons/${id}`, payload);
            } else {
                return await api.post('/api/v1/admin/coupons', payload);
            }
        },
        onSuccess: () => {
            navigate('/admin/coupons');
        },
        onError: (error: any) => {
            if (error.response?.data?.errors) {
                setValidationErrors(error.response.data.errors);
            } else {
                alert('Erro ao salvar o cupom.');
            }
        }
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setValidationErrors({});

        const payload = {
            ...formState,
            value: Number(formState.value),
            max_uses: formState.max_uses ? Number(formState.max_uses) : null,
            is_active: formState.is_active ? 1 : 0
        };

        saveMutation.mutate(payload);
    };

    if (isEditing && isLoading) return <div className="p-8">Carregando dados do cupom...</div>;

    const getError = (field: string) => validationErrors[field] ? validationErrors[field][0] : null;

    return (
        <div className="py-12 px-4 sm:px-6 lg:px-8 max-w-3xl mx-auto">
            <div className="mb-8">
                <div className="flex items-center gap-4">
                    <Link to="/admin/coupons" className="text-gray-500 hover:text-gray-700 transition-colors">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                    </Link>
                    <h1 className="text-3xl font-bold text-gray-800">
                        {isEditing ? 'Editar Cupom' : 'Novo Cupom'}
                    </h1>
                </div>
                <p className="text-gray-600 mt-2 ml-9">
                    {isEditing ? 'Atualize as informações do cupom.' : 'Preencha os dados para criar um novo cupom.'}
                </p>
            </div>

            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 max-w-3xl">
                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {/* Code */}
                        <div>
                            <label htmlFor="code" className="block text-sm font-medium text-gray-700 mb-1">Código do Cupom</label>
                            <input
                                type="text"
                                id="code"
                                value={formState.code}
                                onChange={(e) => setFormState({ ...formState, code: e.target.value.toUpperCase() })}
                                className={`block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 uppercase ${getError('code') ? 'border-red-300' : ''}`}
                                placeholder="EX: PROMO2024"
                                required
                            />
                            {getError('code') && <p className="mt-1 text-sm text-red-600">{getError('code')}</p>}
                        </div>

                        {/* Status */}
                        <div className="flex items-center h-full pt-6">
                            <label className="inline-flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={formState.is_active}
                                    onChange={(e) => setFormState({ ...formState, is_active: e.target.checked })}
                                    className="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                />
                                <span className="ml-2 text-gray-700 font-medium">Cupom Ativo</span>
                            </label>
                        </div>

                        {/* Type */}
                        <div>
                            <label htmlFor="type" className="block text-sm font-medium text-gray-700 mb-1">Tipo de Desconto</label>
                            <select
                                id="type"
                                value={formState.type}
                                onChange={(e) => setFormState({ ...formState, type: e.target.value })}
                                className={`block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 ${getError('type') ? 'border-red-300' : ''}`}
                                required>
                                <option value="percent">Porcentagem (%)</option>
                                <option value="fixed">Valor Fixo (R$)</option>
                            </select>
                            {getError('type') && <p className="mt-1 text-sm text-red-600">{getError('type')}</p>}
                        </div>

                        {/* Value */}
                        <div>
                            <label htmlFor="value" className="block text-sm font-medium text-gray-700 mb-1">Valor do Desconto</label>
                            <input
                                type="number"
                                id="value"
                                step="0.01"
                                min="0"
                                value={formState.value}
                                onChange={(e) => setFormState({ ...formState, value: e.target.value })}
                                className={`block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 ${getError('value') ? 'border-red-300' : ''}`}
                                placeholder="0.00"
                                required
                            />
                            {getError('value') && <p className="mt-1 text-sm text-red-600">{getError('value')}</p>}
                        </div>

                        {/* Max Uses */}
                        <div>
                            <label htmlFor="max_uses" className="block text-sm font-medium text-gray-700 mb-1">Limite de Usos (Global)</label>
                            <input
                                type="number"
                                id="max_uses"
                                min="1"
                                value={formState.max_uses}
                                onChange={(e) => setFormState({ ...formState, max_uses: e.target.value })}
                                className={`block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 ${getError('max_uses') ? 'border-red-300' : ''}`}
                                placeholder="Ilimitado se vazio"
                            />
                            <p className="mt-1 text-xs text-gray-500">Deixe em branco para usos ilimitados.</p>
                            {getError('max_uses') && <p className="mt-1 text-sm text-red-600">{getError('max_uses')}</p>}
                        </div>

                        {/* Used Count (Read Only) */}
                        {isEditing && (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Quantidade Já Utilizada</label>
                                <input
                                    type="text"
                                    value={couponData?.used_count || 0}
                                    disabled
                                    className="block w-full rounded-md border-gray-300 bg-gray-100 text-gray-500 shadow-sm"
                                />
                            </div>
                        )}

                        {/* Start Date */}
                        <div>
                            <label htmlFor="start_date" className="block text-sm font-medium text-gray-700 mb-1">Válido a partir de</label>
                            <input
                                type="datetime-local"
                                id="start_date"
                                value={formState.start_date}
                                onChange={(e) => setFormState({ ...formState, start_date: e.target.value })}
                                className={`block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 ${getError('start_date') ? 'border-red-300' : ''}`}
                            />
                            {getError('start_date') && <p className="mt-1 text-sm text-red-600">{getError('start_date')}</p>}
                        </div>

                        {/* Expires At */}
                        <div>
                            <label htmlFor="expires_at" className="block text-sm font-medium text-gray-700 mb-1">Válido até</label>
                            <input
                                type="datetime-local"
                                id="expires_at"
                                value={formState.expires_at}
                                onChange={(e) => setFormState({ ...formState, expires_at: e.target.value })}
                                className={`block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 ${getError('expires_at') ? 'border-red-300' : ''}`}
                            />
                            {getError('expires_at') && <p className="mt-1 text-sm text-red-600">{getError('expires_at')}</p>}
                        </div>
                    </div>

                    <div className="flex justify-end pt-6 border-t border-gray-100">
                        <Link
                            to="/admin/coupons"
                            className="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-3">
                            Cancelar
                        </Link>
                        <button
                            type="submit"
                            disabled={saveMutation.isPending}
                            className="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
                            {saveMutation.isPending ? 'Salvando...' : (isEditing ? 'Atualizar Cupom' : 'Criar Cupom')}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
