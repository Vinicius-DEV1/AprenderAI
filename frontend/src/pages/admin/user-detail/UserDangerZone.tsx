import React from 'react';

interface UserDangerZoneProps {
    user: any;
    refundMutation: any;
    handleDeleteUser: () => void;
}

export const UserDangerZone: React.FC<UserDangerZoneProps> = ({
    user,
    refundMutation,
    handleDeleteUser
}) => {
    const hasActiveSubscription = user.subscriptions?.some((s: any) => s.status === 'active');

    return (
        <div className="space-y-6 mt-12 pb-12">
            <h3 className="text-lg font-black text-red-600 uppercase tracking-widest border-b border-red-100 pb-2">Zona de Perigo</h3>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Refund/Cancel Subscription */}
                <div className="border border-red-200 bg-red-50/50 rounded-2xl p-6 relative overflow-hidden">
                    <div className="absolute top-0 left-0 w-1.5 h-full bg-red-400"></div>
                    <h4 className="font-bold text-red-800 text-base mb-2">Estorno e Cancelamento</h4>
                    <p className="text-xs text-red-700 mb-4 font-medium leading-normal">
                        Paralisa imediatamente o plano e zera todos os limites premium acumulados. Use para casos de estorno ou desistência nos 7 dias.
                    </p>
                    <button
                        onClick={() => {
                            if (window.confirm('ALERTA: Isso removerá os limites e assinatura ativa HOJE. Confirmar?')) {
                                refundMutation.mutate();
                            }
                        }}
                        disabled={refundMutation.isPending || !hasActiveSubscription}
                        className="w-full py-2.5 rounded-xl bg-white border border-red-200 text-red-600 font-bold uppercase text-[10px] hover:bg-red-600 hover:text-white transition-all disabled:opacity-40"
                    >
                        {refundMutation.isPending ? 'PROCESSANDO...' : 'ESTORNAR ASSINATURA'}
                    </button>
                    {!hasActiveSubscription && (
                        <p className="text-[9px] text-red-400 mt-2 text-center font-bold">Nenhuma assinatura ativa encontrada.</p>
                    )}
                </div>

                {/* Permanent Delete */}
                <div className="border border-gray-900 bg-gray-900 rounded-2xl p-6 relative overflow-hidden text-white">
                    <h4 className="font-bold text-white text-base mb-2">Exclusão Permanente</h4>
                    <p className="text-xs text-gray-400 mb-4 font-medium leading-normal">
                        Apaga TODOS os dados do usuário (redações, simulados, logs) de forma irreversível. 
                    </p>
                    <button
                        onClick={handleDeleteUser}
                        className="w-full py-2.5 rounded-xl bg-red-600 text-white font-bold uppercase text-[10px] hover:bg-red-700 transition-all shadow-lg shadow-red-900/40"
                    >
                        EXCLUIR CONTA PARA SEMPRE
                    </button>
                </div>
            </div>
        </div>
    );
};
