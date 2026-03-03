import { Link } from 'react-router-dom';

export default function StudyPlanEmpty() {
    return (
        <div className="py-16 sm:py-24 flex items-start justify-center min-h-[calc(100vh-160px)] pt-10 pb-16 -mt-[100px]">
            <div className="max-w-3xl w-full mx-auto px-4 sm:px-6 lg:px-8 mt-8">
                <div className="relative bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl overflow-hidden shadow-2xl dark:shadow-[0_8px_30px_rgb(0,0,0,0.5)] rounded-2xl sm:rounded-3xl p-8 sm:p-12 text-center border border-white/50 dark:border-slate-700/50 transition-all duration-300 hover:shadow-blue-500/10 dark:hover:shadow-blue-900/20 group">

                    {/* Efeitos de luz de fundo (Glassmorphism premium) */}
                    <div className="absolute -top-32 -left-32 w-64 h-64 bg-blue-400/20 dark:bg-blue-600/20 rounded-full blur-3xl pointer-events-none transition-transform duration-700 group-hover:translate-x-4 group-hover:translate-y-4"></div>
                    <div className="absolute -bottom-32 -right-32 w-64 h-64 bg-indigo-400/20 dark:bg-indigo-600/20 rounded-full blur-3xl pointer-events-none transition-transform duration-700 group-hover:-translate-x-4 group-hover:-translate-y-4"></div>

                    <div className="relative z-10">
                        {/* Ícone Principal */}
                        <div className="w-20 h-20 sm:w-24 sm:h-24 bg-gradient-to-br from-blue-500 to-indigo-600 text-white rounded-2xl sm:rounded-3xl flex items-center justify-center mx-auto mb-8 shadow-lg shadow-blue-500/30 transform transition-all duration-500 hover:scale-110 hover:rotate-3">
                            <svg className="w-10 h-10 sm:w-12 sm:h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            </svg>
                        </div>

                        <h3 className="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-slate-900 dark:text-white mb-4 tracking-tight">
                            Dados insuficientes para gerar o plano
                        </h3>

                        <p className="text-base sm:text-lg text-slate-600 dark:text-slate-400 mb-10 max-w-xl mx-auto leading-relaxed">
                            Para que o Xavier possa criar um plano realmente efetivo, precisamos de dados sobre seu desempenho.
                            <br /><br />
                            <strong>Requisito:</strong> Resolva pelo menos <strong>50 questões</strong> ou finalize <strong>1 simulado com 50+ questões</strong>.
                        </p>

                        <div className="flex flex-col sm:flex-row justify-center items-center gap-4 sm:gap-6">
                            <Link to="/simulations/create?type=enem" className="w-full sm:w-auto inline-flex items-center justify-center px-8 py-4 bg-blue-600 text-white text-base font-semibold rounded-xl hover:bg-blue-700 hover:shadow-[0_8px_20px_rgba(37,99,235,0.3)] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-300 transform hover:-translate-y-1">
                                Fazer Simulado ENEM
                            </Link>
                            <Link to="/simulations/create?type=concurso" className="w-full sm:w-auto inline-flex items-center justify-center px-8 py-4 bg-white/80 dark:bg-slate-800/80 backdrop-blur-sm border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-base font-semibold rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-offset-2 transition-all duration-300 transform hover:-translate-y-1">
                                Fazer Simulado Concurso
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
