import { useConfigStore } from '../../stores/configStore';

export default function FairUsePolicy() {
    const config = useConfigStore();
    const siteName = config.appName || 'AprovadoAI';

    return (
        <div className="min-h-screen bg-slate-50 dark:bg-slate-950 py-16">
            <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm p-8 sm:p-10 transition-colors">
                    <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white mb-3">
                        Política de Uso Justo – {siteName}
                    </h1>

                    <p className="text-slate-600 dark:text-slate-400 mb-6 font-medium">
                        Para garantir qualidade e sustentabilidade:
                    </p>

                    <ul className="space-y-4 text-slate-700 dark:text-slate-300">
                        <li className="flex items-start gap-4">
                            <span className="mt-2 w-2 h-2 rounded-full bg-blue-600 flex-shrink-0 animate-pulse"></span>
                            <span className="text-base">Interações com IA devem respeitar uso educacional razoável</span>
                        </li>
                        <li className="flex items-start gap-4">
                            <span className="mt-2 w-2 h-2 rounded-full bg-blue-600 flex-shrink-0"></span>
                            <span className="text-base">Limites técnicos automáticos podem ser aplicados</span>
                        </li>
                        <li className="flex items-start gap-4">
                            <span className="mt-2 w-2 h-2 rounded-full bg-blue-600 flex-shrink-0"></span>
                            <span className="text-base">Redações possuem limite mensal conforme plano</span>
                        </li>
                        <li className="flex items-start gap-4">
                            <span className="mt-2 w-2 h-2 rounded-full bg-blue-600 flex-shrink-0"></span>
                            <span className="text-base">Podem existir limites diários</span>
                        </li>
                        <li className="flex items-start gap-4">
                            <span className="mt-2 w-2 h-2 rounded-full bg-blue-600 flex-shrink-0"></span>
                            <span className="text-base">Uso automatizado ou abusivo pode ser restringido</span>
                        </li>
                    </ul>

                    <div className="mt-10 pt-8 border-t border-slate-200 dark:border-slate-800">
                        <p className="text-slate-500 dark:text-slate-400 font-semibold italic text-center">
                            "Garantia de estabilidade, justiça e alto nível."
                        </p>
                    </div>
                </div>

                <div className="mt-8 text-center">
                    <button onClick={() => window.history.back()} className="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 transition-colors">
                        ← Voltar
                    </button>
                </div>
            </div>
        </div>
    );
}
