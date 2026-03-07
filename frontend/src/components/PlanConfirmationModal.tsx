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

    const getPlanLevel = (name?: string) => {
        if (!name) return 0;
        const low = name.toLowerCase();
        if (low.includes('plus')) return 3;
        if (low.includes('básico') || low.includes('basico')) return 2;
        if (low.includes('gratuito')) return 1;
        return 0;
    };

    const currentLevel = getPlanLevel(currentPlan?.name);
    const selectedLevel = getPlanLevel(selectedPlan?.name);
    const currentPrice = currentPlan?.price || 0;

    const isDowngrade = selectedLevel < currentLevel && selectedLevel > 0;
    const isPeriodicityChange = selectedLevel === currentLevel && currentPlan?.interval !== selectedPlan?.interval;

    let theme = {
        bgLight: 'bg-blue-50/50 dark:bg-blue-900/10',
        bgIcon: 'bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400',
        borderBox: 'border-blue-100 bg-blue-50/30 dark:border-blue-800',
        textTitle: 'text-blue-700 dark:text-blue-400',
        bgDot: 'bg-blue-200 dark:bg-blue-800 flex items-center justify-center text-xs font-bold text-blue-800 dark:text-blue-200',
        underline: 'underline decoration-blue-500 decoration-2 font-bold',
        button: 'bg-blue-600 hover:bg-blue-700 shadow-blue-500/25',
        title: 'Confirmar Upgrade',
        iconPath: <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
    };

    if (isDowngrade) {
        theme = {
            ...theme,
            bgLight: 'bg-amber-50/50 dark:bg-amber-900/10',
            bgIcon: 'bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400',
            borderBox: 'border-amber-100 bg-amber-50/30 dark:border-amber-800',
            textTitle: 'text-amber-700 dark:text-amber-400',
            bgDot: 'bg-amber-200 dark:bg-amber-800 flex items-center justify-center text-xs font-bold text-amber-800 dark:text-amber-200',
            underline: 'underline decoration-amber-500 decoration-2 font-bold',
            button: 'bg-slate-800 hover:bg-slate-900 shadow-slate-500/25',
            title: 'Confirmar Downgrade',
            iconPath: <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6" />
        };
    } else if (isPeriodicityChange) {
        theme = {
            ...theme,
            bgLight: 'bg-emerald-50/50 dark:bg-emerald-900/10',
            bgIcon: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400',
            borderBox: 'border-emerald-100 bg-emerald-50/30 dark:border-emerald-800',
            textTitle: 'text-emerald-700 dark:text-emerald-400',
            bgDot: 'bg-emerald-200 dark:bg-emerald-800 flex items-center justify-center text-xs font-bold text-emerald-800 dark:text-emerald-200',
            underline: 'underline decoration-emerald-500 decoration-2 font-bold',
            button: 'bg-emerald-600 hover:bg-emerald-700 shadow-emerald-500/25',
            title: 'Alterar Periodicidade',
            iconPath: <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        };
    }

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
                <div className={`p-8 text-center ${theme.bgLight}`}>
                    <div className={`mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-2xl shadow-inner ${theme.bgIcon}`}>
                        <svg className="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            {theme.iconPath}
                        </svg>
                    </div>
                    <h3 className="text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                        {theme.title}
                    </h3>
                    <p className="mt-2 text-slate-500 dark:text-slate-400">
                        Você está prestes a mudar para o plano <span className="font-bold text-slate-900 dark:text-white">{selectedPlan.name}</span>.
                    </p>
                </div>

                <div className="p-8">
                    {/* Information Box */}
                    <div className={`rounded-2xl p-6 border ${theme.borderBox}`}>
                        <h4 className={`text-sm font-bold uppercase tracking-wider mb-3 ${theme.textTitle}`}>
                            O que acontece agora?
                        </h4>

                        <div className="space-y-4">
                            {isDowngrade ? (
                                <>
                                    <div className="flex gap-4">
                                        <div className={`flex-shrink-0 h-6 w-6 rounded-full ${theme.bgDot}`}>1</div>
                                        <p className="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                                            <strong className="text-slate-900 dark:text-white">Período de Transição:</strong> Sua redução para o plano {selectedPlan.name} só será efetivada no <span className={theme.underline}>final do seu ciclo atual</span>.
                                        </p>
                                    </div>
                                    <div className="flex gap-4">
                                        <div className={`flex-shrink-0 h-6 w-6 rounded-full ${theme.bgDot}`}>2</div>
                                        <p className="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                                            <strong className="text-slate-900 dark:text-white">Sem Perda Imediata:</strong> Você continua com todos os benefícios do seu plano atual até a data da próxima renovação.
                                        </p>
                                    </div>
                                </>
                            ) : isPeriodicityChange ? (
                                <>
                                    <div className="flex gap-4">
                                        <div className={`flex-shrink-0 h-6 w-6 rounded-full ${theme.bgDot}`}>1</div>
                                        <p className="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                                            <strong className="text-slate-900 dark:text-white">Tempo Adicionado:</strong> O novo período ({selectedPlan.interval === 'yearly' || selectedPlan.interval === 'year' ? '1 Ano' : '1 Mês'}) será adicionado e ativado.
                                        </p>
                                    </div>
                                    <div className="flex gap-4">
                                        <div className={`flex-shrink-0 h-6 w-6 rounded-full ${theme.bgDot}`}>2</div>
                                        <p className="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                                            <strong className="text-slate-900 dark:text-white">Recursos Garantidos:</strong> Suas cotas continuarão recarregando normalmente neste novo formato mensal ou anual sem sofrer interrupções.
                                        </p>
                                    </div>
                                </>
                            ) : (
                                <>
                                    <div className="flex gap-4">
                                        <div className={`flex-shrink-0 h-6 w-6 rounded-full ${theme.bgDot}`}>1</div>
                                        <p className="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                                            {currentPrice === 0 ? (
                                                <><strong className="text-slate-900 dark:text-white">Reinício de Cotas:</strong> Os limites de uso gratuitos serão zerados e o seu novo saldo passará a ser as cotas cheias e exclusivas do plano {selectedPlan.name}.</>
                                            ) : (
                                                <><strong className="text-slate-900 dark:text-white">Limite Acumulativo:</strong> As novas cotas do plano {selectedPlan.name} serão <span className={theme.underline}>SOMADAS</span> às que você já possui hoje.</>
                                            )}
                                        </p>
                                    </div>
                                    <div className="flex gap-4">
                                        <div className={`flex-shrink-0 h-6 w-6 rounded-full ${theme.bgDot}`}>2</div>
                                        <p className="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                                            <strong className="text-slate-900 dark:text-white">Acesso Imediato:</strong> Após a confirmação do pagamento, os recursos serão liberados na sua conta instantaneamente.
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
                            className={`flex-[2] py-4 rounded-xl text-sm font-black uppercase tracking-widest text-white transition-all shadow-lg active:scale-95 ${theme.button}`}
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
