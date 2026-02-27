import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useConfigStore } from '../../stores/configStore';
import { useAuthStore } from '../../stores/authStore';
import { register as apiRegister, getUser } from '../../api/auth';
import { toast } from 'sonner';

export default function RegisterPage() {
    const navigate = useNavigate();
    const config = useConfigStore();
    const setUser = useAuthStore((state) => state.setUser);

    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [errors, setErrors] = useState<string[]>([]);
    const [isLoading, setIsLoading] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setErrors([]);
        setIsLoading(true);

        try {
            await apiRegister({
                name,
                email,
                password,
                password_confirmation: passwordConfirmation
            });

            const response = await getUser();
            setUser(response.data.user);

            toast.success('Conta criada com sucesso! Verifique seu e-mail para validar sua conta e liberar todos os recursos.', {
                duration: 8000,
            });

            navigate('/dashboard');
        } catch (err: any) {
            if (err.response?.data?.errors) {
                setErrors(Object.values(err.response.data.errors).flat() as string[]);
            } else {
                setErrors([err.response?.data?.message || 'Ocorreu um erro ao criar a conta.']);
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
                        <p>Crie sua conta gratuita</p>
                    </div>

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
                            <label htmlFor="name">Nome completo</label>
                            <input
                                type="text"
                                id="name"
                                name="name"
                                required
                                autoFocus
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                            />
                        </div>

                        <div className="form-group">
                            <label htmlFor="email">E-mail</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                required
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
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
                            />
                        </div>

                        <div className="form-group">
                            <label htmlFor="password_confirmation">Confirme a senha</label>
                            <input
                                type="password"
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                value={passwordConfirmation}
                                onChange={(e) => setPasswordConfirmation(e.target.value)}
                            />
                        </div>

                        <button type="submit" className="btn" disabled={isLoading}>
                            {isLoading ? 'Criando...' : 'Criar Conta Gratuita'}
                        </button>
                    </form>

                    {config.googleLoginEnabled && (
                        <>
                            <div className="divider">ou</div>
                            <a href={`${import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'}/auth/google`} className="btn-google">
                                <svg className="w-5 h-5 mr-2" viewBox="0 0 24 24">
                                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4" />
                                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853" />
                                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05" />
                                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335" />
                                </svg>
                                Cadastrar com Google
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
                  box-sizing: border-box;
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

                    <div className="divider">
                        Ao criar uma conta, você concorda com nossos <Link to="/uso-justo" className="text-blue-600 hover:underline">Termos de Uso</Link>
                    </div>

                    <div className="link">
                        Já tem uma conta? <Link to="/login">Faça login</Link>
                    </div>
                </div>
            </div>
        </div>
    );
}
