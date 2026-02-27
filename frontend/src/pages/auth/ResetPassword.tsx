import { useState, useEffect } from 'react';
import { useNavigate, useSearchParams, Link } from 'react-router-dom';
import { useConfigStore } from '../../stores/configStore';
import { resetPassword } from '../../api/auth';

export default function ResetPassword() {
    const navigate = useNavigate();
    const config = useConfigStore();
    const [searchParams] = useSearchParams();

    // We expect the URL to be /reset-password?token=XYZ&email=abc@example.com
    const token = searchParams.get('token') || '';
    const emailParam = searchParams.get('email') || '';

    const [email] = useState(emailParam);
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [errors, setErrors] = useState<string[]>([]);
    const [status, setStatus] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        if (!token) {
            setErrors(['Token de recuperação ausente ou inválido.']);
        }
    }, [token]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setErrors([]);
        setStatus(null);
        setIsLoading(true);

        try {
            await resetPassword({
                token,
                email,
                password,
                password_confirmation: passwordConfirmation
            });

            setStatus('Sua senha foi redefinida com sucesso.');
            setTimeout(() => navigate('/login'), 3000);
        } catch (err: any) {
            if (err.response?.data?.errors) {
                setErrors(Object.values(err.response.data.errors).flat() as string[]);
            } else {
                setErrors([err.response?.data?.message || 'Token inválido ou expirado. Tente novamente.']);
            }
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <div className="auth-page">
            <div className="auth-container">
                <div className="card">
                    <div className="logo">
                        <h1>{config.appName}</h1>
                        <p>Definir Nova Senha</p>
                    </div>

                    {status && (
                        <div className="mb-4 text-sm font-medium text-green-600 p-3 bg-green-50 rounded-lg border border-green-200">
                            {status}
                        </div>
                    )}

                    {errors.length > 0 && (
                        <div className="error">
                            <ul>
                                {errors.map((error, idx) => (
                                    <li key={idx}>{error}</li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <form onSubmit={handleSubmit} className={status ? 'hidden' : 'block'}>
                        <div className="form-group hidden">
                            <label htmlFor="email">E-mail</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                readOnly
                                value={email}
                            />
                        </div>

                        <div className="form-group">
                            <label htmlFor="password">Nova Senha</label>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                required
                                autoFocus
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                            />
                        </div>

                        <div className="form-group">
                            <label htmlFor="password_confirmation">Confirmar Nova Senha</label>
                            <input
                                type="password"
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                value={passwordConfirmation}
                                onChange={(e) => setPasswordConfirmation(e.target.value)}
                            />
                        </div>

                        <button type="submit" className="btn" disabled={isLoading || !token}>
                            {isLoading ? 'Redefinindo...' : 'Redefinir Senha'}
                        </button>
                    </form>

                    {status && (
                        <div className="mt-4 text-center">
                            <Link to="/login" className="btn inline-block px-6">Ir para Login</Link>
                        </div>
                    )}

                    <div className="mt-8 pt-4 border-t border-slate-100 flex justify-center">
                        <Link to="/login" className="text-sm text-blue-600 hover:underline">Voltar para Login</Link>
                    </div>
                </div>
            </div>
        </div>
    );
}
