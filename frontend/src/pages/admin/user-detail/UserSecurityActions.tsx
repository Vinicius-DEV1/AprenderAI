import React from 'react';

interface UserSecurityActionsProps {
    user: any;
    toggleStatusMutation: any;
    resetPasswordMutation: any;
    manualPassword: string;
    setManualPassword: (val: string) => void;
}

export const UserSecurityActions: React.FC<UserSecurityActionsProps> = ({
    user,
    toggleStatusMutation,
    resetPasswordMutation,
    manualPassword,
    setManualPassword
}) => {
    return (
        <div className="space-y-8 animate-in fade-in slide-in-from-bottom-2 duration-300">
            <div className="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                <div className="p-5 border-b border-gray-100 flex justify-between items-center sm:flex-row flex-col gap-4">
                    <div>
                        <h4 className="text-base font-bold text-gray-800 flex items-center">
                            <span className={`w-2.5 h-2.5 rounded-full mr-2 ${user.is_banned ? 'bg-red-500' : 'bg-green-500'}`}></span>
                            Status de Acesso à Plataforma
                        </h4>
                        <p className="text-sm text-gray-500 mt-1">
                            {user.is_banned ? 'Usuário banido. Atualmente impedido de fazer login no painel.' : 'Conta legítima. Sessão e login permitidos livremente.'}
                        </p>
                    </div>
                    <button
                        onClick={() => { if (window.confirm(`Tem certeza de que deseja ${user.is_banned ? 'DESBLOQUEAR' : 'BANIR'} o usuário?`)) toggleStatusMutation.mutate(); }}
                        className={`px-5 py-2.5 rounded-lg text-xs font-black uppercase tracking-wider transition-colors border shadow-sm shrink-0 ${user.is_banned ? 'border-green-200 bg-green-50 text-green-700 hover:bg-green-100' : 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100'}`}
                    >
                        {user.is_banned ? 'Desbloquear Usuário' : 'Banir Usuário'}
                    </button>
                </div>

                <div className="p-5 bg-gray-50/50">
                    <h4 className="text-sm font-bold text-gray-700 mb-3 block">Recuperação e Reset de Senha</h4>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div className="bg-white p-4 border border-gray-200 rounded-xl shadow-sm">
                            <p className="text-xs text-gray-500 mb-3">Opção recomendada: Apenas enviar o link mágico de redefinição para a caixa de e-mail cadastrada.</p>
                            <button
                                onClick={() => { if (window.confirm('Habilitar o envio de email para ' + user.email + '?')) resetPasswordMutation.mutate({ send_email: 1 }); }}
                                className="w-full flex items-center justify-center gap-2 bg-gray-50 border border-gray-200 text-gray-700 py-2.5 rounded-lg hover:bg-gray-100 transition-colors text-xs font-bold shadow-sm"
                            >
                                <svg className="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                Enviar Email de Reset
                            </button>
                        </div>
                        <div className="bg-white p-4 border border-gray-200 rounded-xl shadow-sm">
                            <p className="text-xs text-gray-500 mb-3">Opção forçada: Definir uma nova senha de substituição independentemente do usuário e na hora.</p>
                            <div className="flex gap-2">
                                <input
                                    type="text"
                                    value={manualPassword}
                                    onChange={(e) => setManualPassword(e.target.value)}
                                    placeholder="Digite uma nova senha segura"
                                    className="flex-1 rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500 bg-gray-50 focus:bg-white transition-colors"
                                />
                                <button
                                    onClick={() => { if (manualPassword && window.confirm('Deseja forçar essa nova senha na base de dados?')) resetPasswordMutation.mutate({ new_password: manualPassword }); }}
                                    disabled={!manualPassword}
                                    className="bg-gray-800 text-white px-4 rounded-lg hover:bg-gray-900 text-xs font-bold uppercase transition-colors disabled:opacity-50 disabled:cursor-not-allowed shadow-sm shrink-0"
                                >
                                    Gravar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};
