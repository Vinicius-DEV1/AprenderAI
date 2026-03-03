import { useState } from 'react';
import { useAuthStore } from '../stores/authStore';
import api from '../api/axios';
import { sendVerificationEmail } from '../api/auth';
import { toast } from 'sonner';

export default function Profile() {
    const { user, setUser } = useAuthStore();
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState<{ type: 'success' | 'error', text: string } | null>(null);

    const [profileData, setProfileData] = useState({
        name: user?.name || '',
        email: user?.email || '',
        phone: user?.phone || '',
    });

    const [pwdData, setPwdData] = useState({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const handleProfileSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setMessage(null);
        try {
            const res = await api.put('/api/v1/user/profile', profileData);
            setUser(res.data.user);
            setMessage({ type: 'success', text: res.data.message });
        } catch (err: any) {
            setMessage({ type: 'error', text: err.response?.data?.message || 'Erro ao atualizar perfil.' });
        } finally {
            setLoading(false);
        }
    };

    const handlePasswordSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setMessage(null);
        try {
            const res = await api.put('/api/v1/user/password', pwdData);
            setMessage({ type: 'success', text: res.data.message });
            setPwdData({ current_password: '', password: '', password_confirmation: '' });
        } catch (err: any) {
            setMessage({ type: 'error', text: err.response?.data?.message || 'Erro ao atualizar senha.' });
        } finally {
            setLoading(false);
        }
    };

    const handleResendVerification = async () => {
        setLoading(true);
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
            setLoading(false);
        }
    };

    if (!user) return null;

    return (
        <div className="max-w-6xl mx-auto px-4 py-8">
            <style>{`
                .wrap {
                    max-width: 1120px;
                    margin: 0 auto;
                    position: relative;
                }
                .hero {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 14px;
                    margin: 8px 0 24px;
                    padding: 20px;
                    border-radius: 18px;
                    background: linear-gradient(135deg, rgba(255, 255, 255, .84), rgba(255, 255, 255, .95));
                    border: 1px solid rgba(15, 23, 42, .08);
                    box-shadow: 0 10px 22px rgba(15, 23, 42, .06);
                    backdrop-filter: blur(12px);
                }
                .dark .hero {
                    background: linear-gradient(135deg, rgba(30, 41, 59, .9), rgba(15, 23, 42, .95));
                    border-color: rgba(255, 255, 255, 0.06);
                }
                .profile-card {
                    background: linear-gradient(135deg, rgba(255, 255, 255, .86), rgba(255, 255, 255, .96));
                    border: 1px solid rgba(15, 23, 42, .06);
                    border-radius: 18px;
                    padding: 24px;
                    box-shadow: 0 10px 22px rgba(15, 23, 42, .06);
                    backdrop-filter: blur(12px);
                    margin-bottom: 24px;
                }
                .dark .profile-card {
                    background: linear-gradient(135deg, rgba(30, 41, 59, .85), rgba(15, 23, 42, .95));
                    border-color: rgba(255, 255, 255, 0.06);
                }
                .profile-label {
                    display: block;
                    font-size: 13px;
                    font-weight: 600;
                    color: #64748b;
                    margin-bottom: 8px;
                    text-transform: uppercase;
                    letter-spacing: .5px;
                }
                .profile-input {
                    width: 100%;
                    padding: 12px 16px;
                    border-radius: 12px;
                    border: 1px solid rgba(15, 23, 42, .08);
                    background: rgba(255, 255, 255, 0.5);
                    color: inherit;
                    font-size: 14px;
                    transition: all 0.2s ease;
                }
                .dark .profile-input {
                    background: rgba(15, 23, 42, 0.5);
                    border-color: rgba(255, 255, 255, 0.08);
                }
                .profile-btn {
                    padding: 12px 24px;
                    border-radius: 14px;
                    color: #fff;
                    font-weight: 700;
                    font-size: 14px;
                    background: linear-gradient(135deg, #2563eb 0%, #4f46e5 45%, #7c3aed 100%);
                    box-shadow: 0 10px 20px rgba(37, 99, 235, .15);
                    transition: all 0.2s ease;
                    border: none;
                    cursor: pointer;
                }
                .profile-btn:hover { transform: translateY(-2px); box-shadow: 0 14px 28px rgba(37, 99, 235, .25); filter: brightness(1.1); }
                .profile-btn:disabled { opacity: 0.7; transform: none; cursor: not-allowed; }
                
                .info-item h4 {
                    font-size: 11px;
                    font-weight: 600;
                    color: #8a9ab2;
                    text-transform: uppercase;
                    margin-bottom: 4px;
                }
                .info-item p {
                    font-size: 16px;
                    font-weight: 700;
                }
            `}</style>

            <div className="wrap -mt-[10px]">
                <div className="hero">
                    <div>
                        <h1 className="text-xl font-bold">Configurações de Perfil</h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">Gerencie suas informações e preferências de conta.</p>
                    </div>
                    <span className="px-3 py-1 rounded-full bg-blue-50 text-blue-700 text-xs font-bold border border-blue-100 dark:bg-indigo-900/30 dark:text-indigo-300 dark:border-indigo-800">Ativo</span>
                </div>

                {message && (
                    <div className={`mb-6 p-4 rounded-xl border ${message.type === 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200'}`}>
                        {message.text}
                    </div>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 space-y-6">
                        {/* View Profile */}
                        <div className="profile-card">
                            <h3 className="text-lg font-bold mb-6 flex items-center gap-2">
                                <svg className="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Informações Pessoais
                            </h3>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div className="info-item">
                                    <h4>Nome Completo</h4>
                                    <p>{user.name}</p>
                                </div>
                                <div className="info-item">
                                    <h4>E-mail</h4>
                                    <p className="flex items-center gap-2 flex-wrap">
                                        <span className="truncate">{user.email}</span>
                                        {user.email_verified_at ? (
                                            <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                                Confirmado
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">
                                                Pendente
                                            </span>
                                        )}
                                    </p>
                                    {!user.email_verified_at && (
                                        <button
                                            onClick={handleResendVerification}
                                            disabled={loading}
                                            className="mt-2 text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 font-medium disabled:opacity-50"
                                        >
                                            Reenviar E-mail de Confirmação
                                        </button>
                                    )}
                                </div>
                                <div className="info-item">
                                    <h4>Telefone</h4>
                                    <p>{user.phone || 'Não informado'}</p>
                                </div>
                            </div>
                        </div>

                        {/* Edit Profile */}
                        <div className="profile-card">
                            <h3 className="text-lg font-bold mb-6 flex items-center gap-2">
                                <svg className="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Editar Informações
                            </h3>
                            <form onSubmit={handleProfileSubmit}>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                    <div>
                                        <label className="profile-label">Nome</label>
                                        <input
                                            type="text"
                                            className="profile-input"
                                            value={profileData.name}
                                            onChange={e => setProfileData({ ...profileData, name: e.target.value })}
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="profile-label">Telefone</label>
                                        <input
                                            type="text"
                                            className="profile-input"
                                            value={profileData.phone}
                                            onChange={e => setProfileData({ ...profileData, phone: e.target.value })}
                                            placeholder="(00) 00000-0000"
                                        />
                                    </div>
                                </div>
                                <div className="mb-6">
                                    <label className="profile-label">E-mail</label>
                                    <input
                                        type="email"
                                        className="profile-input"
                                        value={profileData.email}
                                        onChange={e => setProfileData({ ...profileData, email: e.target.value })}
                                        required
                                    />
                                </div>
                                <div className="flex justify-end">
                                    <button type="submit" className="profile-btn" disabled={loading}>
                                        {loading ? 'Salvando...' : 'Salvar Alterações'}
                                    </button>
                                </div>
                            </form>
                        </div>

                        {/* Security */}
                        <div className="profile-card">
                            <h3 className="text-lg font-bold mb-6 flex items-center gap-2 text-red-600 dark:text-red-400">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                Segurança e Senha
                            </h3>
                            <form onSubmit={handlePasswordSubmit}>
                                <div className="mb-6">
                                    <label className="profile-label">Senha Atual</label>
                                    <input
                                        type="password"
                                        className="profile-input"
                                        value={pwdData.current_password}
                                        onChange={e => setPwdData({ ...pwdData, current_password: e.target.value })}
                                        required
                                    />
                                </div>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                    <div>
                                        <label className="profile-label">Nova Senha</label>
                                        <input
                                            type="password"
                                            className="profile-input"
                                            value={pwdData.password}
                                            onChange={e => setPwdData({ ...pwdData, password: e.target.value })}
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="profile-label">Confirmar Senha</label>
                                        <input
                                            type="password"
                                            className="profile-input"
                                            value={pwdData.password_confirmation}
                                            onChange={e => setPwdData({ ...pwdData, password_confirmation: e.target.value })}
                                            required
                                        />
                                    </div>
                                </div>
                                <div className="flex justify-end">
                                    <button type="submit" className="profile-btn" disabled={loading} style={{ background: 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)', boxShadow: '0 10px 20px rgba(239, 68, 68, .15)' }}>
                                        {loading ? 'Atualizando...' : 'Atualizar Senha'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div className="space-y-6">
                        {/* Plan Status */}
                        <div className="profile-card" style={{ background: 'linear-gradient(135deg, rgba(79, 70, 229, 0.1) 0%, rgba(124, 58, 237, 0.1) 100%)', borderColor: 'rgba(99, 102, 241, 0.2)' }}>
                            <h3 className="text-lg font-bold mb-4">Seu Plano</h3>
                            <div className="mb-6">
                                <span className="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400">
                                    {user.plan?.name || 'Grátis'}
                                </span>
                                <p className="text-sm text-slate-500 dark:text-slate-400 mt-2">
                                    Status: <span className="text-green-500 font-semibold">Ativo</span>
                                </p>
                            </div>

                            {/* Progress Bar logic as per Blade */}
                            {user.simulation_limit && (
                                <div className="space-y-3 mb-6">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-slate-500 dark:text-slate-400 font-medium">Simulados restantes:</span>
                                        <span className="font-bold text-slate-700 dark:text-slate-200">
                                            {user.simulation_limit.remaining}
                                        </span>
                                    </div>
                                    <div className="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-1.5 overflow-hidden">
                                        <div
                                            className="bg-indigo-500 h-1.5 rounded-full transition-all duration-500"
                                            style={{ width: `${Math.max(0, Math.min(100, (user.simulation_limit.remaining / (user.simulation_limit.total || 1)) * 100))}%` }}
                                        ></div>
                                    </div>
                                    <p className="text-[10px] text-slate-400 uppercase font-bold tracking-wider">
                                        Uso: {user.simulation_limit.total - user.simulation_limit.remaining} / {user.simulation_limit.total}
                                    </p>
                                </div>
                            )}

                            <a href="/plans" className="profile-btn w-full block text-center">Fazer Upgrade</a>
                        </div>

                        <div className="profile-card bg-slate-50 dark:bg-slate-800/50 border-dashed">
                            <h4 className="text-sm font-bold mb-2 flex items-center gap-2">
                                💡 Dica de Segurança
                            </h4>
                            <p className="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                Use senhas fortes com uma mistura de letras, números e símbolos para manter sua conta segura.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
