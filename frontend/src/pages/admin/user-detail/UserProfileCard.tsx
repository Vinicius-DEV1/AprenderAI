import React from 'react';

interface UserProfileCardProps {
    user: any;
    hasActiveGrant: boolean;
    activeGrant: any;
}

export const UserProfileCard: React.FC<UserProfileCardProps> = ({ user, hasActiveGrant, activeGrant }) => {
    return (
        <div className="bg-white rounded-2xl shadow-sm p-6 text-center border border-gray-100 sticky top-8">
            <div className="relative inline-block">
                <img
                    src={user.avatar_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=e2e8f0&color=475569`}
                    alt={user.name}
                    className="w-24 h-24 rounded-full mx-auto border-4 border-white shadow-sm object-cover"
                />
                <span className={`absolute bottom-1 right-1 w-5 h-5 rounded-full border-2 border-white ${user.is_banned ? 'bg-red-500' : 'bg-green-500'}`} title={user.is_banned ? 'Banido' : 'Ativo'}></span>
            </div>
            <h2 className="mt-4 text-xl font-bold text-gray-800 break-words">{user.name}</h2>
            <p className="text-gray-500 text-sm break-words px-2">{user.email}</p>

            <div className="mt-5 flex flex-col gap-2">
                <div className="flex justify-between items-center px-4 py-2 bg-gray-50 rounded-lg text-sm">
                    <span className="text-gray-500 font-medium text-xs">Plano Atual</span>
                    <span className="font-bold text-indigo-600 truncate max-w-[100px]" title={user.plan?.name || 'Free'}>{user.plan?.name || 'Free'}</span>
                </div>
                <div className="flex justify-between items-center px-4 py-2 bg-gray-50 rounded-lg text-sm">
                    <span className="text-gray-500 font-medium text-xs">ID do Sistema</span>
                    <span className="font-mono font-semibold text-gray-700">#{user.id}</span>
                </div>
                <div className="flex justify-between items-center px-4 py-2 bg-gray-50 rounded-lg text-sm">
                    <span className="text-gray-500 font-medium text-xs">Cargo</span>
                    <span className={`font-bold text-xs uppercase ${user.role === 'admin' ? 'text-amber-600' : 'text-gray-600'}`}>{user.role}</span>
                </div>

                {hasActiveGrant && (
                    <div className="mt-2 border border-indigo-200 bg-indigo-50 p-2.5 rounded-lg text-left">
                        <div className="flex items-center text-indigo-700 text-xs font-bold mb-1">
                            <svg className="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" /></svg>
                            Acesso Concedido Manualmente
                        </div>
                        <div className="text-[10px] text-indigo-600/80 leading-snug">
                            Plano <strong>{activeGrant?.plan?.name || 'Válido'}</strong> até {activeGrant?.current_period_end ? new Date(activeGrant.current_period_end).toLocaleDateString() : 'sempre'}
                        </div>
                    </div>
                )}

                <div className="flex justify-between items-center px-4 py-2 bg-gray-50 rounded-lg text-sm">
                    <span className="text-gray-500 font-medium text-xs">Login Via</span>
                    <span className="font-bold text-gray-700 flex items-center gap-1">
                        {user.google_id ? (
                            <>
                                <svg className="w-3 h-3 text-red-500" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12.48 10.92v3.28h7.84c-.24 1.84-.908 3.152-1.928 4.176-1.152 1.152-2.92 2.392-5.912 2.392-4.584 0-8.208-3.712-8.208-8.296s3.624-8.296 8.208-8.296c2.488 0 4.296.976 5.64 2.256l2.328-2.328C18.528 2.216 15.84 0 12.48 0 6.48 0 1.6 4.84 1.6 11.04s4.88 11.04 10.88 11.04c3.24 0 5.68-1.072 7.744-3.232 2.12-2.12 2.792-5.112 2.792-7.536 0-.72-.056-1.4-.16-2.024h-10.376z" />
                                </svg>
                                Google
                            </>
                        ) : 'E-mail'}
                    </span>
                </div>
                <div className="flex justify-between items-center px-4 py-2 bg-gray-50 rounded-lg text-sm">
                    <span className="text-gray-500 font-medium text-xs">Cadastro</span>
                    <span className="font-semibold text-gray-600 text-xs">{new Date(user.created_at).toLocaleDateString('pt-BR')}</span>
                </div>
            </div>
        </div>
    );
};
