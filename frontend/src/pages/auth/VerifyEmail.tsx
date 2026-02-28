import { useEffect, useState } from 'react';
import { useSearchParams, useNavigate, Link } from 'react-router-dom';
import { useAuthStore } from '../../stores/authStore';
import { useConfigStore } from '../../stores/configStore';
import api from '../../api/axios';

export default function VerifyEmail() {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const config = useConfigStore();
    const { setUser } = useAuthStore();

    const [status, setStatus] = useState<'loading' | 'success' | 'error'>('loading');
    const [message, setMessage] = useState('Verificando seu e-mail...');

    useEffect(() => {
        const verify = async () => {
            const hash = searchParams.get('hash');
            const id = searchParams.get('id');
            const expires = searchParams.get('expires');
            const signature = searchParams.get('signature');

            if (!hash || !id || !expires || !signature) {
                setStatus('error');
                setMessage('Link de verificação inválido ou incompleto.');
                return;
            }

            try {
                const response = await api.get(`/api/email/verify/${id}/${hash}`, {
                    params: { expires, signature },
                });

                setStatus('success');
                setMessage(response.data.message || 'Seu e-mail foi verificado com sucesso!');

                // Refresh local user data to get the new email_verified_at status
                const userRes = await api.get('/api/user');
                setUser(userRes.data);
            } catch (err: any) {
                setStatus('error');
                setMessage(err.response?.data?.message || 'Link de verificação inválido, expirado ou e-mail já verificado.');
            }
        };

        verify();
    }, [searchParams, setUser]);

    return (
        <div className="auth-page">
            <div className="auth-container">
                <div className="card text-center py-8">
                    <div className="logo mb-8">
                        <h1>{config.appName}</h1>
                        <p>Verificação de Conta</p>
                    </div>

                    <div className="flex flex-col items-center justify-center space-y-4">
                        {status === 'loading' && (
                            <>
                                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mb-4"></div>
                                <h2 className="text-xl font-bold text-slate-800 dark:text-slate-200">Aguarde...</h2>
                                <p className="text-slate-500">{message}</p>
                            </>
                        )}

                        {status === 'success' && (
                            <>
                                <div className="w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mb-4">
                                    <svg className="w-8 h-8 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <h2 className="text-xl font-bold text-slate-800 dark:text-slate-200">Tudo Certo!</h2>
                                <p className="text-slate-500 mb-6">{message}</p>
                                <button
                                    onClick={() => navigate('/dashboard')}
                                    className="btn w-full mt-4"
                                >
                                    Acessar meu Dashboard
                                </button>
                            </>
                        )}

                        {status === 'error' && (
                            <>
                                <div className="w-16 h-16 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mb-4">
                                    <svg className="w-8 h-8 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </div>
                                <h2 className="text-xl font-bold text-slate-800 dark:text-slate-200">Ops! Algo deu errado.</h2>
                                <p className="text-slate-500 mb-6">{message}</p>
                                <Link to="/dashboard" className="text-indigo-600 hover:underline font-medium">
                                    Voltar para o Início
                                </Link>
                            </>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

