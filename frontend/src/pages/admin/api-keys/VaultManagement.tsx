import { VaultKey } from './Types';
import { UseMutationResult } from '@tanstack/react-query';

interface VaultManagementProps {
    activeSubTab: string;
    setActiveSubTab: (tab: string) => void;
    vaultKeys: VaultKey[];
    editingVaultId: number | null;
    setEditingVaultId: (id: number | null) => void;
    vaultForm: { nickname: string; provider: string; key: string };
    setVaultForm: (form: { nickname: string; provider: string; key: string }) => void;
    addVaultMutation: UseMutationResult<any, any, any, any>;
    deleteVaultMutation: UseMutationResult<any, any, number, any>;
}

export default function VaultManagement({
    activeSubTab,
    setActiveSubTab,
    vaultKeys,
    editingVaultId,
    setEditingVaultId,
    vaultForm,
    setVaultForm,
    addVaultMutation,
    deleteVaultMutation
}: VaultManagementProps) {
    return (
        <div className="space-y-6">
            {/* Sub-tabs for Management */}
            <div className="flex gap-4 mb-2">
                {[
                    { id: 'list', label: 'Lista de Chaves' },
                    { id: 'new', label: 'Cadastrar Nova' },
                    { id: 'routing', label: 'Configurar Roteamento' }
                ].map(sub => (
                    <button
                        key={sub.id}
                        onClick={() => setActiveSubTab(sub.id)}
                        className={`px-4 py-2 rounded-full text-[10px] font-black uppercase tracking-widest transition-all ${
                            activeSubTab === sub.id
                                ? 'bg-slate-800 text-white'
                                : 'bg-white text-slate-400 border border-slate-200 hover:border-slate-300'
                        }`}
                    >
                        {sub.label}
                    </button>
                ))}
            </div>

            {activeSubTab === 'list' && (
                <section className="bg-white rounded-2xl shadow-sm border border-slate-100 p-8">
                    <h4 className="text-xs font-bold text-slate-400 uppercase tracking-widest mb-6">Chaves Armazenadas no Cofre</h4>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        {vaultKeys.map(vk => (
                            <div key={vk.id} className="p-4 rounded-xl border border-slate-100 bg-slate-50 shadow-sm hover:border-indigo-200 transition-all flex justify-between items-center group">
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2">
                                        <span className="font-bold text-slate-800 truncate">{vk.nickname}</span>
                                        <span className={`text-[9px] font-black uppercase px-2 py-0.5 rounded-full flex-shrink-0 ${vk.provider === 'openai' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700'}`}>
                                            {vk.provider}
                                        </span>
                                    </div>
                                    <p className="text-[10px] font-bold text-slate-400 mt-1 uppercase tracking-tighter">
                                        {vk.is_valid ? <span className="text-emerald-500">✅ Validada</span> : <span className="text-amber-500">❓ Não Testada</span>}
                                    </p>
                                </div>
                                <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button
                                        onClick={() => {
                                            setEditingVaultId(vk.id);
                                            setVaultForm({ nickname: vk.nickname, provider: vk.provider, key: '' });
                                            setActiveSubTab('new');
                                        }}
                                        className="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-white rounded-lg transition-all"
                                        title="Editar Chave">✏️</button>
                                    <button
                                        onClick={() => {
                                            if (window.confirm(`Tem certeza?`)) {
                                                deleteVaultMutation.mutate(vk.id);
                                            }
                                        }}
                                        className="p-1.5 text-slate-400 hover:text-red-600 hover:bg-white rounded-lg transition-all"
                                        title="Excluir">🗑️</button>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            )}

            {activeSubTab === 'new' && (
                <section className="max-w-xl bg-white rounded-2xl shadow-sm border border-slate-100 p-8 mx-auto">
                    <div className="flex justify-between items-center mb-6">
                        <h4 className="text-sm font-black text-slate-800 uppercase tracking-widest">{editingVaultId ? 'Editar Chave' : 'Nova Chave de API'}</h4>
                        {editingVaultId && (
                            <button
                                onClick={() => { setEditingVaultId(null); setVaultForm({ nickname: '', provider: 'gemini', key: '' }); setActiveSubTab('list'); }}
                                className="text-[10px] font-black text-indigo-600 uppercase hover:underline">Voltar</button>
                        )}
                    </div>
                    <div className="space-y-4">
                        <div>
                            <label className="text-xs font-bold text-slate-600 mb-1 block">Apelido (Ex: Google Prod)</label>
                            <input
                                value={vaultForm.nickname}
                                onChange={e => setVaultForm({ ...vaultForm, nickname: e.target.value })}
                                className="w-full px-4 py-2 rounded-lg border border-slate-200 outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                                placeholder="Nome amigável" />
                        </div>
                        <div>
                            <label className="text-xs font-bold text-slate-600 mb-1 block">Provedor</label>
                            <select
                                value={vaultForm.provider}
                                onChange={e => setVaultForm({ ...vaultForm, provider: e.target.value })}
                                className="w-full px-4 py-2 rounded-lg border border-slate-200 outline-none focus:ring-2 focus:ring-indigo-500 text-sm">
                                <option value="gemini">Google Gemini</option>
                                <option value="openai">OpenAI</option>
                                <option value="grok">Grok (xAI)</option>
                            </select>
                        </div>
                        <div>
                            <label className="text-xs font-bold text-slate-600 mb-1 block">
                                {editingVaultId ? 'Nova Chave (deixe em branco para manter)' : 'Chave Secreta'}
                            </label>
                            <input
                                type="password"
                                value={vaultForm.key}
                                onChange={e => setVaultForm({ ...vaultForm, key: e.target.value })}
                                className="w-full px-4 py-2 rounded-lg border border-slate-200 outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                                placeholder={editingVaultId ? '••••••••••••' : 'sk-...'} />
                        </div>
                        <button
                            onClick={() => addVaultMutation.mutate(vaultForm)}
                            disabled={addVaultMutation.isPending}
                            className={`w-full py-3 text-white font-bold rounded-xl shadow-lg disabled:opacity-50 transition-all ${editingVaultId ? 'bg-amber-500 hover:bg-amber-600 shadow-amber-100' : 'bg-indigo-600 hover:bg-indigo-700 shadow-indigo-100'}`}>
                            {addVaultMutation.isPending ? 'Guardando...' : editingVaultId ? 'Atualizar no Cofre' : 'Guardar no Cofre'}
                        </button>
                    </div>
                </section>
            )}
        </div>
    );
}
