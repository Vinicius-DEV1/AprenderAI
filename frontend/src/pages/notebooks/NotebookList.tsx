import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../../api/axios';
import { toast } from 'sonner';

interface Notebook {
    id: number;
    name: string;
    questions_count: number;
    created_at: string;
}

export default function NotebookList() {
    const [notebooks, setNotebooks] = useState<Notebook[]>([]);
    const [loading, setLoading] = useState(true);
    const [newName, setNewName] = useState('');
    const [creating, setCreating] = useState(false);
    const navigate = useNavigate();

    useEffect(() => {
        loadNotebooks();
    }, []);

    const loadNotebooks = async () => {
        setLoading(true);
        try {
            const res = await api.get('/api/v1/notebooks');
            setNotebooks(res.data);
        } catch (e) {
            toast.error('Erro ao carregar cadernos');
        } finally {
            setLoading(false);
        }
    };

    const handleCreate = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!newName.trim()) return;
        setCreating(true);
        try {
            const res = await api.post('/api/v1/notebooks', { name: newName });
            setNotebooks([res.data.notebook, ...notebooks]);
            setNewName('');
            toast.success('Caderno criado com sucesso!');
        } catch (e: any) {
            if (e.response?.data?.error_code === 'limit_reached') {
                toast.error(e.response.data.message);
            } else {
                toast.error('Erro ao criar caderno');
            }
        } finally {
            setCreating(false);
        }
    };

    const handleDelete = async (id: number) => {
        if (!confirm('Tem certeza que deseja excluir este caderno? Questões salvas nele não serão excluídas da plataforma.')) return;
        try {
            await api.delete(`/api/v1/notebooks/${id}`);
            setNotebooks(notebooks.filter(n => n.id !== id));
            toast.success('Caderno excluído!');
        } catch (e) {
            toast.error('Erro ao excluir caderno');
        }
    };

    return (
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 animate-fade-in">
            <div className="flex justify-between items-center mb-8">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        📁 Meus Cadernos de Questões
                    </h1>
                    <p className="text-gray-500 dark:text-gray-400 mt-1 text-sm">
                        Crie cadernos para organizar suas questões favoritas e revisá-las quando quiser.
                    </p>
                </div>
                <button
                    onClick={() => navigate('/questoes')}
                    className="hidden sm:flex items-center gap-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 px-4 py-2 rounded-lg text-sm font-semibold text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-50 dark:hover:bg-slate-700 transition"
                >
                    🔍 Buscar Questões
                </button>
            </div>

            <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-700 p-6 mb-8">
                <form onSubmit={handleCreate} className="flex gap-4 items-end flex-wrap sm:flex-nowrap">
                    <div className="flex-1 w-full min-w-[200px]">
                        <label className="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">
                            Novo Caderno
                        </label>
                        <input
                            type="text"
                            value={newName}
                            onChange={(e) => setNewName(e.target.value)}
                            placeholder="Criar caderno (ex: Caderno de Erros Biologia)"
                            className="w-full rounded-xl border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2.5 shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition"
                        />
                    </div>
                    <button
                        type="submit"
                        disabled={creating || !newName.trim()}
                        className="bg-indigo-600 text-white w-full sm:w-auto px-6 py-2.5 rounded-xl hover:bg-indigo-700 font-semibold shadow-sm transition-colors disabled:opacity-50 h-[46px] flex items-center justify-center cursor-pointer"
                    >
                        {creating ? 'Criando...' : '+ Criar'}
                    </button>
                </form>
            </div>

            {loading ? (
                <div className="text-center py-20 text-gray-500 animate-pulse font-medium">Carregando cadernos...</div>
            ) : notebooks.length === 0 ? (
                <div className="text-center py-24 bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 border-dashed">
                    <div className="text-5xl mb-4">📚</div>
                    <h3 className="text-xl font-bold text-gray-900 dark:text-white mb-2">Nenhum caderno criado</h3>
                    <p className="text-gray-500 dark:text-gray-400 max-w-sm mx-auto mb-6 text-sm">
                        Você ainda não criou nenhum caderno. Use o formulário acima e depois salve questões através da Triagem Inteligente.
                    </p>
                    <button
                        onClick={() => navigate('/questoes')}
                        className="text-indigo-600 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-400 dark:hover:bg-indigo-900/50 px-6 py-3 rounded-lg font-bold transition"
                    >
                        Ir para Triagem de Questões
                    </button>
                </div>
            ) : (
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    {notebooks.map(nb => (
                        <div key={nb.id} className="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 p-6 shadow-sm hover:shadow-md transition-all relative group flex flex-col justify-between min-h-[160px]">
                            <button
                                onClick={() => handleDelete(nb.id)}
                                className="absolute top-4 right-4 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 p-1.5 rounded-md lg:opacity-0 group-hover:opacity-100 transition-all"
                                title="Excluir Caderno"
                            >
                                🗑️
                            </button>

                            <div>
                                <h3 className="text-lg font-bold text-gray-900 dark:text-white pr-8 mb-2 leading-tight" title={nb.name}>
                                    {nb.name}
                                </h3>
                                <div className="flex flex-wrap items-center gap-2 mb-4">
                                    <span className="text-xs text-indigo-700 dark:text-indigo-300 bg-indigo-100 dark:bg-indigo-900/40 px-2 py-1 rounded-md font-bold tracking-wide">
                                        {nb.questions_count} {nb.questions_count === 1 ? 'questão' : 'questões'}
                                    </span>
                                    <span className="text-[11px] text-gray-500 dark:text-gray-400 uppercase font-medium">
                                        Criado em {new Date(nb.created_at).toLocaleDateString()}
                                    </span>
                                </div>
                            </div>

                            <button
                                onClick={() => navigate(`/questoes?notebook_id=${nb.id}`)}
                                className="w-full text-center border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-indigo-50 hover:text-indigo-700 hover:border-indigo-200 dark:hover:bg-slate-700 transition"
                            >
                                Estudar Questões
                            </button>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
