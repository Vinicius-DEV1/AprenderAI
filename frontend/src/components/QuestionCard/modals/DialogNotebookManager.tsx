import { useState, useEffect } from 'react';
import { toast } from 'sonner';
import api from '../../../api/axios';

interface Notebook {
    id: number;
    name: string;
    questions_count?: number;
}

interface Props {
    isOpen: boolean;
    onClose: () => void;
    questionId: number;
    initialNotebookIds?: number[];
    onSaved?: (notebookIds: number[]) => void;
}

export default function DialogNotebookManager({ isOpen, onClose, questionId, initialNotebookIds = [], onSaved }: Props) {
    const [notebooks, setNotebooks] = useState<Notebook[]>([]);
    const [selectedIds, setSelectedIds] = useState<number[]>(initialNotebookIds);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);

    // Create new notebook inline
    const [newName, setNewName] = useState('');
    const [creating, setCreating] = useState(false);

    useEffect(() => {
        if (isOpen) {
            // Ensure IDs are numbers to match nb.id type
            const numericIds = (initialNotebookIds || []).map(id => Number(id));
            setSelectedIds(numericIds);
            loadNotebooks();
        }
    }, [isOpen, initialNotebookIds]);

    const loadNotebooks = async () => {
        setLoading(true);
        try {
            const res = await api.get('/api/v1/notebooks');
            setNotebooks(res.data);
        } catch (e) {
            toast.error('Erro ao carregar cadernos.');
        } finally {
            setLoading(false);
        }
    };

    const handleToggle = (id: number) => {
        setSelectedIds(prev =>
            prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]
        );
    };

    const handleSave = async () => {
        setSaving(true);
        try {
            await api.post(`/api/v1/questions/${questionId}/sync-notebooks`, {
                notebook_ids: selectedIds
            });
            toast.success('Cadernos atualizados!');
            if (onSaved) onSaved(selectedIds);
            onClose();
        } catch (e) {
            toast.error('Erro ao salvar em cadernos.');
        } finally {
            setSaving(false);
        }
    };

    const handleCreateNotebook = async () => {
        if (!newName.trim()) return;
        setCreating(true);
        try {
            const res = await api.post('/api/v1/notebooks', { name: newName });
            setNotebooks([res.data.notebook, ...notebooks]);
            setSelectedIds([...selectedIds, res.data.notebook.id]);
            setNewName('');
            toast.success('Caderno criado e questão adicionada!');
        } catch (e: any) {
            if (e.response?.data?.error_code === 'limit_reached') {
                toast.error(e.response.data.message);
            } else {
                toast.error('Erro ao criar caderno.');
            }
        } finally {
            setCreating(false);
        }
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-0">
            <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onClick={onClose}></div>
            <div className="relative w-full max-w-sm rounded-2xl bg-white dark:bg-slate-800 p-5 shadow-xl animate-fade-in-up">
                <h3 className="mb-4 text-lg font-bold text-gray-900 dark:text-slate-100 flex items-center gap-2">
                    📁 Salvar em Cadernos
                </h3>

                <div className="mb-4 flex gap-2">
                    <input
                        type="text"
                        placeholder="Nome do novo caderno..."
                        value={newName}
                        onChange={e => setNewName(e.target.value)}
                        className="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-1 focus:ring-indigo-500 outline-none dark:bg-slate-700 dark:border-slate-600 dark:text-white"
                        onKeyDown={e => e.key === 'Enter' && handleCreateNotebook()}
                    />
                    <button
                        onClick={handleCreateNotebook}
                        disabled={creating || !newName.trim()}
                        className="px-4 bg-indigo-100 text-indigo-700 rounded-md hover:bg-indigo-200 dark:bg-indigo-900/40 dark:text-indigo-300 font-semibold disabled:opacity-50 transition"
                    >
                        Criar
                    </button>
                </div>

                <div className="max-h-56 overflow-y-auto space-y-1.5 mb-5 pr-1" style={{ scrollbarWidth: 'thin' }}>
                    {loading ? (
                        <p className="text-center text-xs text-gray-500 py-4">Carregando...</p>
                    ) : notebooks.length === 0 ? (
                        <p className="text-center text-xs text-gray-500 py-4">Nenhum caderno criado.</p>
                    ) : (
                        notebooks.map(nb => (
                            <label key={nb.id} className="flex items-center gap-3 p-2.5 hover:bg-gray-50 dark:hover:bg-slate-700/50 rounded-lg cursor-pointer transition border border-transparent hover:border-gray-200 dark:hover:border-slate-600 select-none">
                                <input
                                    type="checkbox"
                                    checked={selectedIds.includes(nb.id)}
                                    onChange={() => handleToggle(nb.id)}
                                    className="w-4 h-4 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500 bg-white"
                                />
                                <span className="flex-1 text-sm text-gray-800 dark:text-slate-200 font-medium truncate">
                                    {nb.name}
                                </span>
                                {nb.questions_count !== undefined && (
                                    <span className="text-xs text-gray-400 bg-gray-100 dark:bg-slate-700 px-2 py-0.5 rounded-full">
                                        {nb.questions_count}
                                    </span>
                                )}
                            </label>
                        ))
                    )}
                </div>

                <div className="flex justify-end gap-2 pt-3 border-t border-gray-100 dark:border-slate-700">
                    <button onClick={onClose} disabled={saving} className="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg dark:text-gray-400 dark:hover:bg-slate-700 font-medium transition">
                        Cancelar
                    </button>
                    <button onClick={handleSave} disabled={saving} className="px-5 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-semibold shadow-sm transition">
                        {saving ? '...' : 'Confirmar'}
                    </button>
                </div>
            </div>
        </div>
    );
}
