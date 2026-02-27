import { useConfigStore } from '../../stores/configStore';

export default function PrivacyPolicy() {
    const config = useConfigStore();
    const siteName = config.appName || 'AprenderAI';
    const lastUpdate = new Date().toLocaleDateString('pt-BR');

    return (
        <div className="min-h-screen py-12 bg-slate-50 dark:bg-slate-950">
            <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm p-8 sm:p-10 transition-colors">
                    <h1 className="text-3xl font-extrabold text-slate-900 dark:text-white mb-2">
                        Política de Privacidade – {siteName}
                    </h1>

                    <p className="text-sm text-slate-500 dark:text-slate-400 mb-8">
                        Última atualização: {lastUpdate}
                    </p>

                    <div className="space-y-8 text-slate-700 dark:text-slate-300 leading-relaxed text-sm sm:text-base">
                        <section>
                            <h2 className="font-bold text-slate-900 dark:text-slate-100 mb-2 underline decoration-blue-500/30 underline-offset-4">1. Introdução</h2>
                            <p>
                                O {siteName} valoriza sua privacidade. Esta política explica como coletamos,
                                utilizamos e protegemos suas informações ao utilizar nossa plataforma.
                            </p>
                        </section>

                        <section>
                            <h2 className="font-bold text-slate-900 dark:text-slate-100 mb-2 underline decoration-blue-500/30 underline-offset-4">2. Dados Coletados</h2>
                            <ul className="list-disc pl-5 space-y-1">
                                <li>Nome, e-mail e dados de cadastro.</li>
                                <li>Respostas de simulados e redações enviadas.</li>
                                <li>Informações técnicas como IP e tipo de dispositivo.</li>
                                <li>Dados de pagamento processados por parceiros seguros.</li>
                            </ul>
                        </section>

                        <section>
                            <h2 className="font-bold text-slate-900 dark:text-slate-100 mb-2 underline decoration-blue-500/30 underline-offset-4">3. Uso das Informações</h2>
                            <p>
                                Utilizamos seus dados para fornecer correções por IA, gerar planos de estudo,
                                melhorar a experiência da plataforma e processar assinaturas.
                            </p>
                        </section>

                        <section>
                            <h2 className="font-bold text-slate-900 dark:text-slate-100 mb-2 underline decoration-blue-500/30 underline-offset-4">4. Segurança</h2>
                            <p>
                                Implementamos medidas técnicas e organizacionais para proteger suas informações
                                contra acessos não autorizados e uso indevido.
                            </p>
                        </section>

                        <section>
                            <h2 className="font-bold text-slate-900 dark:text-slate-100 mb-2 underline decoration-blue-500/30 underline-offset-4">5. Seus Direitos</h2>
                            <p>
                                Você pode solicitar acesso, correção ou exclusão dos seus dados a qualquer momento.
                            </p>
                        </section>

                        <section className="pt-6 border-t border-slate-200 dark:border-slate-800">
                            <p className="text-sm">
                                Dúvidas? Entre em contato:
                            </p>
                            <a href="mailto:stackupsoftware@gmail.com" className="text-blue-600 dark:text-blue-400 font-medium hover:underline">
                                stackupsoftware@gmail.com
                            </a>
                        </section>
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
