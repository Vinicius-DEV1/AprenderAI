import { useAuthStore } from '../stores/authStore';
import { sendVerificationEmail } from '../api/auth';
import { toast } from 'sonner';
import { useState } from 'react';

export default function EmailVerificationBanner() {
    const { user } = useAuthStore();
    const [isSending, setIsSending] = useState(false);

    if (!user) return null;
    if (user.email_verified_at) return null;

    const handleResend = async () => {
        setIsSending(true);
        try {
            await sendVerificationEmail();
            toast.success('Um novo link de confirmação foi enviado para o seu e-mail!');
        } catch (error: any) {
            if (error.response?.status === 429) {
                toast.error('Aguarde um momento antes de pedir um novo link.');
            } else {
                toast.error(error.response?.data?.message || 'Erro ao reenviar link. Tente novamente mais tarde.');
            }
        } finally {
            setIsSending(false);
        }
    };

    return (
        <div className="bg-yellow-50 dark:bg-yellow-900/30 border-b border-yellow-200 dark:border-yellow-900/50 p-3 sm:px-6 lg:px-8">
            <div className="flex flex-col sm:flex-row items-center justify-between gap-3 max-w-7xl mx-auto">
                <div className="flex items-center gap-3">
                    <div className="flex-shrink-0">
                        <svg className="h-5 w-5 text-yellow-600 dark:text-yellow-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                        </svg>
                    </div>
                    <p className="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                        Você ainda não confirmou seu e-mail. Confirme para garantir a recuperação de senha e a segurança da sua conta.
                    </p>
                </div>
                <div className="flex-shrink-0 w-full sm:w-auto mt-2 sm:mt-0">
                    <button
                        onClick={handleResend}
                        disabled={isSending}
                        className="w-full sm:w-auto focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 flex items-center justify-center px-4 py-1.5 border border-transparent text-sm font-medium rounded-md text-yellow-900 bg-yellow-200 hover:bg-yellow-300 dark:bg-yellow-800 dark:text-yellow-100 dark:hover:bg-yellow-700 transition-colors disabled:opacity-50"
                    >
                        {isSending ? 'Enviando...' : 'Reenviar E-mail'}
                    </button>
                </div>
            </div>
        </div>
    );
}
