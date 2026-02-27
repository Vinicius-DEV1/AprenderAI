import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useConfigStore } from '../../stores/configStore';
import { forgotPassword } from '../../api/auth';

export default function ForgotPassword() {
    const config = useConfigStore();
    const [email, setEmail] = useState('');
    const [status, setStatus] = useState<string | null>(null);
    const [errors, setErrors] = useState<string[]>([]);
    const [isLoading, setIsLoading] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setErrors([]);
        setStatus(null);
        setIsLoading(true);

        try {
            const response = await forgotPassword({ email });
            setStatus(response.data.status || 'Um link de recuperação foi enviado para seu e-mail.');
        } catch (err: any) {
            if (err.response?.data?.errors) {
                setErrors(Object.values(err.response.data.errors).flat() as string[]);
            } else {
                setErrors([err.response?.data?.message || 'E-mail não encontrado em nossos registros.']);
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
                        <p>Recuperar Senha</p>
                    </div>

                    <p className="text-sm text-slate-600 mb-6 text-center">
                        Esqueceu sua senha? Sem problemas. Basta nos informar seu endereço de e-mail e nós enviaremos um link de redefinição de senha para você.
                    </p>

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

                    <form onSubmit={handleSubmit}>
                        <div className="form-group">
                            <label htmlFor="email">E-mail</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                required
                                autoFocus
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                            />
                        </div>

                        <button type="submit" className="btn" disabled={isLoading}>
                            {isLoading ? 'Enviando Link...' : 'Enviar Link de Recuperação'}
                        </button>
                    </form>

                    <div className="mt-8 pt-4 border-t border-slate-100 flex justify-center">
                        <Link to="/login" className="text-sm text-blue-600 hover:underline">Voltar para Login</Link>
                    </div>
                </div>
            </div>
        </div>
    );
}
