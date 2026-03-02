import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../api/axios';
import { toast } from 'sonner';
import { motion, AnimatePresence } from 'framer-motion';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface Distribution {
    id?: number;
    disciplina: string;
    percentual: number;
    dificuldade?: string;
    ordem?: number;
}

interface EngineRule {
    id: number;
    total_questoes: number;
    tempo_minutos: number;
    percentual_ia: number;
    nao_repetir_ultimos_simulados: number;
    difficulty_mode: 'balanceado' | 'progressivo' | 'aleatorio';
    distributions: Distribution[];
}

interface SimulationModel {
    id: number;
    slug: string;
    nome: string;
    tipo: 'enem' | 'concurso';
    ativo: boolean;
    rule: EngineRule | null;
}

const DIFF_MODE_LABELS: Record<string, string> = {
    balanceado: 'Balanceado (Aleatório)',
    progressivo: 'Progressivo (Fácil → Difícil)',
    aleatorio: 'Totalmente Aleatório',
};

// ─────────────────────────────────────────────────────────────────────────────
// Default rule form values
// ─────────────────────────────────────────────────────────────────────────────

const defaultRuleForm = (existing?: EngineRule | null) => ({
    total_questoes: existing?.total_questoes ?? 90,
    tempo_minutos: existing?.tempo_minutos ?? 270,
    percentual_ia: existing?.percentual_ia ?? 10,
    nao_repetir_ultimos_simulados: existing?.nao_repetir_ultimos_simulados ?? 10,
    difficulty_mode: (existing?.difficulty_mode ?? 'balanceado') as 'balanceado' | 'progressivo' | 'aleatorio',
    distributions: existing?.distributions?.length
        ? existing.distributions.map(d => ({ disciplina: d.disciplina, percentual: d.percentual, dificuldade: d.dificuldade ?? '', ordem: d.ordem ?? 0 }))
        : [{ disciplina: '', percentual: 50, dificuldade: '', ordem: 0 }],
});

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export default function SimulationBuilder() {
    const queryClient = useQueryClient();
    const [editingModel, setEditingModel] = useState<SimulationModel | null>(null);
    const [ruleForm, setRuleForm] = useState(defaultRuleForm());

    // ── Data ─────────────────────────────────────────────────────────────────
    const { data, isLoading, isError, error } = useQuery<{ models: SimulationModel[] }>({
        queryKey: ['admin-simulation-models'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/simulation-models');
            return res.data;
        },
        retry: 1
    });

    const models: SimulationModel[] = data?.models ?? [];

    // ── Mutations ─────────────────────────────────────────────────────────────
    const updateRuleMutation = useMutation({
        mutationFn: async ({ modelId, payload }: { modelId: number; payload: any }) =>
            api.post(`/api/v1/admin/simulation-models/${modelId}/rule`, payload),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-simulation-models'] });
            setEditingModel(null);
            toast.success('Regras salvas com sucesso!');
        },
        onError: () => toast.error('Erro ao salvar regras.'),
    });

    const toggleMutation = useMutation({
        mutationFn: async (modelId: number) =>
            api.post(`/api/v1/admin/simulation-models/${modelId}/toggle`),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-simulation-models'] });
        },
        onError: () => toast.error('Erro ao alterar status.'),
    });

    // ── Handlers ──────────────────────────────────────────────────────────────
    const openEditor = (model: SimulationModel) => {
        setEditingModel(model);
        setRuleForm(defaultRuleForm(model.rule));
    };

    const handleDistChange = (i: number, field: keyof Distribution, value: any) => {
        const next = [...ruleForm.distributions];
        next[i] = { ...next[i], [field]: value };
        setRuleForm(prev => ({ ...prev, distributions: next }));
    };

    const addDiscipline = () =>
        setRuleForm(prev => ({
            ...prev,
            distributions: [...prev.distributions, { disciplina: '', percentual: 0, dificuldade: '', ordem: prev.distributions.length }],
        }));

    const removeDiscipline = (i: number) =>
        setRuleForm(prev => ({ ...prev, distributions: prev.distributions.filter((_, idx) => idx !== i) }));

    const totalPct = ruleForm.distributions.reduce((s, d) => s + Number(d.percentual), 0);
    const pctOk = Math.abs(totalPct - 100) < 0.1;

    const handleSave = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingModel) return;
        if (!pctOk && editingModel.tipo !== 'concurso') {
            toast.error(`A soma dos percentuais deve ser 100% (atual: ${totalPct.toFixed(0)}%)`);
            return;
        }
        updateRuleMutation.mutate({ modelId: editingModel.id, payload: ruleForm });
    };

    // ── Loading ───────────────────────────────────────────────────────────────
    if (isLoading) return (
        <div className="p-12 flex flex-col items-center justify-center min-h-[400px] text-slate-500 gap-4">
            <div className="w-10 h-10 border-4 border-indigo-100 border-t-indigo-600 rounded-full animate-spin"></div>
            <p className="font-bold tracking-tight animate-pulse">Carregando Motor de Simulados...</p>
        </div>
    );

    if (isError) return (
        <div className="p-12 flex flex-col items-center justify-center min-h-[400px] text-red-500 gap-4 bg-red-50 rounded-3xl m-8">
            <span className="text-4xl">🚫</span>
            <div className="text-center">
                <p className="font-black text-xl mb-2">Falha ao Carregar Motor</p>
                <p className="text-sm font-medium opacity-70">
                    {(error as any)?.response?.data?.message || (error as any)?.message || 'Erro desconhecido. Verifique se está logado como admin.'}
                </p>
            </div>
            <button
                onClick={() => queryClient.invalidateQueries({ queryKey: ['admin-simulation-models'] })}
                className="mt-4 px-6 py-2 bg-red-600 text-white font-bold rounded-xl hover:bg-red-700 transition-all"
            >
                Tentar Novamente
            </button>
        </div>
    );

    // ─────────────────────────────────────────────────────────────────────────
    // Render
    // ─────────────────────────────────────────────────────────────────────────
    return (
        <div className="p-4 md:p-6 w-full space-y-6 bg-slate-50/30 min-h-screen">
            {/* Header */}
            <header>
                <h1 className="text-3xl font-black text-slate-800 tracking-tight">Motor de Simulados ⚙️</h1>
                <p className="text-slate-500 font-medium mt-1">
                    Configure a lógica de geração de cada modelo — questões, tempo, % IA, distribuição por disciplina.
                </p>
                <p className="mt-2 text-xs text-indigo-600 font-bold bg-indigo-50 border border-indigo-100 rounded-lg px-3 py-2 inline-block">
                    💡 As alterações entram em vigor imediatamente no próximo simulado gerado.
                </p>
            </header>

            {/* Model Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {models.length === 0 ? (
                    <div className="col-span-2 p-12 flex flex-col items-center justify-center bg-white border-2 border-dashed border-slate-200 rounded-3xl text-slate-400 gap-4">
                        <span className="text-5xl">⚙️</span>
                        <div className="text-center">
                            <p className="font-black text-lg text-slate-600">Nenhum modelo carregado</p>
                            <p className="text-sm mt-1">Verifique se você está logado como admin e se o seeder foi executado.</p>
                            <code className="text-xs mt-2 inline-block bg-slate-100 px-3 py-1 rounded font-mono text-slate-500">
                                php artisan db:seed --class=SimulationModelSeeder
                            </code>
                        </div>
                        <button
                            onClick={() => queryClient.invalidateQueries({ queryKey: ['admin-simulation-models'] })}
                            className="px-6 py-2 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-all text-sm"
                        >
                            🔄 Recarregar
                        </button>
                    </div>
                ) : models.map(model => (
                    <motion.div
                        key={model.id}
                        layout
                        className={`bg-white rounded-3xl border border-slate-100 shadow-sm hover:shadow-md transition-all overflow-hidden ${!model.ativo ? 'opacity-60 grayscale' : ''}`}
                    >
                        {/* Card Header */}
                        <div className="p-5 border-b border-slate-50 flex items-start justify-between">
                            <div className="flex items-center gap-3">
                                <div className={`w-10 h-10 rounded-xl flex items-center justify-center text-lg ${model.tipo === 'enem' ? 'bg-amber-100' : 'bg-blue-100'}`}>
                                    {model.tipo === 'enem' ? '📋' : '🏛️'}
                                </div>
                                <div>
                                    <h3 className="font-black text-slate-800 leading-none">{model.nome}</h3>
                                    <code className="text-[10px] text-slate-400 font-mono">{model.slug}</code>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className={`px-2 py-1 rounded-full text-[9px] font-black uppercase ${model.tipo === 'enem' ? 'bg-amber-100 text-amber-600' : 'bg-blue-100 text-blue-600'}`}>
                                    {model.tipo}
                                </span>
                                <button
                                    onClick={() => toggleMutation.mutate(model.id)}
                                    title={model.ativo ? 'Desativar' : 'Ativar'}
                                    className={`w-8 h-8 rounded-lg flex items-center justify-center transition-all ${model.ativo ? 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100' : 'bg-slate-100 text-slate-400 hover:bg-slate-200'}`}
                                >
                                    {model.ativo ? '✓' : '○'}
                                </button>
                            </div>
                        </div>

                        {/* Rule Summary */}
                        {model.rule ? (
                            <div className="p-5 space-y-4">
                                <div className="grid grid-cols-4 gap-3">
                                    {[
                                        { label: 'Questões', value: model.rule.total_questoes, color: 'indigo' },
                                        { label: 'Tempo', value: `${model.rule.tempo_minutos}min`, color: 'violet' },
                                        { label: '% IA', value: `${model.rule.percentual_ia}%`, color: 'purple' },
                                        { label: 'Não rep.', value: `${model.rule.nao_repetir_ultimos_simulados} sim.`, color: 'slate' },
                                    ].map(stat => (
                                        <div key={stat.label} className="bg-slate-50 rounded-xl p-3 text-center">
                                            <p className="text-[9px] font-black text-slate-400 uppercase tracking-widest">{stat.label}</p>
                                            <p className="text-base font-black text-slate-700 mt-1">{stat.value}</p>
                                        </div>
                                    ))}
                                </div>

                                {/* Distributions */}
                                {model.rule.distributions.length > 0 && (
                                    <div>
                                        <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Distribuição de Disciplinas</p>
                                        <div className="flex flex-wrap gap-1.5">
                                            {model.rule.distributions.map((d, i) => (
                                                <span key={i} className="px-2 py-1 bg-indigo-50 text-indigo-700 text-[10px] font-bold rounded-lg border border-indigo-100">
                                                    {d.disciplina}: {d.percentual}%
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <div className="flex items-center justify-between text-[10px] text-slate-400">
                                    <span>Dificuldade: <b className="text-slate-600">{DIFF_MODE_LABELS[model.rule.difficulty_mode]}</b></span>
                                </div>
                            </div>
                        ) : (
                            <div className="p-5 text-center text-slate-400 text-sm py-8">
                                ⚠️ Nenhuma regra configurada ainda.
                            </div>
                        )}

                        {/* Edit button */}
                        <div className="px-5 pb-5">
                            <button
                                onClick={() => openEditor(model)}
                                className="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black rounded-xl transition-all shadow-sm shadow-indigo-200"
                            >
                                ✏️ Editar Regras
                            </button>
                        </div>
                    </motion.div>
                ))}
            </div>

            {/* Edit Drawer / Sheet */}
            <AnimatePresence>
                {editingModel && (
                    <>
                        {/* Backdrop */}
                        <motion.div
                            key="backdrop"
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            className="fixed inset-0 bg-black/30 backdrop-blur-sm z-40"
                            onClick={() => setEditingModel(null)}
                        />
                        {/* Panel */}
                        <motion.aside
                            key="panel"
                            initial={{ x: '100%' }}
                            animate={{ x: 0 }}
                            exit={{ x: '100%' }}
                            transition={{ type: 'spring', stiffness: 300, damping: 30 }}
                            className="fixed top-0 right-0 h-screen w-full max-w-xl bg-white z-50 shadow-2xl overflow-y-auto flex flex-col"
                        >
                            {/* Panel Header */}
                            <div className="p-6 border-b border-slate-100 flex items-center justify-between shrink-0">
                                <div>
                                    <h2 className="text-lg font-black text-slate-800">Editar: {editingModel.nome}</h2>
                                    <p className="text-xs text-slate-400 font-mono">{editingModel.slug}</p>
                                </div>
                                <button onClick={() => setEditingModel(null)} className="w-8 h-8 rounded-lg hover:bg-slate-100 flex items-center justify-center text-slate-400 font-bold">✕</button>
                            </div>

                            {/* Form */}
                            <form onSubmit={handleSave} className="flex-1 overflow-y-auto p-6 space-y-6">
                                {/* Basic Rules */}
                                <section className="space-y-4">
                                    <h3 className="text-xs font-black text-slate-400 uppercase tracking-widest">Configurações Gerais</h3>

                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-[10px] font-black text-slate-500 uppercase mb-1">Total de Questões</label>
                                            <input type="number" min={1} max={500}
                                                value={ruleForm.total_questoes}
                                                onChange={e => setRuleForm(p => ({ ...p, total_questoes: +e.target.value }))}
                                                className="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-[10px] font-black text-slate-500 uppercase mb-1">Tempo (minutos)</label>
                                            <input type="number" min={1}
                                                value={ruleForm.tempo_minutos}
                                                onChange={e => setRuleForm(p => ({ ...p, tempo_minutos: +e.target.value }))}
                                                className="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500"
                                            />
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-[10px] font-black text-slate-500 uppercase mb-1">% IA</label>
                                            <div className="flex items-center gap-2">
                                                <input type="range" min={0} max={100} step={5}
                                                    value={ruleForm.percentual_ia}
                                                    onChange={e => setRuleForm(p => ({ ...p, percentual_ia: +e.target.value }))}
                                                    className="flex-1 accent-indigo-600"
                                                />
                                                <span className="text-sm font-black text-indigo-600 w-10 text-right">{ruleForm.percentual_ia}%</span>
                                            </div>
                                        </div>
                                        <div>
                                            <label className="block text-[10px] font-black text-slate-500 uppercase mb-1">Não repetir últimos N sim.</label>
                                            <input type="number" min={0}
                                                value={ruleForm.nao_repetir_ultimos_simulados}
                                                onChange={e => setRuleForm(p => ({ ...p, nao_repetir_ultimos_simulados: +e.target.value }))}
                                                className="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500"
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-[10px] font-black text-slate-500 uppercase mb-1">Modo de Dificuldade</label>
                                        <select
                                            value={ruleForm.difficulty_mode}
                                            onChange={e => setRuleForm(p => ({ ...p, difficulty_mode: e.target.value as any }))}
                                            className="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500"
                                        >
                                            <option value="balanceado">Balanceado (Aleatório)</option>
                                            <option value="progressivo">Progressivo (Fácil → Difícil)</option>
                                            <option value="aleatorio">Totalmente Aleatório</option>
                                        </select>
                                    </div>
                                </section>

                                {/* Distributions */}
                                <section className="space-y-3">
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-xs font-black text-slate-400 uppercase tracking-widest">Distribuição por Disciplina</h3>
                                        <span className={`text-[10px] font-black px-2 py-0.5 rounded-full ${pctOk || editingModel.tipo === 'concurso' ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600'}`}>
                                            {editingModel.tipo === 'concurso' ? 'Flexível para Concurso' : `${totalPct.toFixed(0)}% / 100%`}
                                        </span>
                                    </div>

                                    {editingModel.tipo === 'concurso' && (
                                        <p className="text-xs text-slate-400 bg-blue-50 border border-blue-100 rounded-lg px-3 py-2">
                                            ℹ️ No modo Concurso Flexível, a distribuição é definida pelo aluno no momento da criação. O default abaixo é usado apenas como sugestão.
                                        </p>
                                    )}

                                    <div className="space-y-2">
                                        {ruleForm.distributions.map((d, i) => (
                                            <div key={i} className="flex gap-2 items-center bg-slate-50 rounded-xl p-2.5 border border-slate-100">
                                                <input
                                                    type="text"
                                                    placeholder="Disciplina (ex: MATEMÁTICA)"
                                                    value={d.disciplina}
                                                    onChange={e => handleDistChange(i, 'disciplina', e.target.value.toUpperCase())}
                                                    className="flex-1 bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-bold focus:ring-1 focus:ring-indigo-400"
                                                />
                                                <div className="flex items-center gap-1 w-22">
                                                    <input
                                                        type="number" min={0} max={100} step={5}
                                                        value={d.percentual}
                                                        onChange={e => handleDistChange(i, 'percentual', +e.target.value)}
                                                        className="w-16 bg-white border border-slate-200 rounded-lg px-2 py-1.5 text-xs font-bold text-center focus:ring-1 focus:ring-indigo-400"
                                                    />
                                                    <span className="text-xs text-slate-400 font-bold">%</span>
                                                </div>
                                                <button type="button" onClick={() => removeDiscipline(i)}
                                                    className="w-7 h-7 rounded-lg hover:bg-red-50 hover:text-red-600 flex items-center justify-center text-slate-300 transition-colors text-sm font-bold">
                                                    ×
                                                </button>
                                            </div>
                                        ))}
                                    </div>

                                    <button type="button" onClick={addDiscipline}
                                        className="w-full py-2 text-xs font-black text-indigo-600 border border-dashed border-indigo-200 rounded-xl hover:bg-indigo-50 transition-colors">
                                        + Adicionar Disciplina
                                    </button>
                                </section>

                                {/* Save Footer */}
                                <div className="pt-4 border-t border-slate-100 flex gap-3 shrink-0">
                                    <button type="button" onClick={() => setEditingModel(null)}
                                        className="flex-1 py-3 text-slate-500 font-bold hover:bg-slate-100 rounded-xl transition-all text-sm">
                                        Cancelar
                                    </button>
                                    <button type="submit"
                                        disabled={updateRuleMutation.isPending}
                                        className="flex-1 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-100 flex items-center justify-center gap-2 transition-all text-sm">
                                        {updateRuleMutation.isPending && <div className="w-4 h-4 border-2 border-white/30 border-t-white animate-spin rounded-full" />}
                                        💾 Salvar Regras
                                    </button>
                                </div>
                            </form>
                        </motion.aside>
                    </>
                )}
            </AnimatePresence>
        </div>
    );
}
