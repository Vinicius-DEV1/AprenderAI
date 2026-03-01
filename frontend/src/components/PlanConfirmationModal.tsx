interface PlanConfirmationModalProps {
    isOpen: boolean;
    onClose: () => void;
    onConfirm: () => void;
    currentPlan: any;
    selectedPlan: any;
}

export default function PlanConfirmationModal({
    isOpen,
    onClose,
    onConfirm,
    currentPlan,
    selectedPlan
}: PlanConfirmationModalProps) {
    if (!isOpen || !selectedPlan) return null;

    const currentPrice = currentPlan?.price || 0;
    const isUpgrade = selectedPlan.price > currentPrice;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-0">
            {/* Backdrop */}
            <div
                className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity animate-fade-in"
                onClick={onClose}
            ></div>

            {/* Modal Panel */}
            <div className="relative w-full max-w-lg transform overflow-hidden rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl transition-all animate-fade-in-up">

                {/* Header with Icon */}
                <div className={`p-8 text-center ${isUpgrade ? 'bg-blue-50/50 dark:bg-blue-900/10' : 'bg-amber-50/50 dark:bg-amber-900/10'}`}>
                    <div className={`mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-2xl shadow-inner ${isUpgrade ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400' : 'bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400'}`}>
                        {isUpgrade ? (
                            <svg className="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                        ) : (
                            <svg className="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6" />
                            </svg>
                        )}
                    </div>
                    <h3 className="text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                        {isUpgrade ? 'Confirmar Upgrade' : 'Confirmar Alteração'}
                    </h3>
                    <p className="mt-2 text-slate-500 dark:text-slate-400">
                        Você está prestes a mudar para o plano <span className="font-bold text-slate-900 dark:text-white">{selectedPlan.name}</span>.
                    </p>
                </div>

                <div className="p-8">
                    {/* Information Box */}
                    <div className={`rounded-2xl p-6 border ${isUpgrade ? 'border-blue-100 bg-blue-50/30 dark:border-blue-800' : 'border-amber-100 bg-amber-50/30 dark:border-amber-800'}`}>
                        <h4 className={`text-sm font-bold uppercase tracking-wider mb-3 ${isUpgrade ? 'text-blue-700 dark:text-blue-400' : 'text-amber-700 dark:text-amber-400'}`}>
                            O que acontece agora?
                        </h4>

                        <div className="space-y-4">
                            {isUpgrade ? (
                                <>
                                    <div className="flex gap-4">
                                        <div className="flex-shrink-0 h-6 w-6 rounded-full bg-blue-200 dark:bg-blue-800 flex items-center justify-center text-xs font-bold text-blue-800 dark:text-blue-200">1</div>
                                        <p className="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                                            <strong className="text-slate-900 dark:text-white">Limite Acumulativo:</strong> As novas cotas do plano {selectedPlan.name} serão <span className="underline decoration-blue-500 decoration-2 font-bold">SOMADAS</span> às que você já possui hoje.
                                        </p>
                                    </div>
                                    <div className="flex gap-4">
                                        <div className="flex-shrink-0 h-6 w-6 rounded-full bg-blue-200 dark:bg-blue-800 flex items-center justify-center text-xs font-bold text-blue-800 dark:text-blue-200">2</div>
                                        <p className="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                                            <strong className="text-slate-900 dark:text-white">Acesso Imediato:</strong> Após a confirmação do pagamento, os recursos serão liberados na sua conta instantaneamente.
                                        </p>
                                    </div>
                                </>
                            ) : (
                                <>
                                    <div className="flex gap-4">
                                        <div className="flex-shrink-0 h-6 w-6 rounded-full bg-amber-200 dark:bg-amber-800 flex items-center justify-center text-xs font-bold text-amber-800 dark:text-amber-200">1</div>
                                        <p className="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                                            <strong className="text-slate-900 dark:text-white">Período de Transição:</strong> Sua redução para o plano {selectedPlan.name} só será efetivada no <span className="underline decoration-amber-500 decoration-2 font-bold">final do seu ciclo atual</span>.
                                        </p>
                                    </div>
                                    <div className="flex gap-4">
                                        <div className="flex-shrink-0 h-6 w-6 rounded-full bg-amber-200 dark:bg-amber-800 flex items-center justify-center text-xs font-bold text-amber-800 dark:text-amber-200">2</div>
                                        <p className="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                                            <strong className="text-slate-900 dark:text-white">Sem Perda Imediata:</strong> Você continua com todos os benefícios do seu plano atual até a data da próxima renovação.
                                        </p>
                                    </div>
                                </>
                            )}
                        </div>
                    </div>

                    {/* Footer Actions */}
                    <div className="mt-8 flex flex-col sm:flex-row gap-3">
                        <button
                            onClick={onConfirm}
                            className={`flex-[2] py-4 rounded-xl text-sm font-black uppercase tracking-widest text-white transition-all shadow-lg active:scale-95 ${isUpgrade ? 'bg-blue-600 hover:bg-blue-700 shadow-blue-500/25' : 'bg-slate-800 hover:bg-slate-900 shadow-slate-500/25'}`}
                        >
                            Confirmar e Ir para Checkout
                        </button>
                        <button
                            onClick={onClose}
                            className="flex-1 py-4 rounded-xl text-sm font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all border border-transparent hover:border-slate-200 dark:hover:border-slate-700"
                        >
                            Cancelar
                        </button>
                    </div>

                    <p className="mt-6 text-center text-[10px] text-slate-400 uppercase font-bold tracking-tighter">
                        Pagamento processado com segurança via asaas/mercado pago
                    </p>
                </div>
            </div>
        </div>
    );
}
