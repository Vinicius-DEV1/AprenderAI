import { useState } from 'react';
import { useAuthStore } from '../stores/authStore';
import api from '../api/axios';

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

    if (!user) return null;

    return (
        <div className="max-w-6xl mx-auto px-4 py-8">
            <style>{`
                .profile-card {
                    background: var(--card-bg, #fff);
                    border: 1px solid var(--border);
                    border-radius: 18px;
                    padding: 24px;
                    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
                    margin-bottom: 24px;
                }
                .dark .profile-card {
                    background: #1e293b;
                    border-color: #334155;
                }
                .profile-label {
                    display: block;
                    font-size: 13px;
                    font-weight: 600;
                    color: var(--muted);
                    margin-bottom: 8px;
                    text-transform: uppercase;
                    letter-spacing: .5px;
                }
                .profile-input {
                    width: 100%;
                    padding: 12px 16px;
                    border-radius: 12px;
                    border: 1px solid var(--border);
                    background: rgba(255, 255, 255, 0.5);
                    color: var(--text);
                    font-size: 14px;
                    transition: all 0.2s ease;
                }
                .dark .profile-input {
                    background: rgba(15, 23, 42, 0.5);
                    border-color: #334155;
                }
                .profile-btn {
                    padding: 12px 24px;
                    border-radius: 14px;
                    color: #fff;
                    font-weight: 700;
                    font-size: 14px;
                    background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%);
                    transition: all 0.2s ease;
                }
                .profile-btn:hover { transform: translateY(-2px); filter: brightness(1.1); }
                .profile-btn:disabled { opacity: 0.7; transform: none; cursor: not-allowed; }
            `}</style>

            <div className="flex justify-between items-center mb-8">
                <div>
                    <h1 className="text-2xl font-bold">Configurações de Perfil</h1>
                    <p className="text-slate-500 dark:text-slate-400 mt-1">Gerencie suas informações e preferências de conta.</p>
                </div>
                <span className="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-bold border border-green-200">Ativo</span>
            </div>

            {message && (
                <div className={`mb-6 p-4 rounded-xl border ${message.type === 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200'}`}>
                    {message.text}
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div className="lg:col-span-2 space-y-8">
                    {/* View Profile */}
                    <div className="profile-card">
                        <h3 className="text-lg font-bold mb-6 flex items-center gap-2">
                            👤 Informações Pessoais
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <h4 className="text-xs font-bold text-slate-400 uppercase mb-1">Nome Completo</h4>
                                <p className="font-bold">{user.name}</p>
                            </div>
                            <div>
                                <h4 className="text-xs font-bold text-slate-400 uppercase mb-1">E-mail</h4>
                                <p className="font-bold">{user.email}</p>
                            </div>
                            <div>
                                <h4 className="text-xs font-bold text-slate-400 uppercase mb-1">Telefone</h4>
                                <p className="font-bold">{user.phone || 'Não informado'}</p>
                            </div>
                        </div>
                    </div>

                    {/* Edit Profile */}
                    <div className="profile-card">
                        <h3 className="text-lg font-bold mb-6 flex items-center gap-2">
                            ✏️ Editar Informações
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
                        <h3 className="text-lg font-bold mb-6 flex items-center gap-2">
                            🔒 Segurança e Senha
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
                                <button type="submit" className="profile-btn bg-red-600 hover:bg-red-700" disabled={loading} style={{ background: 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)' }}>
                                    {loading ? 'Atualizando...' : 'Atualizar Senha'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div className="space-y-8">
                    {/* Plan Status */}
                    <div className="profile-card border-indigo-200 dark:border-indigo-900 bg-indigo-50/50 dark:bg-indigo-900/10">
                        <h3 className="text-lg font-bold mb-4">Seu Plano</h3>
                        <div className="mb-6">
                            <span className="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400">
                                {user.plan?.name || 'Grátis'}
                            </span>
                            <p className="text-sm text-slate-500 dark:text-slate-400 mt-2">
                                Status: <span className="text-green-500 font-semibold">Ativo</span>
                            </p>
                        </div>
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
    );
}
