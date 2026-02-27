import { useConfigStore } from '../../stores/configStore';
import { useNavigate } from 'react-router-dom';

export default function ServerError() {
    const config = useConfigStore();
    const navigate = useNavigate();

    return (
        <div className="min-h-screen bg-slate-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8 font-sans">
            <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
                <div className="bg-white py-12 px-4 shadow-xl shadow-slate-200/50 sm:rounded-2xl sm:px-10 border border-slate-100 flex flex-col items-center text-center">
                    <div className="text-red-500 mb-6">
                        <svg className="h-24 w-24 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <h2 className="text-3xl font-extrabold text-slate-900 mb-2">Erro Interno</h2>
                    <p className="text-slate-500 mb-8 max-w-sm leading-relaxed">
                        Ops! Nosso servidor encontrou um problema e não pôde concluir sua requisição. Pense nisso como um contratempo na hora da prova.
                    </p>
                    <div className="flex flex-col sm:flex-row gap-3 w-full justify-center">
                        <button
                            onClick={() => window.location.reload()}
                            className="inline-flex items-center justify-center px-6 py-3 border border-slate-300 shadow-sm text-base font-bold rounded-xl text-slate-700 bg-white hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors w-full sm:w-auto"
                        >
                            Tentar Novamente
                        </button>
                        <button
                            onClick={() => navigate('/dashboard')}
                            className="inline-flex items-center justify-center px-6 py-3 border border-transparent shadow-sm text-base font-bold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors w-full sm:w-auto"
                        >
                            Voltar Seguro
                        </button>
                    </div>

                    <div className="mt-8 pt-6 border-t border-slate-100 w-full">
                        <p className="text-sm font-medium text-slate-400">
                            {config.appName} &copy; {new Date().getFullYear()}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
