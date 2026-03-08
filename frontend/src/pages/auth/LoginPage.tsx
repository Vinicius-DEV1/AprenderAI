import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useConfigStore } from '../../stores/configStore';
import { useAuthStore } from '../../stores/authStore';
import { login as apiLogin, getUser } from '../../api/auth';
import { useQueryClient } from '@tanstack/react-query';

export default function LoginPage() {
    const navigate = useNavigate();
    const config = useConfigStore();
    const setUser = useAuthStore((state) => state.setUser);
    const queryClient = useQueryClient();

    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [remember, setRemember] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setError(null);
        setIsLoading(true);

        try {
            await apiLogin({ email, password, remember });
            queryClient.clear();

            // Fetch user data after successful login
            const response = await getUser();
            setUser(response.data.user);

            navigate('/dashboard');
        } catch (err: any) {
            setError(
                err.response?.data?.message ||
                'As credenciais fornecidas não correspondem aos nossos registros.'
            );
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
                        <p>Entre na sua conta</p>
                    </div>

                    {error && (
                        <div className="error">
                            <ul>
                                <li>{error}</li>
                            </ul>
                        </div>
                    )}

                    <form onSubmit={handleSubmit} method="POST" action="/login">
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
                                autoComplete="username"
                            />
                        </div>

                        <div className="form-group">
                            <label htmlFor="password">Senha</label>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                required
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                autoComplete="current-password"
                            />
                        </div>

                        <div className="checkbox-group">
                            <input
                                type="checkbox"
                                id="remember"
                                name="remember"
                                checked={remember}
                                onChange={(e) => setRemember(e.target.checked)}
                            />
                            <label htmlFor="remember">Lembrar de mim</label>
                        </div>

                        <button type="submit" className="btn" disabled={isLoading}>
                            {isLoading ? 'Entrando...' : 'Entrar'}
                        </button>
                    </form>

                    {config.googleLoginEnabled && (
                        <>
                            <div className="divider">ou</div>
                            <a href="/auth/google" className="btn-google">
                                <svg className="w-5 h-5 mr-2" viewBox="0 0 24 24">
                                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4" />
                                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853" />
                                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05" />
                                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335" />
                                </svg>
                                Entrar com Google
                            </a>

                            <style>{`
              .btn-google {
                  width: 100%;
                  padding: 12px;
                  background: white;
                  color: #3c4043;
                  border: 1px solid #dadce0;
                  border-radius: 8px;
                  font-size: 15px;
                  font-weight: 500;
                  cursor: pointer;
                  transition: all 0.2s;
                  font-family: 'Inter', sans-serif;
                  display: flex;
                  align-items: center;
                  justify-content: center;
                  text-decoration: none;
                  margin-top: 16px;
              }
              .btn-google:hover {
                  background: #f7f8f8;
                  border-color: #d2e3fc;
                  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
              }
              .btn-google svg {
                  width: 20px;
                  height: 20px;
                  margin-right: 12px;
              }
            `}</style>
                        </>
                    )}

                    <div className="link">
                        Não tem uma conta? <Link to="/register">Cadastre-se gratuitamente</Link>
                    </div>

                    <div className="mt-8 pt-4 border-t border-slate-100 flex justify-center gap-4">
                        <Link to="/privacidade" className="text-[10px] text-slate-400 hover:text-blue-500 transition-colors">Privacidade</Link>
                        <Link to="/uso-justo" className="text-[10px] text-slate-400 hover:text-blue-500 transition-colors">Uso Justo</Link>
                    </div>
                </div>
            </div>
        </div>
    );
}
