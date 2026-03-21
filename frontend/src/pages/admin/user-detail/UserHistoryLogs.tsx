import React from 'react';
import { EmptyState } from './components/Common';

interface UserHistoryLogsProps {
    promptHistory: any;
    subscriptions: any[];
    systemLogs: any[];
}

export const UserHistoryLogs: React.FC<UserHistoryLogsProps> = ({
    promptHistory,
    subscriptions,
    systemLogs
}) => {
    return (
        <div className="space-y-8 animate-in fade-in slide-in-from-bottom-2 duration-300">
            {/* IA Logs */}
            <div>
                <h3 className="text-lg font-bold text-gray-800 mb-4 border-b border-gray-100 pb-2 flex items-center">
                    <svg className="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" /></svg>
                    Uso da Inteligência Artificial
                </h3>
                {(!promptHistory?.data || promptHistory.data.length === 0) ? (
                    <EmptyState title="Nenhum registro" message="O usuário não solicitou interações ou chats com a IA até o momento." />
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
                        <table className="w-full text-sm text-left">
                            <thead className="bg-gray-50 border-b border-gray-200">
                                <tr className="text-[11px] text-gray-500 uppercase font-bold tracking-wider">
                                    <th className="px-4 py-3">Data da Interação</th>
                                    <th className="px-4 py-3">Provedor/Modelo</th>
                                    <th className="px-4 py-3">Tokens Gastos (In / Out)</th>
                                    <th className="px-4 py-3 text-right">Custo Estimado</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {promptHistory.data.map((log: any) => (
                                    <tr key={log.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="px-4 py-3 text-gray-600 whitespace-nowrap text-xs">
                                            {new Date(log.created_at).toLocaleString('pt-BR')}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-col">
                                                <span className="font-semibold text-gray-800">{log.provider || '-'}</span>
                                                <span className="text-[10px] text-gray-500">{log.model || '-'}</span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-gray-600 font-mono text-xs">
                                            {log.tokens_used_input !== null ? log.tokens_used_input : '-'} / {log.tokens_used_output !== null ? log.tokens_used_output : '-'}
                                        </td>
                                        <td className="px-4 py-3 text-right font-semibold text-gray-800">
                                            R$ {Number(log.estimated_cost || 0).toLocaleString('pt-BR', { minimumFractionDigits: 4 })}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* Subscription Logs */}
            <div>
                <h3 className="text-lg font-bold text-gray-800 mb-4 border-b border-gray-100 pb-2 flex items-center">
                    <svg className="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                    Ciclos de Assinatura
                </h3>
                {(!subscriptions || subscriptions.length === 0) ? (
                    <EmptyState title="Nenhuma assinatura" message="O histórico de compras premium está vazio." icon={<svg className="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>} />
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
                        <table className="w-full text-sm text-left">
                            <thead className="bg-gray-50 border-b border-gray-200">
                                <tr className="text-[11px] text-gray-500 uppercase font-bold tracking-wider">
                                    <th className="px-4 py-3">Plano Adquirido</th>
                                    <th className="px-4 py-3">Status Base</th>
                                    <th className="px-4 py-3">Início</th>
                                    <th className="px-4 py-3">Data de Renovação/Fim</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {subscriptions.map((sub: any) => (
                                    <tr key={sub.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="px-4 py-3 font-semibold text-gray-800">
                                            {sub.plan?.name || 'Desconhecido'}
                                            {sub.is_manual_grant && <span className="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold uppercase bg-indigo-100 text-indigo-700">Grant</span>}
                                            {sub.installment_count && <span className="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold uppercase bg-emerald-100 text-emerald-700">{sub.installment_count}x</span>}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase ${sub.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'}`}>
                                                {sub.status === 'active' ? 'Ativo' : sub.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-gray-600 text-xs">{new Date(sub.created_at).toLocaleDateString('pt-BR')}</td>
                                        <td className="px-4 py-3 text-gray-600 text-xs">{sub.current_period_end ? new Date(sub.current_period_end).toLocaleDateString('pt-BR') : '-'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* System Audit Logs */}
            <div>
                <h3 className="text-lg font-bold text-gray-800 mb-4 border-b border-gray-100 pb-2 flex items-center">
                    <svg className="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                    Auditoria Sistêmica
                </h3>
                {(!systemLogs || systemLogs.length === 0) ? (
                    <EmptyState title="Auditoria Limpa" message="Sem eventos críticos para exibir no perfil deste aluno." icon={<svg className="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>} />
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
                        <table className="w-full text-sm text-left">
                            <thead className="bg-gray-50 border-b border-gray-200">
                                <tr className="text-[11px] text-gray-500 uppercase font-bold tracking-wider">
                                    <th className="px-4 py-3 w-1/4">Ação</th>
                                    <th className="px-4 py-3 w-1/2">Informação Extra (Payload)</th>
                                    <th className="px-4 py-3 text-right w-1/4">Data</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {systemLogs.map((log: any) => (
                                    <tr key={log.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="px-4 py-3 font-semibold text-indigo-700 text-[11px] uppercase tracking-wide">
                                            {log.action}
                                            {log.ip_address && <span className="block text-[9px] text-gray-400 font-mono mt-0.5 font-normal">{log.ip_address}</span>}
                                        </td>
                                        <td className="px-4 py-3 text-gray-600 text-xs break-words">
                                            {log.description || log.details || '-'}
                                        </td>
                                        <td className="px-4 py-3 text-right text-gray-500 text-xs">
                                            {new Date(log.created_at).toLocaleString('pt-BR')}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    );
};
