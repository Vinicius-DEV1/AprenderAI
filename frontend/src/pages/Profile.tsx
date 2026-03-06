import { useState, useEffect } from 'react';
import { useAuthStore } from '../stores/authStore';
import api from '../api/axios';
import { sendVerificationEmail } from '../api/auth';
import { toast } from 'sonner';
import { Link } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import QuestionCard from '../components/QuestionCard';

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

    const [activeTab, setActiveTab] = useState<'summary' | 'favorites' | 'notebooks' | 'notes'>('summary');

    // Favorites state
    const [favorites, setFavorites] = useState<any[]>([]);
    const [favPage, setFavPage] = useState(1);
    const [favTotal, setFavTotal] = useState(0);
    const [favLoading, setFavLoading] = useState(false);
    const [favHasNext, setFavHasNext] = useState(false);
    const [favViewMode, setFavViewMode] = useState<'card' | 'list'>('card');

    // Notebooks state
    const [notebooks, setNotebooks] = useState<any[]>([]);
    const [nbLoading, setNbLoading] = useState(false);
    const [newNbName, setNewNbName] = useState('');
    const [creatingNb, setCreatingNb] = useState(false);

    // Annotations state
    const [notes, setNotes] = useState<any[]>([]);
    const [notesPage, setNotesPage] = useState(1);
    const [notesTotal, setNotesTotal] = useState(0);
    const [notesLoading, setNotesLoading] = useState(false);
    const [notesHasNext, setNotesHasNext] = useState(false);

    useEffect(() => {
        if (activeTab === 'favorites') {
            loadFavorites();
        } else if (activeTab === 'notebooks') {
            loadNotebooks();
        } else if (activeTab === 'notes') {
            loadNotes();
        }
    }, [activeTab, favPage, notesPage]);

    const loadFavorites = async () => {
        setFavLoading(true);
        try {
            const res = await api.get(`/api/v1/questions?is_favorite=1&page=${favPage}`);
            setFavorites(res.data.data);
            setFavTotal(res.data.total);
            setFavHasNext(!!res.data.next_page_url);
        } catch (e) {
            toast.error('Erro ao carregar favoritas');
        } finally {
            setFavLoading(false);
        }
    };

    const loadNotebooks = async () => {
        setNbLoading(true);
        try {
            const res = await api.get('/api/v1/notebooks');
            setNotebooks(res.data);
        } catch (e) {
            toast.error('Erro ao carregar cadernos');
        } finally {
            setNbLoading(false);
        }
    };

    const loadNotes = async () => {
        setNotesLoading(true);
        try {
            const res = await api.get(`/api/v1/questions?has_notes=1&page=${notesPage}`);
            setNotes(res.data.data);
            setNotesTotal(res.data.total);
            setNotesHasNext(!!res.data.next_page_url);
        } catch (e) {
            toast.error('Erro ao carregar anotações');
        } finally {
            setNotesLoading(false);
        }
    };

    const handleCreateNotebook = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!newNbName.trim()) return;
        setCreatingNb(true);
        try {
            const res = await api.post('/api/v1/notebooks', { name: newNbName });
            setNotebooks([res.data.notebook, ...notebooks]);
            setNewNbName('');
            toast.success('Caderno criado!');
        } catch (e: any) {
            toast.error(e.response?.data?.message || 'Erro ao criar caderno');
        } finally {
            setCreatingNb(false);
        }
    };

    const handleDeleteNotebook = async (id: number) => {
        if (!confirm('Excluir este caderno?')) return;
        try {
            await api.delete(`/api/v1/notebooks/${id}`);
            setNotebooks(notebooks.filter(n => n.id !== id));
            toast.success('Caderno excluído');
        } catch (e) {
            toast.error('Erro ao excluir caderno');
        }
    };

    const handleRemoveFavorite = async (id: number) => {
        try {
            await api.post(`/api/v1/questions/${id}/favorite`);
            setFavorites(favorites.filter(f => f.id !== id));
            setFavTotal(prev => prev - 1);
            toast.success('Removida dos favoritos');
        } catch (e) {
            toast.error('Erro ao remover favorito');
        }
    };

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
                <div className="hero !mb-2">
                    <div>
                        <h1 className="text-xl font-bold">Meu Perfil</h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">Gerencie sua conta, cadernos e questões favoritas.</p>
                    </div>
                    <span className="px-3 py-1 rounded-full bg-blue-50 text-blue-700 text-xs font-bold border border-blue-100 dark:bg-indigo-900/30 dark:text-indigo-300 dark:border-indigo-800 uppercase tracking-tighter">Status: Ativo</span>
                </div>

                {/* Tab Navigation */}
                <div className="flex gap-2 mb-6 border-b border-gray-100 dark:border-slate-800 pb-px overflow-x-auto whitespace-nowrap scrollbar-none">
                    <button
                        onClick={() => setActiveTab('summary')}
                        className={`px-4 py-3 text-sm font-bold transition-all border-b-2 gap-2 flex items-center ${activeTab === 'summary' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-400 hover:text-gray-600'}`}
                    >
                        📊 Resumo
                    </button>
                    <button
                        onClick={() => setActiveTab('favorites')}
                        className={`px-4 py-3 text-sm font-bold transition-all border-b-2 gap-2 flex items-center ${activeTab === 'favorites' ? 'border-amber-500 text-amber-600' : 'border-transparent text-gray-400 hover:text-gray-600'}`}
                    >
                        ⭐ Favoritas {favTotal > 0 && <span className="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full">{favTotal}</span>}
                    </button>
                    <button
                        onClick={() => setActiveTab('notebooks')}
                        className={`px-4 py-3 text-sm font-bold transition-all border-b-2 gap-2 flex items-center ${activeTab === 'notebooks' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-400 hover:text-gray-600'}`}
                    >
                        📁 Meus Cadernos
                    </button>
                    <button
                        onClick={() => setActiveTab('notes')}
                        className={`px-4 py-3 text-sm font-bold transition-all border-b-2 gap-2 flex items-center ${activeTab === 'notes' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-400 hover:text-gray-600'}`}
                    >
                        📝 Minhas Anotações {notesTotal > 0 && <span className="text-[10px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded-full">{notesTotal}</span>}
                    </button>
                </div>

                {message && (
                    <div className={`mb-6 p-4 rounded-xl border ${message.type === 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200'}`}>
                        {message.text}
                    </div>
                )}

                <div className="min-h-[400px]">
                    <AnimatePresence mode="wait">
                        {activeTab === 'summary' && (
                            <motion.div
                                key="summary"
                                initial={{ opacity: 0, x: -10 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: 10 }}
                                className="grid grid-cols-1 lg:grid-cols-3 gap-6"
                            >
                                <div className="lg:col-span-2 space-y-6">
                                    {/* View Profile */}
                                    <div className="profile-card !mb-0">
                                        <h3 className="text-lg font-bold mb-6 flex items-center gap-2">
                                            <svg className="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            Informações Pessoais
                                        </h3>
                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                            <div className="info-item">
                                                <h4>Nome Completo</h4>
                                                <p className="text-sm">{user.name}</p>
                                            </div>
                                            <div className="info-item">
                                                <h4>E-mail</h4>
                                                <p className="flex items-center gap-2 flex-wrap text-sm">
                                                    <span className="truncate">{user.email}</span>
                                                    {user.email_verified_at ? (
                                                        <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 uppercase">Confirmado</span>
                                                    ) : (
                                                        <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400 uppercase">Pendente</span>
                                                    )}
                                                </p>
                                                {!user.email_verified_at && (
                                                    <button onClick={handleResendVerification} disabled={loading} className="mt-2 text-[11px] text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 font-bold uppercase underline">Reenviar Confirmação</button>
                                                )}
                                            </div>
                                            <div className="info-item">
                                                <h4>Telefone</h4>
                                                <p className="text-sm">{user.phone || '—'}</p>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Edit Profile */}
                                    <div className="profile-card !mb-0">
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
                                                    <input type="text" className="profile-input" value={profileData.name} onChange={e => setProfileData({ ...profileData, name: e.target.value })} required />
                                                </div>
                                                <div>
                                                    <label className="profile-label">Telefone</label>
                                                    <input type="text" className="profile-input" value={profileData.phone} onChange={e => setProfileData({ ...profileData, phone: e.target.value })} placeholder="(00) 00000-0000" />
                                                </div>
                                            </div>
                                            <div className="mb-6">
                                                <label className="profile-label">E-mail</label>
                                                <input type="email" className="profile-input" value={profileData.email} onChange={e => setProfileData({ ...profileData, email: e.target.value })} required />
                                            </div>
                                            <div className="flex justify-end">
                                                <button type="submit" className="profile-btn" disabled={loading}>{loading ? 'Salvando...' : 'Salvar Alterações'}</button>
                                            </div>
                                        </form>
                                    </div>

                                    {/* Security */}
                                    <div className="profile-card !mb-0">
                                        <h3 className="text-lg font-bold mb-6 flex items-center gap-2 text-red-600 dark:text-red-400">
                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                            Segurança e Senha
                                        </h3>
                                        <form onSubmit={handlePasswordSubmit}>
                                            <div className="mb-6">
                                                <label className="profile-label">Senha Atual</label>
                                                <input type="password" title="Senha Atual" className="profile-input" value={pwdData.current_password} onChange={e => setPwdData({ ...pwdData, current_password: e.target.value })} required />
                                            </div>
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                                <div>
                                                    <label className="profile-label">Nova Senha</label>
                                                    <input type="password" title="Nova Senha" className="profile-input" value={pwdData.password} onChange={e => setPwdData({ ...pwdData, password: e.target.value })} required />
                                                </div>
                                                <div>
                                                    <label className="profile-label">Confirmar Senha</label>
                                                    <input type="password" title="Confirmar Senha" className="profile-input" value={pwdData.password_confirmation} onChange={e => setPwdData({ ...pwdData, password_confirmation: e.target.value })} required />
                                                </div>
                                            </div>
                                            <div className="flex justify-end">
                                                <button type="submit" className="profile-btn" disabled={loading} style={{ background: 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)' }}>{loading ? 'Atualizando...' : 'Atualizar Senha'}</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <div className="space-y-6">
                                    {/* Plan Status */}
                                    <div className="profile-card" style={{ background: 'linear-gradient(135deg, rgba(79, 70, 229, 0.1) 0%, rgba(124, 58, 237, 0.1) 100%)', borderColor: 'rgba(99, 102, 241, 0.2)' }}>
                                        <div className="flex justify-between items-start mb-4">
                                            <h3 className="text-lg font-bold">Seu Plano</h3>
                                            {user.subscriptions?.some((s: any) => s.status === 'active' && s.is_manual_grant) && (
                                                <span className="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-indigo-200 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300">
                                                    Acesso Concedido
                                                </span>
                                            )}
                                        </div>
                                        <div className="mb-6">
                                            <span className="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400">{user.plan?.name || 'Grátis'}</span>
                                            <p className="text-sm text-slate-500 dark:text-slate-400 mt-2">Uso ilimitado das ferramentas principais.</p>
                                        </div>
                                        {user.simulation_limit && (
                                            <div className="space-y-3 mb-6">
                                                <div className="flex justify-between text-sm">
                                                    <span className="text-slate-500 dark:text-slate-400 font-medium">Simulados restantes:</span>
                                                    <span className="font-bold text-slate-700 dark:text-slate-200">{user.simulation_limit.remaining}</span>
                                                </div>
                                                <div className="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-1.5 overflow-hidden">
                                                    <div className="bg-indigo-500 h-1.5 rounded-full transition-all duration-500" style={{ width: `${Math.max(0, Math.min(100, (user.simulation_limit.remaining / (user.simulation_limit.total || 1)) * 100))}%` }}></div>
                                                </div>
                                            </div>
                                        )}
                                        <Link to="/plans" className="profile-btn w-full block text-center">Fazer Upgrade</Link>
                                    </div>
                                    <div className="profile-card bg-slate-50 dark:bg-slate-800/50 border-dashed">
                                        <h4 className="text-sm font-bold mb-2 flex items-center gap-2">💡 Dica de Segurança</h4>
                                        <p className="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Use senhas fortes com uma mistura de letras, números e símbolos.</p>
                                    </div>
                                </div>
                            </motion.div>
                        )}

                        {activeTab === 'favorites' && (
                            <motion.div
                                key="favorites"
                                initial={{ opacity: 0, x: -10 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: 10 }}
                                className="space-y-4"
                            >
                                <div className="flex justify-between items-center mb-4 flex-wrap gap-4">
                                    <h3 className="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2">
                                        ⭐ Questões Favoritas
                                    </h3>
                                    <div className="flex items-center gap-4">
                                        <div className="flex bg-gray-100 dark:bg-slate-800 p-1 rounded-lg">
                                            <button
                                                onClick={() => setFavViewMode('card')}
                                                className={`px-3 py-1 text-xs font-bold rounded-md transition-all ${favViewMode === 'card' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:text-slate-400 dark:hover:text-slate-200'}`}
                                            >
                                                Cards
                                            </button>
                                            <button
                                                onClick={() => setFavViewMode('list')}
                                                className={`px-3 py-1 text-xs font-bold rounded-md transition-all ${favViewMode === 'list' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:text-slate-400 dark:hover:text-slate-200'}`}
                                            >
                                                Completo
                                            </button>
                                        </div>
                                        <Link to="/questions" className="text-xs font-bold text-blue-600 hover:underline uppercase tracking-wider">Resolver Favoritas</Link>
                                    </div>
                                </div>

                                {favLoading ? (
                                    <div className="p-20 text-center text-gray-400 animate-pulse font-bold">Carregando favoritas...</div>
                                ) : favorites.length === 0 ? (
                                    <div className="p-20 text-center bg-white dark:bg-slate-900 rounded-2xl border border-dashed border-gray-200 dark:border-slate-800">
                                        <span className="text-4xl block mb-4">⭐</span>
                                        <p className="text-gray-500 font-bold">Você ainda não tem questões favoritas.</p>
                                    </div>
                                ) : (
                                    <>
                                        {favViewMode === 'card' ? (
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                {favorites.map(q => (
                                                    <div key={q.id} className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-gray-100 dark:border-slate-700 shadow-sm flex flex-col justify-between hover:shadow-md transition-all">
                                                        <div>
                                                            <div className="flex justify-between items-start mb-2">
                                                                <span className="text-[10px] uppercase font-black text-indigo-400 tracking-tighter">#{q.id} • {q.subjects?.[0]?.name || 'Geral'}</span>
                                                                <button onClick={() => handleRemoveFavorite(q.id)} className="text-amber-500 hover:text-gray-400 text-sm" title="Remover dos Favoritos">★</button>
                                                            </div>
                                                            <div dangerouslySetInnerHTML={{ __html: q.statement }} className="text-xs text-gray-700 dark:text-slate-300 line-clamp-3 mb-4 font-medium" />
                                                        </div>
                                                        <div className="flex justify-end gap-2">
                                                            <Link to={`/questions?id=${q.id}`} className="px-3 py-1.5 bg-gray-50 dark:bg-slate-700 text-gray-600 dark:text-gray-200 text-[10px] font-bold rounded-lg hover:bg-indigo-50 hover:text-indigo-600 transition-colors uppercase tracking-wider">Visualizar</Link>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <div className="space-y-4">
                                                {favorites.map(q => (
                                                    <QuestionCard key={q.id} question={q} />
                                                ))}
                                            </div>
                                        )}
                                        <div className="flex justify-center gap-4 mt-8 pb-8">
                                            <button onClick={() => setFavPage(p => Math.max(1, p - 1))} disabled={favPage === 1} className="px-4 py-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-xs font-bold disabled:opacity-50">Anterior</button>
                                            <button onClick={() => setFavPage(p => p + 1)} disabled={!favHasNext} className="px-4 py-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-xs font-bold disabled:opacity-50">Próxima</button>
                                        </div>
                                    </>
                                )}
                            </motion.div>
                        )}

                        {activeTab === 'notebooks' && (
                            <motion.div
                                key="notebooks"
                                initial={{ opacity: 0, x: -10 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: 10 }}
                                className="space-y-6"
                            >
                                <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-700 p-4">
                                    <form onSubmit={handleCreateNotebook} className="flex gap-2">
                                        <input
                                            type="text"
                                            value={newNbName}
                                            onChange={(e) => setNewNbName(e.target.value)}
                                            placeholder="Nome do novo caderno..."
                                            className="flex-1 bg-gray-50 dark:bg-slate-900 border-none rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 transition-all outline-none"
                                        />
                                        <button
                                            type="submit"
                                            disabled={creatingNb || !newNbName.trim()}
                                            className="px-6 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 transition shadow-sm disabled:opacity-50"
                                        >
                                            {creatingNb ? 'Wait...' : '+ Criar'}
                                        </button>
                                    </form>
                                </div>

                                {nbLoading ? (
                                    <div className="p-20 text-center text-gray-400 animate-pulse font-bold">Carregando cadernos...</div>
                                ) : notebooks.length === 0 ? (
                                    <div className="p-20 text-center bg-white dark:bg-slate-900 rounded-2xl border border-dashed border-gray-200 dark:border-slate-800">
                                        <span className="text-4xl block mb-4">📁</span>
                                        <p className="text-gray-500 font-bold">Você ainda não tem cadernos.</p>
                                    </div>
                                ) : (
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 pb-8">
                                        {notebooks.map(nb => (
                                            <div key={nb.id} className="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm flex flex-col justify-between hover:border-indigo-200 transition-all relative group">
                                                <button onClick={() => handleDeleteNotebook(nb.id)} className="absolute top-3 right-3 text-gray-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-all">✕</button>
                                                <div>
                                                    <h4 className="font-bold text-gray-800 dark:text-white mb-1 truncate pr-6">{nb.name}</h4>
                                                    <span className="text-[10px] px-2 py-0.5 bg-indigo-50 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 rounded-full font-bold uppercase">{nb.questions_count} Questões</span>
                                                </div>
                                                <div className="mt-6 flex flex-col gap-2">
                                                    <Link to={`/questions?notebook_id=${nb.id}`} className="w-full text-center py-2 bg-indigo-600 dark:bg-indigo-600/90 text-white text-[11px] font-bold rounded-xl hover:bg-indigo-700 uppercase tracking-wider transition-all">Estudar Agora</Link>
                                                    <button className="w-full text-center py-2 bg-gray-50 dark:bg-slate-700 text-gray-500 dark:text-gray-300 text-[11px] font-bold rounded-xl hover:bg-gray-100 uppercase tracking-wider transition-all" onClick={() => toast.info('Funcionalidade em breve')}>Renomear</button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </motion.div>
                        )}
                    </AnimatePresence>
                </div>
            </div>
        </div>
    );
}
