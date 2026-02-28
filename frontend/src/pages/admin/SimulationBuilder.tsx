import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../api/axios';
import { toast } from 'sonner';
import { motion, AnimatePresence } from 'framer-motion';

interface SimulationRule {
    category: string;
    configuration: any;
}

interface SimulationPreset {
    id: number;
    name: string;
    description: string | null;
    type: 'enem' | 'banca';
    is_active: boolean;
    rules: SimulationRule[];
}

export default function SimulationBuilder() {
    const queryClient = useQueryClient();
    const [selectedPreset, setSelectedPreset] = useState<SimulationPreset | null>(null);
    const [isEditing, setIsEditing] = useState(false);
    const [showForm, setShowForm] = useState(false);

    // Form state
    const [formData, setFormData] = useState({
        name: '',
        description: '',
        type: 'enem' as 'enem' | 'banca',
        is_active: true,
        rules: [
            { category: 'subject_distribution', configuration: { ai_ratio: 0.1, human_ratio: 0.9 } },
            { category: 'general_config', configuration: { allow_repeated: false, difficulty_curve: 'linear' } }
        ]
    });

    const { data: presets, isLoading } = useQuery<SimulationPreset[]>({
        queryKey: ['admin-presets'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/simulations/presets');
            return res.data;
        }
    });

    const createMutation = useMutation({
        mutationFn: async (payload: any) => api.post('/api/v1/admin/simulations/presets', payload),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-presets'] });
            setShowForm(false);
            toast.success('Preset criado com sucesso!');
        },
        onError: () => toast.error('Erro ao criar preset.')
    });

    const updateMutation = useMutation({
        mutationFn: async (payload: any) => api.put(`/api/v1/admin/simulations/presets/${selectedPreset?.id}`, payload),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-presets'] });
            setIsEditing(false);
            setSelectedPreset(null);
            toast.success('Preset atualizado!');
        },
        onError: () => toast.error('Erro ao atualizar preset.')
    });

    const deleteMutation = useMutation({
        mutationFn: async (id: number) => api.delete(`/api/v1/admin/simulations/presets/${id}`),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-presets'] });
            toast.success('Preset removido.');
        }
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEditing) {
            updateMutation.mutate(formData);
        } else {
            createMutation.mutate(formData);
        }
    };

    const startEditing = (preset: SimulationPreset) => {
        setIsEditing(true);
        setSelectedPreset(preset);
        setFormData({
            name: preset.name,
            description: preset.description || '',
            type: preset.type,
            is_active: preset.is_active,
            rules: preset.rules.length > 0 ? preset.rules : formData.rules
        });
        setShowForm(true);
    };

    if (isLoading) return <div className="p-8 text-center text-slate-500 font-bold animate-pulse">Carregando Motor de Simulados...</div>;

    return (
        <div className="p-4 md:p-8 max-w-7xl mx-auto space-y-8 bg-slate-50/30 min-h-screen">
            <header className="flex flex-col md:flex-row justify-between items-start gap-4">
                <div>
                    <h1 className="text-3xl font-black text-slate-800 tracking-tight">Simulados: Motor de Presets ⚙️</h1>
                    <p className="text-slate-500 font-medium">Configure as regras de geração, proporção IA/Humana e métricas de dificuldade.</p>
                </div>
                <button
                    onClick={() => { setShowForm(true); setIsEditing(false); setFormData({ ...formData, name: '' }); }}
                    className="px-6 py-3 bg-indigo-600 text-white font-bold rounded-2xl shadow-xl shadow-indigo-100 hover:scale-[1.02] active:scale-[0.98] transition-all">
                    + Novo Preset
                </button>
            </header>

            <AnimatePresence>
                {showForm && (
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -20 }}
                        className="bg-white rounded-3xl shadow-xl border border-slate-100 p-8"
                    >
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div className="space-y-4">
                                    <div>
                                        <label className="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">Nome do Preset</label>
                                        <input
                                            type="text"
                                            value={formData.name}
                                            onChange={e => setFormData({ ...formData, name: e.target.value })}
                                            className="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 font-medium"
                                            placeholder="Ex: ENEM Final 2026"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">Tipo</label>
                                        <select
                                            value={formData.type}
                                            onChange={e => setFormData({ ...formData, type: e.target.value as any })}
                                            className="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 font-medium"
                                        >
                                            <option value="enem">ENEM</option>
                                            <option value="banca">Concurso (Banca)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">Descrição</label>
                                        <textarea
                                            value={formData.description}
                                            onChange={e => setFormData({ ...formData, description: e.target.value })}
                                            className="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 font-medium min-h-[100px]"
                                            placeholder="Descreve o objetivo deste preset..."
                                        />
                                    </div>
                                </div>

                                <div className="space-y-4">
                                    <h4 className="text-sm font-bold text-slate-800 flex items-center gap-2">
                                        <span className="p-1.5 bg-yellow-50 text-yellow-600 rounded text-xs">🚀</span>
                                        Regras de Inteligência Artificial
                                    </h4>
                                    <div className="p-6 bg-slate-50 rounded-3xl border border-slate-200 space-y-6">
                                        <div>
                                            <div className="flex justify-between mb-2">
                                                <label className="text-xs font-bold text-slate-500">Proporção IA: {Math.round(formData.rules[0].configuration.ai_ratio * 100)}%</label>
                                            </div>
                                            <input
                                                type="range" min="0" max="1" step="0.05"
                                                value={formData.rules[0].configuration.ai_ratio}
                                                onChange={e => {
                                                    const val = parseFloat(e.target.value);
                                                    const newRules = [...formData.rules];
                                                    newRules[0].configuration = { ai_ratio: val, human_ratio: 1 - val };
                                                    setFormData({ ...formData, rules: newRules });
                                                }}
                                                className="w-full h-2 bg-indigo-100 rounded-lg appearance-none cursor-pointer accent-indigo-600"
                                            />
                                        </div>
                                        <div className="space-y-3">
                                            <label className="flex items-center gap-3 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    checked={formData.rules[1].configuration.allow_repeated}
                                                    onChange={e => {
                                                        const newRules = [...formData.rules];
                                                        newRules[1].configuration.allow_repeated = e.target.checked;
                                                        setFormData({ ...formData, rules: newRules });
                                                    }}
                                                    className="w-5 h-5 rounded border-slate-200 text-indigo-600"
                                                />
                                                <span className="text-sm font-medium text-slate-600">Permitir questões já respondidas</span>
                                            </label>
                                            <div>
                                                <label className="block text-[10px] font-black text-slate-400 uppercase mb-1">Curva de Dificuldade</label>
                                                <select
                                                    value={formData.rules[1].configuration.difficulty_curve}
                                                    onChange={e => {
                                                        const newRules = [...formData.rules];
                                                        newRules[1].configuration.difficulty_curve = e.target.value;
                                                        setFormData({ ...formData, rules: newRules });
                                                    }}
                                                    className="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs"
                                                >
                                                    <option value="linear">Linear (Aleatório)</option>
                                                    <option value="bell_curve">Distribuição Normal (Gauss)</option>
                                                    <option value="progressive">Progressiva (Fácil → Difícil)</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="flex justify-end gap-4 border-t border-slate-100 pt-6">
                                <button
                                    type="button"
                                    onClick={() => setShowForm(false)}
                                    className="px-6 py-3 text-slate-500 font-bold hover:bg-slate-100 rounded-2xl transition-all">
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={createMutation.isPending || updateMutation.isPending}
                                    className="px-8 py-3 bg-indigo-600 text-white font-bold rounded-2xl shadow-xl shadow-indigo-100 flex items-center gap-2">
                                    {(createMutation.isPending || updateMutation.isPending) && <div className="w-4 h-4 border-2 border-white/30 border-t-white animate-spin rounded-full"></div>}
                                    {isEditing ? 'Salvar Alterações' : 'Criar Motor'}
                                </button>
                            </div>
                        </form>
                    </motion.div>
                )}
            </AnimatePresence>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {presets?.map(preset => (
                    <motion.div
                        key={preset.id}
                        layout
                        className={`bg-white rounded-3xl border border-slate-100 p-6 shadow-sm hover:shadow-md transition-all flex flex-col justify-between ${!preset.is_active ? 'opacity-60 grayscale' : ''}`}
                    >
                        <div>
                            <div className="flex justify-between items-start mb-4">
                                <span className={`px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest ${preset.type === 'enem' ? 'bg-amber-100 text-amber-600' : 'bg-blue-100 text-blue-600'}`}>
                                    {preset.type === 'enem' ? 'ENEM' : 'CONCURSO'}
                                </span>
                                <div className="flex gap-2">
                                    <button
                                        onClick={() => startEditing(preset)}
                                        className="p-2 hover:bg-slate-50 text-slate-400 hover:text-indigo-600 rounded-lg">
                                        ✏️
                                    </button>
                                    <button
                                        onClick={() => window.confirm('Remover este motor?') && deleteMutation.mutate(preset.id)}
                                        className="p-2 hover:bg-red-50 text-slate-400 hover:text-red-600 rounded-lg">
                                        ✕
                                    </button>
                                </div>
                            </div>
                            <h3 className="text-xl font-bold text-slate-800 mb-2">{preset.name}</h3>
                            <p className="text-sm text-slate-500 mb-6">{preset.description || 'Sem descrição.'}</p>

                            <div className="grid grid-cols-2 gap-4 mb-6">
                                <div className="p-3 bg-slate-50 rounded-2xl">
                                    <p className="text-[10px] font-black text-slate-400 uppercase mb-1">IA Ratio</p>
                                    <p className="text-lg font-bold text-indigo-600">
                                        {Math.round((preset.rules.find(r => r.category === 'subject_distribution')?.configuration?.ai_ratio ?? 0) * 100)}%
                                    </p>
                                </div>
                                <div className="p-3 bg-slate-50 rounded-2xl">
                                    <p className="text-[10px] font-black text-slate-400 uppercase mb-1">Dificuldade</p>
                                    <p className="text-xs font-bold text-slate-700 capitalize">
                                        {preset.rules.find(r => r.category === 'general_config')?.configuration?.difficulty_curve ?? 'N/A'}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center justify-between pt-4 border-t border-slate-50">
                            <div className="flex items-center gap-2">
                                <span className={`w-2 h-2 rounded-full ${preset.is_active ? 'bg-emerald-500' : 'bg-slate-400'}`}></span>
                                <span className="text-[10px] font-bold text-slate-500 uppercase">{preset.is_active ? 'Ativo' : 'Pausado'}</span>
                            </div>
                            <div className="text-[10px] font-mono text-slate-300">ID: #{preset.id}</div>
                        </div>
                    </motion.div>
                ))}

                {presets?.length === 0 && !showForm && (
                    <div className="col-span-full py-20 text-center space-y-4">
                        <div className="text-5xl">🧊</div>
                        <h2 className="text-xl font-bold text-slate-400">Nenhum Motor de Simulado configurado.</h2>
                        <p className="text-slate-400 max-w-md mx-auto">Crie seu primeiro preset para definir como a IA deve balancear questões humanas e geradas.</p>
                    </div>
                )}
            </div>
        </div>
    );
}
