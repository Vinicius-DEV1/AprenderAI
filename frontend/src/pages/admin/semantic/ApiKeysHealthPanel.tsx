import React, { useState, useEffect } from 'react';
import { Key, AlertCircle, CheckCircle2, RefreshCw, ChevronUp, ChevronDown, Clock, Activity, Zap } from 'lucide-react';
import { DashboardStats } from './Types';

interface ApiKeysHealthPanelProps {
    stats: DashboardStats;
    loading?: boolean;
}

const ApiKeysHealthPanel: React.FC<ApiKeysHealthPanelProps> = ({ stats, loading }) => {
    const [expandedKey, setExpandedKey] = useState<number | null>(null);
    const [expandedBatch, setExpandedBatch] = useState<string | null>(null);
    const [displayLimit, setDisplayLimit] = useState(10);
    const keys = stats?.api_keys || [];
    
    // Reset limits when changing expanded key
    useEffect(() => {
        setExpandedBatch(null);
        setDisplayLimit(10);
    }, [expandedKey]);

    // Quick summarize
    const activeKeys = keys.filter(k => (k.status === 'active' || k.status === 'online') && !k.is_blacklisted).length;
    const blacklistedKeys = keys.filter(k => k.is_blacklisted || k.status === 'blacklisted').length;

    return (
        <div className="bg-white dark:bg-slate-900 rounded-3xl shadow-xl shadow-indigo-500/5 overflow-hidden border border-slate-100 dark:border-slate-800 animate-in fade-in slide-in-from-bottom-4 duration-700">
            <div className="px-6 py-5 border-b border-slate-50 dark:border-slate-800 flex items-center justify-between bg-gradient-to-r from-slate-50/50 to-transparent">
                <div className="flex items-center gap-3">
                    <div className="p-2.5 bg-indigo-500 rounded-2xl shadow-lg shadow-indigo-500/20">
                        <Key className="w-5 h-5 text-white" />
                    </div>
                    <div>
                        <h2 className="text-lg font-black text-slate-800 dark:text-white flex items-center gap-2">
                            Saúde das Chaves AI
                            {loading && <RefreshCw className="w-3.5 h-3.5 animate-spin text-indigo-400" />}
                        </h2>
                        <p className="text-xs text-slate-500 font-medium">Monitoramento de cotas e performance em tempo real</p>
                    </div>
                </div>
                <div className="flex gap-2">
                    <div className="px-3 py-1.5 bg-emerald-50 dark:bg-emerald-500/10 rounded-xl border border-emerald-100 dark:border-emerald-500/20 flex items-center gap-2">
                        <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span className="text-[11px] font-bold text-emerald-700 dark:text-emerald-400">{activeKeys} Online</span>
                    </div>
                    {blacklistedKeys > 0 && (
                        <div className="px-3 py-1.5 bg-rose-50 dark:bg-rose-500/10 rounded-xl border border-rose-100 dark:border-rose-500/20 flex items-center gap-2">
                            <AlertCircle className="w-3.5 h-3.5 text-rose-500" />
                            <span className="text-[11px] font-bold text-rose-700 dark:text-rose-400">{blacklistedKeys} Bloqueadas</span>
                        </div>
                    )}
                </div>
            </div>

            <div className="overflow-x-auto">
                <table className={`w-full transition-opacity duration-300 ${loading ? 'opacity-60' : 'opacity-100'}`}>
                    <thead className="bg-slate-50/50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800">
                        <tr>
                            <th className="px-6 py-4 text-left text-[11px] font-black text-slate-400 uppercase tracking-widest">Modelo / Provedor</th>
                            <th className="px-6 py-4 text-center text-[11px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                            <th className="px-6 py-4 text-center text-[11px] font-black text-slate-400 uppercase tracking-widest">Capacidades</th>
                            <th className="px-6 py-4 text-center text-[11px] font-black text-slate-400 uppercase tracking-widest">Hoje</th>
                            <th className="px-6 py-4 text-center text-[11px] font-black text-slate-400 uppercase tracking-widest">Quota Exceed</th>
                            <th className="px-6 py-4 text-right text-[11px] font-black text-slate-400 uppercase tracking-widest">Ações</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-50 dark:divide-slate-800">
                        {keys.map((apiKey) => (
                            <React.Fragment key={apiKey.id}>
                                <tr className={`group transition-all hover:bg-slate-50/80 dark:hover:bg-slate-800/40 ${expandedKey === apiKey.id ? 'bg-indigo-50/30 dark:bg-indigo-500/5' : ''}`}>
                                    <td className="px-6 py-5">
                                        <div className="flex items-center gap-3">
                                            <div className="w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 shadow-sm flex items-center justify-center p-2 shrink-0 group-hover:scale-110 transition-transform">
                                                <img 
                                                    src={`/img/ai/${apiKey.provider}.png`} 
                                                    alt={apiKey.provider} 
                                                    className="w-full h-full object-contain filter dark:brightness-110"
                                                    onError={(e) => (e.currentTarget.src = '/img/icons/ai-generic.png')}
                                                />
                                            </div>
                                            <div>
                                                <div className="text-sm font-bold text-slate-800 dark:text-white leading-tight">{apiKey.name}</div>
                                                <div className="text-[10px] text-slate-500 font-medium uppercase tracking-wider mt-0.5">{apiKey.model}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-6 py-5 text-center">
                                        <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-tight ${
                                            apiKey.status === 'online' || apiKey.status === 'active'
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400'
                                                : apiKey.status === 'blacklisted'
                                                ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-400'
                                                : 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400'
                                        }`}>
                                            {apiKey.status === 'online' && <CheckCircle2 className="w-3 h-3" />}
                                            {apiKey.status === 'blacklisted' && <AlertCircle className="w-3 h-3" />}
                                            {apiKey.status === 'rate_limit' ? 'Cooldown' : apiKey.status}
                                        </span>
                                    </td>
                                    <td className="px-6 py-5">
                                        <div className="flex flex-wrap justify-center gap-1">
                                            {(apiKey.capabilities || []).map((cap, i) => (
                                                <span key={i} className="px-2 py-0.5 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-[9px] font-bold rounded-md border border-slate-200/50 dark:border-slate-700">
                                                    {cap}
                                                </span>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-6 py-5 text-center">
                                        <div className="inline-flex flex-col items-center">
                                            <span className="text-sm font-black text-slate-700 dark:text-slate-200">{apiKey.requests_today || 0}</span>
                                            <span className="text-[9px] text-slate-400 font-bold uppercase tracking-tighter">REQ</span>
                                        </div>
                                    </td>
                                    <td className="px-6 py-5 text-center">
                                        {(apiKey.quota_exceeded_count || 0) > 0 ? (
                                            <div className="inline-flex flex-col items-center group/quota">
                                                <span className="text-sm font-black text-rose-500 group-hover:scale-125 transition-transform">{apiKey.quota_exceeded_count}</span>
                                                <span className="text-[9px] text-rose-400 font-bold uppercase tracking-tighter">FALHAS</span>
                                            </div>
                                        ) : (
                                            <span className="text-indigo-200 dark:text-slate-700">—</span>
                                        )}
                                    </td>
                                    <td className="px-6 py-5 text-right text-sm font-medium">
                                        <button 
                                            onClick={() => setExpandedKey(expandedKey === apiKey.id ? null : apiKey.id)}
                                            className="text-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-300 transition-all p-2 hover:bg-white dark:hover:bg-slate-700 rounded-xl shadow-none hover:shadow-lg active:scale-90"
                                            title="Ver métricas e histórico"
                                        >
                                            {expandedKey === apiKey.id ? <ChevronUp className="w-5 h-5" /> : <ChevronDown className="w-5 h-5" />}
                                        </button>
                                    </td>
                                </tr>

                                {expandedKey === apiKey.id && (
                                    <tr className="bg-slate-50/50 dark:bg-slate-900/50 border-b border-indigo-100 dark:border-indigo-900/30">
                                        <td colSpan={6} className="px-4 py-4">
                                            <div className="bg-white dark:bg-slate-800 rounded-2xl border border-indigo-100 dark:border-indigo-900/40 shadow-xl overflow-hidden animate-in slide-in-from-top-2 duration-300">
                                                <div className="px-5 py-3 bg-indigo-50/50 dark:bg-indigo-500/5 border-b border-indigo-100 dark:border-indigo-900/40 flex items-center justify-between">
                                                    <div className="flex items-center gap-2">
                                                        <Activity className="w-4 h-4 text-indigo-500" />
                                                        <span className="text-xs font-black text-indigo-900 dark:text-indigo-300 uppercase tracking-widest">Últimas Atividades</span>
                                                    </div>
                                                </div>

                                                <div className="divide-y divide-slate-100 dark:divide-slate-700 max-h-[500px] overflow-y-auto">
                                                    {apiKey.recent_logs && apiKey.recent_logs.length > 0 ? (
                                                        <>
                                                            {apiKey.recent_logs.slice(0, displayLimit).map((log: any, idx: number) => {
                                                                const isBatch = log.count && log.count > 1;
                                                                const logKey = `${apiKey.id}_${log.created_at}_${idx}`;
                                                                const isLogExpanded = expandedBatch === logKey;

                                                                return (
                                                                    <div key={idx} className="flex flex-col">
                                                                        <div 
                                                                            onClick={() => log.count ? setExpandedBatch(isLogExpanded ? null : logKey) : null}
                                                                            className={`px-5 py-3 hover:bg-slate-50/80 dark:hover:bg-slate-800/80 transition-all flex items-center justify-between group cursor-pointer ${isLogExpanded ? 'bg-slate-50 dark:bg-slate-800/90' : ''}`}
                                                                        >
                                                                            <div className="flex items-center gap-3">
                                                                                <div className={`p-2 rounded-lg ${log.type === 'error' ? 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-400' : 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400'}`}>
                                                                                    {log.type === 'error' ? <AlertCircle className="w-3.5 h-3.5" /> : <Zap className={`w-3.5 h-3.5 ${isBatch ? 'animate-pulse' : ''}`} />}
                                                                                </div>
                                                                                <div>
                                                                                    <div className="flex items-center gap-2">
                                                                                        <span className="text-[11px] font-black text-slate-700 dark:text-slate-200 capitalize">
                                                                                            {isBatch ? `${log.module} (Batch: ${log.count})` : log.module}
                                                                                        </span>
                                                                                        <span className={`text-[9px] px-1.5 py-0.5 rounded-md font-bold ${
                                                                                            log.status === 'success' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10' : 'bg-rose-50 text-rose-600 dark:bg-rose-500/10'
                                                                                        }`}>
                                                                                            {log.status === 'quota_exceeded' ? 'QUOTA EXCEEDED' : log.status.toUpperCase()}
                                                                                        </span>
                                                                                    </div>
                                                                                    {log.message && <p className="text-[10px] text-rose-500 mt-0.5 font-medium">{log.message}</p>}
                                                                                </div>
                                                                            </div>
                                                                            <div className="flex items-center gap-6">
                                                                                <div className="flex flex-col items-end">
                                                                                    <span className="text-[10px] font-black text-slate-600 dark:text-slate-300">
                                                                                        {log.tokens > 0 ? `${log.tokens.toLocaleString()} tokens` : '—'}
                                                                                    </span>
                                                                                    <span className="text-[9px] text-slate-400 font-bold">{Math.round(log.execution_time * 1000)}ms</span>
                                                                                </div>
                                                                                <div className="text-[10px] font-bold text-slate-400 bg-slate-100 dark:bg-slate-700 px-2 py-1 rounded-md min-w-[65px] text-center">
                                                                                    {new Date(log.created_at).toLocaleTimeString('pt-BR')}
                                                                                </div>
                                                                                {log.count > 0 && (
                                                                                    <ChevronDown className={`w-3.5 h-3.5 text-slate-300 transition-transform ${isLogExpanded ? 'rotate-180' : ''}`} />
                                                                                )}
                                                                            </div>
                                                                        </div>

                                                                        {/* NESTED EXPANSION (PROMPTS/ITEMS) */}
                                                                        {isLogExpanded && log.items && (
                                                                            <div className="bg-slate-50/50 dark:bg-slate-900/30 px-5 py-4 space-y-4 border-t border-slate-100 dark:border-slate-800 animate-in fade-in duration-200">
                                                                                {log.items.map((item: any, i: number) => (
                                                                                    <div key={i} className="space-y-2 border-l-2 border-indigo-200 dark:border-indigo-800 pl-4 py-1">
                                                                                        <div className="flex justify-between items-center bg-slate-100/50 dark:bg-slate-800/50 px-2 py-1 rounded">
                                                                                            <span className="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-tighter">Requisição #{i + 1}</span>
                                                                                            <div className="flex gap-2">
                                                                                                <span className="text-[9px] font-bold text-slate-400">IN: {item.tokens_in}</span>
                                                                                                <span className="text-[9px] font-bold text-slate-400">OUT: {item.tokens_out}</span>
                                                                                                <span className="text-[9px] font-bold text-indigo-400">TOTAL: {item.tokens_total}</span>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                                                            <div className="space-y-1">
                                                                                                <span className="text-[9px] font-bold text-slate-400 uppercase tracking-widest pl-1">Prompt Enviado</span>
                                                                                                <div className="bg-white dark:bg-slate-800 p-3 rounded-xl border border-slate-100 dark:border-slate-700 text-[11px] text-slate-600 dark:text-slate-300 font-mono leading-relaxed max-h-[150px] overflow-y-auto whitespace-pre-wrap">
                                                                                                    {item.prompt || <span className="italic opacity-50 text-[10px]">Nenhum prompt disponível</span>}
                                                                                                </div>
                                                                                            </div>
                                                                                            <div className="space-y-1">
                                                                                                <span className="text-[9px] font-bold text-slate-400 uppercase tracking-widest pl-1">Retorno (Raw)</span>
                                                                                                <div className="bg-white dark:bg-slate-800 p-3 rounded-xl border border-slate-100 dark:border-slate-700 text-[11px] text-emerald-600 dark:text-emerald-400 font-mono leading-relaxed max-h-[150px] overflow-y-auto whitespace-pre-wrap">
                                                                                                    {item.response || <span className="italic opacity-50 text-[10px] text-slate-400 text-center block py-10">Processado com sucesso (sem retorno textual)</span>}
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                ))}
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                );
                                                            })}

                                                            {apiKey.recent_logs.length > displayLimit && (
                                                                <div className="p-4 flex justify-center bg-slate-50/30 dark:bg-slate-800/30 border-t border-slate-100 dark:border-slate-800">
                                                                    <button 
                                                                        onClick={() => setDisplayLimit(prev => prev + 20)}
                                                                        className="flex items-center gap-2 px-6 py-2 bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 rounded-full text-xs font-black uppercase tracking-widest hover:bg-indigo-100 dark:hover:bg-indigo-500/20 transition-all active:scale-95 shadow-sm"
                                                                    >
                                                                        Exibir Mais Atividades
                                                                        <ChevronDown className="w-3 h-3" />
                                                                    </button>
                                                                </div>
                                                            )}
                                                        </>
                                                    ) : (
                                                        <div className="px-4 py-12 text-center">
                                                            <Clock className="w-8 h-8 text-slate-200 dark:text-slate-800 mx-auto mb-2" />
                                                            <p className="text-xs text-slate-400 italic">Nenhum log recente encontrado para esta chave.</p>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </React.Fragment>
                        ))}
                    </tbody>
                </table>
            </div>

            {stats.global_recent_logs && stats.global_recent_logs.length > 0 && (
                <div className="mt-8 border-t border-slate-200 dark:border-slate-700 pt-6 px-6 pb-6 animate-in fade-in duration-500">
                    <h3 className="text-sm font-bold text-slate-800 dark:text-slate-200 mb-4 flex items-center gap-2">
                        <Activity className="w-4 h-4 text-indigo-500" />
                        Últimas 10 Requisições (Global)
                    </h3>
                    <div className="space-y-2">
                        {stats.global_recent_logs.map((log) => (
                            <div key={log.id} className="flex flex-col sm:flex-row sm:items-center justify-between p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg border border-slate-100 dark:border-slate-700/50 gap-3 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                                <div className="flex items-center gap-3">
                                    <div className={`p-1.5 rounded-md ${log.status === 'success' ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20' : 'bg-rose-100 text-rose-600 dark:bg-rose-500/20'}`}>
                                        {log.status === 'success' ? <Zap className="w-3.5 h-3.5" /> : <AlertCircle className="w-3.5 h-3.5" />}
                                    </div>
                                    <div className="flex flex-col">
                                        <div className="flex items-center gap-2">
                                            <span className="text-xs font-bold text-slate-700 dark:text-slate-300">{log.module}</span>
                                            <span className={`text-[9px] px-1.5 py-0.5 rounded font-bold uppercase tracking-wide ${log.status === 'success' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400'}`}>
                                                {log.status === 'quota_exceeded' ? 'QUOTA EXCEEDED' : log.status}
                                            </span>
                                        </div>
                                        <span className="text-[10px] text-slate-400 font-medium">Key: <span className="text-indigo-600 dark:text-indigo-400 font-mono">{log.api_key}</span></span>
                                    </div>
                                </div>
                                <div className="flex items-center gap-4 sm:justify-end">
                                    <span className="text-[10px] font-bold text-slate-500 dark:text-slate-400">
                                        {log.latency > 0 ? `${(log.latency * 1000).toFixed(0)}ms` : '—'}
                                    </span>
                                    <div className="text-[10px] font-bold text-slate-500 bg-white dark:bg-slate-800 px-2 py-1 rounded shadow-sm border border-slate-100 dark:border-slate-700 text-center min-w-[65px]">
                                        {new Date(log.timestamp).toLocaleTimeString('pt-BR')}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
};

export default ApiKeysHealthPanel;
