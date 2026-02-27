import { Link } from 'react-router-dom';
import { useConfigStore } from '../../stores/configStore';

export default function NotFound() {
    const config = useConfigStore();

    return (
        <div className="min-h-screen bg-slate-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8 font-sans">
            <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
                <div className="bg-white py-12 px-4 shadow-xl shadow-slate-200/50 sm:rounded-2xl sm:px-10 border border-slate-100 flex flex-col items-center text-center">
                    <div className="text-blue-500 mb-6">
                        <svg className="h-24 w-24 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h2 className="text-3xl font-extrabold text-slate-900 mb-2">Página não encontrada</h2>
                    <p className="text-slate-500 mb-8 max-w-sm">
                        Não conseguimos encontrar a página que você está procurando. Parece que o link está quebrado ou a página foi removida.
                    </p>
                    <Link
                        to="/dashboard"
                        className="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-bold rounded-xl shadow-sm tracking-wide text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors w-full sm:w-auto"
                    >
                        Voltar para o Dashboard
                    </Link>

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
