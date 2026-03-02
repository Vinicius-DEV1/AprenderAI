import { useEffect } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useAuthStore } from '../../stores/authStore';
import { getUser } from '../../api/auth';

export default function PlanSuccess() {
    const navigate = useNavigate();
    const location = useLocation();
    const { user, setUser } = useAuthStore();

    // Attempt to extract planName from the query string (e.g. ?planName=Premium)
    const queryParams = new URLSearchParams(location.search);
    const planName = queryParams.get('planName') || 'Premium';

    useEffect(() => {
        const refreshUser = async () => {
            try {
                const response = await getUser();
                if (response.data.user) {
                    setUser(response.data.user);
                }
            } catch (error) {
                console.error("Failed to refresh user data after checkout");
            }
        };
        refreshUser();
    }, [setUser]);

    return (
        <div className="min-h-[80vh] flex flex-col items-center justify-center p-4 py-16">
            <div className="w-full max-w-lg bg-white dark:bg-slate-900 rounded-3xl shadow-xl border border-slate-100 dark:border-slate-800 overflow-hidden text-center p-8 sm:p-12 animate-in zoom-in-95 duration-500">
                {/* Check Icon with celebration effect */}
                <div className="relative w-24 h-24 mx-auto mb-8">
                    <div className="absolute inset-0 bg-green-100 dark:bg-green-900/30 rounded-full animate-ping opacity-75"></div>
                    <div className="relative w-full h-full bg-gradient-to-tr from-green-500 to-emerald-400 rounded-full flex items-center justify-center shadow-lg shadow-green-200 dark:shadow-none">
                        <svg className="w-12 h-12 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </div>

                <h1 className="text-3xl font-black text-slate-900 dark:text-white mb-4 tracking-tight">
                    Pagamento Aprovado!
                </h1>

                <p className="text-slate-600 dark:text-slate-400 text-lg mb-2">
                    Parabéns{user?.name ? `, ${user.name.split(' ')[0]}` : ''}!
                </p>
                <p className="text-slate-600 dark:text-slate-400 text-lg mb-8">
                    Agora você é <strong className="text-blue-600 dark:text-blue-400 font-bold">{planName}</strong> e já pode aproveitar todos os benefícios exclusivos da plataforma.
                </p>

                <div className="bg-slate-50 dark:bg-slate-800/50 rounded-2xl p-6 mb-8 border border-slate-100 dark:border-slate-700/50">
                    <h3 className="text-sm font-bold text-slate-800 dark:text-slate-200 mb-3 flex items-center justify-center gap-2">
                        <svg className="w-5 h-5 text-indigo-500" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clipRule="evenodd" /></svg>
                        O que acontece agora?
                    </h3>
                    <ul className="text-sm text-slate-600 dark:text-slate-400 text-left space-y-3">
                        <li className="flex items-start gap-2">
                            <span className="text-green-500 font-bold">✓</span> Seus limites de cota atualizados foram creditados.
                        </li>
                        <li className="flex items-start gap-2">
                            <span className="text-green-500 font-bold">✓</span> Recibos fiscais serão enviados ao seu email em breve.
                        </li>
                    </ul>
                </div>

                <button
                    onClick={() => navigate('/dashboard')}
                    className="w-full py-4 px-6 bg-slate-900 hover:bg-slate-800 dark:bg-white dark:hover:bg-slate-100 text-white dark:text-slate-900 font-bold text-lg rounded-xl shadow-lg transition-all transform active:scale-95 flex items-center justify-center gap-2"
                >
                    Acessar meu Dashboard
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                </button>
            </div>
        </div>
    );
}
