export default function FairUse() {
    const siteName = import.meta.env.VITE_APP_NAME || 'AprovadoAI';

    return (
        <div className="font-sans antialiased text-slate-900 bg-white min-h-screen">
            <main className="min-h-screen bg-slate-50">
                <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                    <div className="bg-white border border-slate-200 rounded-2xl shadow-sm p-8 sm:p-10">
                        <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 mb-3">
                            Política de Uso Justo – {siteName}
                        </h1>

                        <p className="text-slate-600 mb-6">
                            Para garantir qualidade e sustentabilidade:
                        </p>

                        <ul className="space-y-3 text-slate-700">
                            <li className="flex items-start gap-3">
                                <span className="mt-2 w-2 h-2 rounded-full bg-blue-600 flex-shrink-0"></span>
                                <span>Interações com IA devem respeitar uso educacional razoável</span>
                            </li>
                            <li className="flex items-start gap-3">
                                <span className="mt-2 w-2 h-2 rounded-full bg-blue-600 flex-shrink-0"></span>
                                <span>Limites técnicos automáticos podem ser aplicados</span>
                            </li>
                            <li className="flex items-start gap-3">
                                <span className="mt-2 w-2 h-2 rounded-full bg-blue-600 flex-shrink-0"></span>
                                <span>Redações possuem limite mensal conforme plano</span>
                            </li>
                            <li className="flex items-start gap-3">
                                <span className="mt-2 w-2 h-2 rounded-full bg-blue-600 flex-shrink-0"></span>
                                <span>Podem existir limites diários</span>
                            </li>
                            <li className="flex items-start gap-3">
                                <span className="mt-2 w-2 h-2 rounded-full bg-blue-600 flex-shrink-0"></span>
                                <span>Uso automatizado ou abusivo pode ser restringido</span>
                            </li>
                        </ul>

                        <div className="mt-8 pt-6 border-t border-slate-200">
                            <p className="text-slate-600 font-medium">
                                Garantia de estabilidade, justiça e alto nível.
                            </p>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    );
}
