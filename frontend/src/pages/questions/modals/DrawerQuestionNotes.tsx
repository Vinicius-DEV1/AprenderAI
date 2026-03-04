import { useState, useEffect } from 'react';
import { toast } from 'sonner';
import api from '../../../api/axios';

interface Note {
    id: number;
    content: string;
    updated_at: string;
}

interface Props {
    isOpen: boolean;
    onClose: () => void;
    questionId: number;
    onNoteSaved?: () => void;
}

export default function DrawerQuestionNotes({ isOpen, onClose, questionId, onNoteSaved }: Props) {
    const [notes, setNotes] = useState<Note[]>([]);
    const [newNote, setNewNote] = useState('');
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (isOpen) {
            loadNotes();
        }
    }, [isOpen, questionId]);

    const loadNotes = async () => {
        setLoading(true);
        try {
            const res = await api.get(`/api/v1/questions/${questionId}/notes`);
            setNotes(res.data);
        } catch (error) {
            toast.error('Erro ao carregar anotações.');
        } finally {
            setLoading(false);
        }
    };

    const handleSave = async () => {
        if (!newNote.trim()) return;
        setSaving(true);
        try {
            const res = await api.post(`/api/v1/questions/${questionId}/notes`, { content: newNote });
            setNotes([res.data.note, ...notes]);
            setNewNote('');
            toast.success('Anotação salva!');
            if (onNoteSaved) onNoteSaved();
        } catch (error) {
            toast.error('Erro ao salvar anotação.');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (id: number) => {
        try {
            await api.delete(`/api/v1/notes/${id}`);
            setNotes(notes.filter(n => n.id !== id));
            toast.success('Anotação excluída.');
        } catch (error) {
            toast.error('Erro ao excluir anotação.');
        }
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex justify-end">
            <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" onClick={onClose}></div>
            <div className="relative w-full max-w-md h-full bg-white dark:bg-slate-800 shadow-2xl flex flex-col transform transition-transform animate-slide-in-right">
                <div className="p-4 border-b border-gray-200 dark:border-slate-700 flex justify-between items-center bg-white dark:bg-slate-800">
                    <h3 className="text-lg font-bold text-gray-900 dark:text-slate-100 flex items-center gap-2">
                        📝 Suas Anotações
                    </h3>
                    <button onClick={onClose} className="p-2 text-gray-500 hover:bg-gray-100 rounded-full dark:hover:bg-slate-700 transition">
                        ✕
                    </button>
                </div>

                <div className="flex-1 overflow-y-auto p-4 flex flex-col gap-4 bg-gray-50 dark:bg-slate-900">
                    <div className="bg-white dark:bg-slate-800 p-3 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm transition-all focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-transparent">
                        <textarea
                            className="w-full bg-transparent resize-none outline-none text-sm dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500"
                            rows={4}
                            placeholder="Escreva sua anotação aqui (suporta Markdown)..."
                            value={newNote}
                            onChange={(e) => setNewNote(e.target.value)}
                            disabled={saving}
                        />
                        <div className="flex justify-end mt-2">
                            <button
                                onClick={handleSave}
                                disabled={saving || !newNote.trim()}
                                className="px-4 py-1.5 bg-indigo-600 text-white rounded-md text-xs font-semibold hover:bg-indigo-700 disabled:opacity-50 transition-colors cursor-pointer"
                            >
                                {saving ? 'Salvando...' : 'Salvar'}
                            </button>
                        </div>
                    </div>

                    {loading ? (
                        <p className="text-center text-sm text-gray-500 mt-4 animate-pulse">Carregando...</p>
                    ) : notes.length === 0 ? (
                        <div className="text-center mt-10">
                            <span className="text-4xl">💭</span>
                            <p className="text-sm text-gray-500 mt-2">Nenhuma anotação nesta questão.</p>
                        </div>
                    ) : (
                        notes.map(note => (
                            <div key={note.id} className="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-xl border border-yellow-200 dark:border-yellow-700/50 shadow-sm relative group">
                                <button
                                    onClick={() => handleDelete(note.id)}
                                    className="absolute top-2 right-2 text-red-500 opacity-0 group-hover:opacity-100 hover:bg-red-100 dark:hover:bg-red-900/30 p-1.5 rounded-md transition"
                                    title="Excluir"
                                >
                                    🗑️
                                </button>
                                <div className="text-sm text-gray-800 dark:text-slate-300 pr-6 break-words whitespace-pre-wrap leading-relaxed">
                                    {note.content}
                                </div>
                                <div className="text-[10px] text-gray-400 mt-2 text-right font-medium">
                                    {new Date(note.updated_at).toLocaleDateString('pt-BR')}
                                </div>
                            </div>
                        ))
                    )}
                </div>
            </div>
        </div>
    );
}
