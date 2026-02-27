import { toast } from 'sonner';
import { useState, useEffect } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { useParams, useNavigate, Link } from 'react-router-dom';
import api from '../../api/axios';
import { AdminPageSkeleton } from './components/AdminSkeletons';

export default function PromptEditor() {
    const { id } = useParams();
    const navigate = useNavigate();

    const [formState, setFormState] = useState({
        title: '',
        description: '',
        content: ''
    });

    const [validationErrors, setValidationErrors] = useState<any>({});

    const { data: promptData, isLoading } = useQuery({
        queryKey: ['admin-prompt', id],
        queryFn: async () => {
            const res = await api.get(`/api/v1/admin/prompts/${id}`);
            return res.data;
        },
        enabled: !!id
    });

    useEffect(() => {
        if (promptData) {
            setFormState({
                title: promptData.title || '',
                description: promptData.description || '',
                content: promptData.content || ''
            });
        }
    }, [promptData]);

    const saveMutation = useMutation({
        mutationFn: async (payload: any) => {
            return await api.put(`/api/v1/admin/prompts/${id}`, payload);
        },
        onSuccess: () => {
            navigate('/admin/prompts');
        },
        onError: (error: any) => {
            if (error.response?.data?.errors) {
                setValidationErrors(error.response.data.errors);
            } else {
                toast.error('Erro ao salvar o prompt.');
            }
        }
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setValidationErrors({});
        saveMutation.mutate(formState);
    };

    if (isLoading) return <AdminPageSkeleton />;
    if (!promptData) return <div className="p-8 text-center text-red-500 font-bold">Prompt não encontrado.</div>;

    const getError = (field: string) => validationErrors[field] ? validationErrors[field][0] : null;
    const variables = Array.isArray(promptData.variables) ? promptData.variables : [];

    return (
        <div className="max-w-4xl mx-auto space-y-6 py-12 px-4 sm:px-6 lg:px-8">
            <div className="flex items-center gap-4">
                <Link to="/admin/prompts" className="p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition-colors">
                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7 7-7" />
                    </svg>
                </Link>
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100">Editar Prompt</h1>
                    <p className="text-sm text-slate-500 dark:text-slate-400">Slug: <span className="font-mono text-indigo-600 dark:text-indigo-400">{promptData.slug}</span></p>
                </div>
            </div>

            {variables.length > 0 && (
                <div className="bg-blue-50 dark:bg-blue-950/30 border-l-4 border-blue-500 p-4 rounded-r">
                    <div className="flex">
                        <div className="flex-shrink-0">
                            <svg className="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                            </svg>
                        </div>
                        <div className="ml-3">
                            <p className="text-sm text-blue-700 dark:text-blue-300">
                                <strong>Variáveis disponíveis: </strong>
                                {variables.map((v: string) => `{${v}}`).join(', ')}
                            </p>
                        </div>
                    </div>
                </div>
            )}

            <div className="bg-white dark:bg-slate-900 shadow-sm rounded-xl border border-slate-200 dark:border-slate-700">
                <form onSubmit={handleSubmit} className="p-6 space-y-6">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="space-y-2">
                            <label htmlFor="title" className="text-sm font-medium text-slate-700 dark:text-slate-300">Título</label>
                            <input
                                type="text"
                                id="title"
                                value={formState.title}
                                onChange={(e) => setFormState({ ...formState, title: e.target.value })}
                                className={`w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm ${getError('title') ? 'border-red-300' : ''}`}
                            />
                            {getError('title') && <p className="text-xs text-red-500 mt-1">{getError('title')}</p>}
                        </div>

                        <div className="space-y-2">
                            <label htmlFor="description" className="text-sm font-medium text-slate-700 dark:text-slate-300">Descrição</label>
                            <input
                                type="text"
                                id="description"
                                value={formState.description}
                                onChange={(e) => setFormState({ ...formState, description: e.target.value })}
                                className={`w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm ${getError('description') ? 'border-red-300' : ''}`}
                            />
                            {getError('description') && <p className="text-xs text-red-500 mt-1">{getError('description')}</p>}
                        </div>
                    </div>

                    <div className="space-y-2">
                        <label htmlFor="content" className="text-sm font-medium text-slate-700 dark:text-slate-300">Conteúdo do Prompt</label>
                        <textarea
                            id="content"
                            rows={15}
                            value={formState.content}
                            onChange={(e) => setFormState({ ...formState, content: e.target.value })}
                            className={`w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white font-mono text-xs focus:ring-indigo-500 focus:border-indigo-500 ${getError('content') ? 'border-red-300' : ''}`}
                        ></textarea>
                        {getError('content') && <p className="text-xs text-red-500 mt-1">{getError('content')}</p>}
                    </div>

                    <div className="flex items-center justify-end gap-3 pt-6 border-t border-slate-100 dark:border-slate-700">
                        <Link to="/admin/prompts" className="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-colors">Cancelar</Link>
                        <button
                            type="submit"
                            disabled={saveMutation.isPending}
                            className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors disabled:opacity-50">
                            {saveMutation.isPending ? 'Salvando...' : 'Salvar Alterações'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
