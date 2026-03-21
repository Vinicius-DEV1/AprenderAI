import React from 'react';
import { Tooltip } from './components/Common';

interface UserEditFormsProps {
    formState: any;
    setFormState: (state: any) => void;
    getError: (field: string) => string | null;
    handleUpdateProfile: (e: React.FormEvent) => void;
    isPending: boolean;
    userPlan: any;
}

export const UserEditForms: React.FC<UserEditFormsProps> = ({
    formState,
    setFormState,
    getError,
    handleUpdateProfile,
    isPending,
    userPlan
}) => {
    return (
        <form onSubmit={handleUpdateProfile} className="space-y-8 animate-in fade-in slide-in-from-bottom-2 duration-300">
            <div>
                <h3 className="text-lg font-bold text-gray-800 mb-4 border-b border-gray-100 pb-2">Informações Pessoais</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1.5">Nome Completo</label>
                        <input type="text" value={formState.name} onChange={(e) => setFormState({ ...formState, name: e.target.value })} className="w-full rounded-lg border-gray-300 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors" />
                        {getError('name') && <span className="text-xs text-red-500 mt-1 block font-medium">{getError('name')}</span>}
                    </div>
                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1.5">E-mail</label>
                        <input type="email" value={formState.email} onChange={(e) => setFormState({ ...formState, email: e.target.value })} className="w-full rounded-lg border-gray-300 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors" />
                        {getError('email') && <span className="text-xs text-red-500 mt-1 block font-medium">{getError('email')}</span>}
                    </div>
                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1.5">Telefone</label>
                        <input type="text" value={formState.phone} onChange={(e) => setFormState({ ...formState, phone: e.target.value })} className="w-full rounded-lg border-gray-300 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors" placeholder="(00) 00000-0000" />
                        {getError('phone') && <span className="text-xs text-red-500 mt-1 block font-medium">{getError('phone')}</span>}
                    </div>
                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1.5">Nível de Acesso (Cargo)</label>
                        <select value={formState.role} onChange={(e) => setFormState({ ...formState, role: e.target.value })} className="w-full rounded-lg border-gray-300 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
                            <option value="user">Usuário Comum (Aluno)</option>
                            <option value="admin">Administrador Geral</option>
                        </select>
                        {formState.role === 'admin' && (
                            <p className="text-xs text-amber-600 mt-1.5 font-bold flex items-center">
                                <svg className="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" /></svg>
                                Acesso total liberado ao painel
                            </p>
                        )}
                    </div>
                </div>
            </div>

            <div>
                <h3 className="text-lg font-bold text-gray-800 mb-4 border-b border-gray-100 pb-2">Overrides e Limites</h3>
                <p className="text-sm text-gray-500 mb-4">Ajuste os limites individuais deste usuário. Essas configurações sobrepõem os padrões definidos pelo plano original dele.</p>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="bg-white border border-gray-200 rounded-xl p-4 shadow-sm hover:border-indigo-200 transition-colors">
                        <label className="text-sm font-bold text-gray-700 mb-2 flex items-center">
                            Consumo Atual de IA
                            <Tooltip content="Zerar este valor permite que o usuário volte a consumir o limite de requisições de IA no ciclo atual." />
                        </label>
                        <div className="flex items-center gap-2">
                            <input type="number" value={formState.ai_questions_count} onChange={(e) => setFormState({ ...formState, ai_questions_count: parseInt(e.target.value) || 0 })} className="flex-1 rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500" min="0" />
                            <button type="button" onClick={() => setFormState({ ...formState, ai_questions_count: 0 })} className="px-3 py-2 bg-white border border-gray-300 rounded-lg text-xs font-bold text-indigo-600 hover:bg-indigo-50 transition-colors">Zerar</button>
                        </div>
                        <p className="text-[10px] text-gray-500 mt-2 font-medium">Você pode redefinir manualmente o que ele já usou.</p>
                    </div>

                    <div className="bg-white border border-gray-200 rounded-xl p-4 shadow-sm hover:border-indigo-200 transition-colors">
                        <label className="text-sm font-bold text-gray-700 mb-2 flex items-center">
                            Limite Override - IA
                            <Tooltip content="Mantenha vazio para usar o limite do plano. Preencha com 0 para dar acesso Ilimitado. Qualquer outro número será o novo teto de uso." />
                        </label>
                        <input type="number" value={formState.max_ai_questions_override} onChange={(e) => setFormState({ ...formState, max_ai_questions_override: e.target.value })} className="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500" min="0" placeholder="Ex: 50 (Vazio = Plano)" />
                        <p className="text-[10px] text-gray-500 mt-2 font-medium">Atual (Plano): {userPlan?.max_ai_questions ?? 'N/A'}</p>
                    </div>

                    <div className="bg-white border border-gray-200 rounded-xl p-4 shadow-sm hover:border-indigo-200 transition-colors">
                        <label className="text-sm font-bold text-gray-700 mb-2 flex items-center">
                            Limite Override - Simulados
                            <Tooltip content="Mantenha vazio para usar o limite do plano. Preencha com 0 para dar acesso Ilimitado." />
                        </label>
                        <input type="number" value={formState.max_simulations_override} onChange={(e) => setFormState({ ...formState, max_simulations_override: e.target.value })} className="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500" min="0" placeholder="Vazio = Usar Plano" />
                        <p className="text-[10px] text-gray-500 mt-2 font-medium">Atual (Plano): {userPlan?.simulations_limit ?? 'N/A'}</p>
                    </div>

                    <div className="bg-white border border-gray-200 rounded-xl p-4 shadow-sm hover:border-indigo-200 transition-colors">
                        <label className="text-sm font-bold text-gray-700 mb-2 flex items-center">
                            Limite Override - Redações
                            <Tooltip content="Mantenha vazio para usar o limite do plano. Preencha com 0 para dar acesso Ilimitado." />
                        </label>
                        <input type="number" value={formState.max_essays_override} onChange={(e) => setFormState({ ...formState, max_essays_override: e.target.value })} className="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500" min="0" placeholder="Vazio = Usar Plano" />
                        <p className="text-[10px] text-gray-500 mt-2 font-medium">Atual (Plano): {userPlan?.essays_limit ?? 'N/A'}</p>
                    </div>
                </div>

                <div className="flex justify-end pt-8 border-t border-gray-100 mt-8">
                    <button type="submit" disabled={isPending} className="px-8 py-3 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 font-bold transition-colors disabled:opacity-70 disabled:cursor-not-allowed shadow-sm text-sm uppercase tracking-wide">
                        {isPending ? 'Salvando...' : 'Salvar Informações'}
                    </button>
                </div>
            </div>
        </form>
    );
};
