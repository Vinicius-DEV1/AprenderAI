import React from 'react';

interface UserGrantAccessProps {
    hasActiveGrant: boolean;
    activeGrant: any;
    grantForm: any;
    setGrantForm: (state: any) => void;
    plansData: any;
    grantMutation: any;
    revokeGrantMutation: any;
}

export const UserGrantAccess: React.FC<UserGrantAccessProps> = ({
    hasActiveGrant,
    activeGrant,
    grantForm,
    setGrantForm,
    plansData,
    grantMutation,
    revokeGrantMutation
}) => {
    return (
        <div className="border border-indigo-100 bg-indigo-50/30 rounded-2xl p-6 relative">
            <div className="absolute top-0 left-0 w-1.5 h-full bg-indigo-500 rounded-l-2xl"></div>
            <h3 className="text-lg font-bold text-gray-800 mb-2 flex items-center">
                Concessão Manual de Plano (Grant)
            </h3>
            <p className="text-sm text-gray-500 mb-5">
                Dê acesso gratuito a um plano premium para este usuário. A assinatura aparecerá como "Ativa" no perfil do aluno, com todas as cotas do plano, mas não impactará o LTV e KPI Financeiros.
            </p>

            {hasActiveGrant ? (
                <div className="bg-white p-5 rounded-xl border border-indigo-200 shadow-sm">
                    <div className="flex flex-col sm:flex-row justify-between sm:items-center gap-4">
                        <div>
                            <span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-indigo-100 text-indigo-800 mb-2">GRANT ATIVO</span>
                            <p className="text-sm text-gray-700 font-medium">Este usuário já possui um acesso manual concedido ao plano <span className="font-bold text-indigo-600">{activeGrant?.plan?.name}</span>.</p>
                            <p className="text-xs text-gray-500 mt-1">Data final do acesso: {activeGrant?.current_period_end ? new Date(activeGrant.current_period_end).toLocaleDateString() : 'Infinita'}</p>
                            {activeGrant?.granted_reason && <p className="text-xs italic mt-2">"{activeGrant.granted_reason}"</p>}
                        </div>
                        <button
                            type="button"
                            onClick={() => { if (window.confirm('Revogar esse passe remove permanentemente os limites extras desse plano (se ele não constar como assinante pagante). Deseja continuar?')) revokeGrantMutation.mutate() }}
                            disabled={revokeGrantMutation.isPending}
                            className="px-4 py-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg border border-red-200 text-xs font-bold uppercase disabled:opacity-50"
                        >
                            {revokeGrantMutation.isPending ? 'Revogando...' : 'Revogar Acesso'}
                        </button>
                    </div>
                </div>
            ) : (
                <div className="bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-bold text-gray-700 mb-1.5">Escolha o Plano</label>
                            <select
                                value={grantForm.plan_id}
                                onChange={e => setGrantForm({ ...grantForm, plan_id: e.target.value })}
                                className="w-full rounded-lg border-gray-300 text-sm py-2"
                            >
                                <option value="">Selecione um plano...</option>
                                {(Array.isArray(plansData) ? plansData : plansData?.data ?? []).map((p: any) => (
                                    <option key={p.id} value={p.id}>{p.name}{p.price > 0 ? ` - R$ ${Number(p.price).toFixed(2)}` : ' (Gratuito)'}</option>
                                ))}
                            </select>
                        </div>
                        <div className="flex gap-2">
                            <div className="w-1/3">
                                <label className="block text-xs font-bold text-gray-700 mb-1.5">Tipo Duração</label>
                                <select
                                    value={grantForm.duration_type}
                                    onChange={e => setGrantForm({ ...grantForm, duration_type: e.target.value })}
                                    className="w-full rounded-lg border-gray-300 text-sm py-2"
                                >
                                    <option value="days">Dias</option>
                                    <option value="months">Meses</option>
                                </select>
                            </div>
                            <div className="w-2/3">
                                <label className="block text-xs font-bold text-gray-700 mb-1.5">Quantidade de {grantForm.duration_type === 'days' ? 'Dias' : 'Meses'}</label>
                                <input
                                    type="number"
                                    min="1"
                                    value={grantForm.duration_value}
                                    onChange={e => setGrantForm({ ...grantForm, duration_value: parseInt(e.target.value) || 1 })}
                                    className="w-full rounded-lg border-gray-300 text-sm py-2"
                                />
                            </div>
                        </div>
                    </div>
                    <div>
                        <label className="block text-xs font-bold text-gray-700 mb-1.5">Motivo (Opcional - Invisível para o usuário)</label>
                        <input
                            type="text"
                            placeholder="Ex: Presenteado por participar da live, suporte..."
                            value={grantForm.reason}
                            onChange={e => setGrantForm({ ...grantForm, reason: e.target.value })}
                            className="w-full rounded-lg border-gray-300 text-sm py-2"
                        />
                    </div>
                    <div className="flex justify-end pt-2">
                        <button
                            type="button"
                            disabled={!grantForm.plan_id || grantMutation.isPending}
                            onClick={() => { if (window.confirm('Tem certeza? Isso ativará um plano sem gerar cobrança.')) grantMutation.mutate(grantForm); }}
                            className="px-5 py-2.5 bg-indigo-600 text-white hover:bg-indigo-700 rounded-lg text-sm font-bold uppercase transition-colors disabled:opacity-60 disabled:cursor-not-allowed shadow-sm"
                        >
                            {grantMutation.isPending ? 'Concedendo...' : 'Conceder Acesso Agora'}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
};
