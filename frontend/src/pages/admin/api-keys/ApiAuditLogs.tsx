import { AiLog } from './Types';

interface ApiAuditLogsProps {
    aiLogs: AiLog[];
    setActiveLog: (log: AiLog) => void;
    setShowLogModal: (show: boolean) => void;
}

export default function ApiAuditLogs({
    aiLogs,
    setActiveLog,
    setShowLogModal
}: ApiAuditLogsProps) {
    return (
        <div className="space-y-6">
            <div className="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div className="p-6 border-b border-slate-50 bg-slate-50/50 flex justify-between items-center">
                    <h3 className="font-bold text-slate-800 flex items-center gap-3">
                        <span className="p-2 bg-indigo-50 text-indigo-600 rounded-lg text-xs">📋</span>
                        Logs Auditados
                    </h3>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="bg-white text-slate-400 font-bold uppercase tracking-widest whitespace-nowrap">
                            <tr>
                                <th className="px-6 py-4">Usuário</th>
                                <th className="px-6 py-4">Módulo</th>
                                <th className="px-6 py-4">Horário</th>
                                <th className="px-6 py-4">Modelo</th>
                                <th className="px-6 py-4">Tokens</th>
                                <th className="px-6 py-4 text-right">Ação</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-50">
                            {aiLogs.map(log => (
                                <tr key={log.id} className="hover:bg-slate-50 transition-colors group">
                                    <td className="px-6 py-4 font-bold text-slate-700">{log.user?.name || 'Sistema'}</td>
                                    <td className="px-6 py-4 uppercase text-[9px] font-black text-slate-400">{log.module}</td>
                                    <td className="px-6 py-4">{new Date(log.created_at).toLocaleTimeString()}</td>
                                    <td className="px-6 py-4">
                                        <p className="font-bold text-slate-800">{log.provider}</p>
                                        <p className="text-[10px] text-slate-400 font-mono">{log.model}</p>
                                    </td>
                                    <td className="px-6 py-4 font-mono">{log.tokens_used_input + log.tokens_used_output}</td>
                                    <td className="px-6 py-4 text-right">
                                        <button onClick={() => { setActiveLog(log); setShowLogModal(true); }} className="text-indigo-600 font-bold hover:underline">Ver</button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}
