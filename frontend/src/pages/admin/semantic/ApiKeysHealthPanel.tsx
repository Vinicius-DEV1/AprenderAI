import React, { useState } from 'react';
import { Key, AlertCircle, CheckCircle2, RefreshCw, ChevronUp, ChevronDown, Clock, Zap } from 'lucide-react';
import { DashboardStats } from './Types';

interface ApiKeysHealthPanelProps {
    stats: DashboardStats;
    loading?: boolean;
}

const ApiKeysHealthPanel: React.FC<ApiKeysHealthPanelProps> = ({ stats, loading }) => {
    const [expandedKey, setExpandedKey] = useState<number | null>(null);
    const keys = stats?.api_keys || [];
    
    // Quick summarize
    const activeKeys = keys.filter(k => (k.status === 'active' || k.status === 'online') && !k.is_blacklisted).length;
    const blacklistedKeys = keys.filter(k => k.is_blacklisted || k.status === 'blacklisted').length;
    const rateLimitedKeys = keys.filter(k => k.status === 'rate_limit' && !k.is_blacklisted).length;
    const errorKeys = keys.filter(k => k.status !== 'active' && k.status !== 'online' && k.status !== 'rate_limit' && k.status !== 'blacklisted' && !k.is_blacklisted).length;
    
    return (
        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mt-6">
            <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                <div className="flex items-center gap-3">
                    <div className="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                        <Key className="w-5 h-5" />
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <h3 className="font-bold text-gray-800">Health Check: Chaves de IA</h3>
                            {loading && <RefreshCw className="w-3.5 h-3.5 animate-spin text-indigo-500" />}
                        </div>
                        <p className="text-xs text-gray-500 mt-0.5">Disponibilidade e limites de taxa das APIs externas</p>
                    </div>
                </div>
                <div className="flex gap-2 text-xs font-bold">
                    <div className="flex items-center gap-1.5 px-2.5 py-1 bg-green-50 text-green-700 rounded-lg">
                        <div className="w-2 h-2 rounded-full bg-green-500"></div>
                        <span>{activeKeys} Ativas</span>
                    </div>
                    {rateLimitedKeys > 0 && (
                        <div className="flex items-center gap-1.5 px-2.5 py-1 bg-orange-50 text-orange-700 rounded-lg">
                            <div className="w-2 h-2 rounded-full bg-orange-500 animate-pulse"></div>
                            <span>{rateLimitedKeys} Rate Limit</span>
                        </div>
                    )}
                    {blacklistedKeys > 0 && (
                        <div className="flex items-center gap-1.5 px-2.5 py-1 bg-amber-50 text-amber-700 rounded-lg">
                            <div className="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></div>
                            <span>{blacklistedKeys} Cooldown</span>
                        </div>
                    )}
                    {errorKeys > 0 && (
                        <div className="flex items-center gap-1.5 px-2.5 py-1 bg-red-50 text-red-700 rounded-lg">
                            <div className="w-2 h-2 rounded-full bg-red-500"></div>
                            <span>{errorKeys} Erro</span>
                        </div>
                    )}
                </div>
            </div>

            <div className={`p-4 overflow-x-auto transition-opacity duration-300 ${loading && keys.length > 0 ? 'opacity-70' : 'opacity-100'}`}>
                {keys.length === 0 && !loading ? (
                    <div className="text-center py-8 text-gray-500 italic text-sm">
                        Nenhuma chave de API configurada no sistema.
                    </div>
                ) : keys.length === 0 && loading ? (
                    <div className="flex items-center justify-center p-12">
                        <RefreshCw className="w-8 h-8 animate-spin text-gray-300" />
                    </div>
                ) : (
                    <table className="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr className="text-xs uppercase tracking-wider text-gray-500 border-b border-gray-50 bg-gray-50/30">
                                <th className="py-3 px-4 font-black">Nome / Modelo</th>
                                <th className="py-3 px-4 font-black">Provedor</th>
                                <th className="py-3 px-4 font-black">Status</th>
                                <th className="py-3 px-4 font-black text-center">Requisições</th>
                                <th className="py-3 px-4 font-black text-center">Hoje</th>
                                <th className="py-3 px-4 font-black text-center">Erros Cota</th>
                                <th className="py-3 px-4 font-black text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {keys.map((apiKey) => (
                                <React.Fragment key={apiKey.id}>
                                    <tr className={`hover:bg-gray-50/50 transition-colors ${expandedKey === apiKey.id ? 'bg-indigo-50/20' : ''}`}>
                                        <td className="py-3 px-4">
                                            <div className="font-bold text-gray-800">{apiKey.name}</div>
                                            <div className="text-[10px] font-mono text-gray-400 mt-0.5">{apiKey.model || 'Padrão'}</div>
                                        </td>
                                        <td className="py-3 px-4">
                                            <div className="flex flex-col gap-1">
                                                <span className="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-gray-100 text-gray-600 w-fit">
                                                    {apiKey.provider}
                                                </span>
                                                {apiKey.capabilities && apiKey.capabilities.length > 0 && (
                                                    <div className="flex flex-wrap gap-1">
                                                        {apiKey.capabilities.map(cap => (
                                                            <span key={cap} className="px-1.5 py-0.5 rounded-[4px] text-[8px] font-black uppercase bg-indigo-50 text-indigo-600 border border-indigo-100 shadow-sm">
                                                                {cap}
                                                            </span>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                        </td>
                                        <td className="py-3 px-4">
                                            {apiKey.is_blacklisted || apiKey.status === 'blacklisted' ? (
                                                <span className="flex flex-col text-xs font-semibold text-amber-600">
                                                    <div className="flex items-center gap-1.5">
                                                        <RefreshCw className="w-3.5 h-3.5 animate-spin-slow" /> Cooldown (60m)
                                                    </div>
                                                    <span className="text-[10px] font-normal text-amber-500 mt-0.5 ml-5">
                                                        Temporariamente pausada
                                                    </span>
                                                </span>
                                            ) : apiKey.status === 'active' || apiKey.status === 'online' ? (
                                                <span className="flex items-center gap-1.5 text-xs font-semibold text-green-600">
                                                    <CheckCircle2 className="w-3.5 h-3.5" /> Normal
                                                </span>
                                            ) : apiKey.status === 'rate_limit' ? (
                                                <span className="flex flex-col text-xs font-semibold text-orange-600">
                                                    <div className="flex items-center gap-1.5">
                                                        <AlertCircle className="w-3.5 h-3.5" /> Rate Limit
                                                    </div>
                                                    {apiKey.rate_limit_ends_in && (
                                                        <span className="text-[10px] font-normal text-orange-500 mt-0.5 ml-5">
                                                            Libera em {Math.ceil(apiKey.rate_limit_ends_in / 60)} min
                                                        </span>
                                                    )}
                                                </span>
                                            ) : (
                                                <span className="flex items-center gap-1.5 text-xs font-semibold text-red-600">
                                                    <AlertCircle className="w-3.5 h-3.5" /> {apiKey.status}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-4 whitespace-nowrap text-sm text-slate-600 text-center font-medium">
                                            {apiKey.total_requests.toLocaleString()}
                                        </td>
                                        <td className="px-4 py-4 whitespace-nowrap text-center">
                                            <span className="text-sm font-medium text-slate-900 bg-slate-100 px-2 py-1 rounded-md">
                                                {apiKey.requests_today?.toLocaleString() ?? 0}
                                            </span>
                                        </td>
                                        <td className="px-4 py-4 whitespace-nowrap text-center">
                                            <span className={`text-sm font-bold px-2 py-1 rounded-md ${
                                                (apiKey.quota_exceeded_count ?? 0) > 0 
                                                ? 'bg-rose-50 text-rose-600' 
                                                : 'bg-slate-50 text-slate-400'
                                            }`}>
                                                {apiKey.quota_exceeded_count?.toLocaleString() ?? 0}
                                            </span>
                                        </td>
                                        <td className="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button 
                                                onClick={() => setExpandedKey(expandedKey === apiKey.id ? null : apiKey.id)}
                                                className="text-indigo-400 hover:text-indigo-600 transition-colors p-1"
                                            >
                                                {expandedKey === apiKey.id ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
                                            </button>
                                        </td>
                                    </tr>
                                    {expandedKey === apiKey.id && (
                                        <tr className="bg-slate-50/50 border-b border-indigo-100">
                                            <td colSpan={7} className="px-4 py-4">
                                                <div className="bg-white rounded-xl border border-indigo-100 shadow-lg overflow-hidden animate-in slide-in-from-top-2 duration-200">
                                                    <div className="px-4 py-2 bg-indigo-50/50 border-b border-indigo-100 flex items-center justify-between">
                                                        <span className="text-[10px] font-black uppercase text-indigo-600 flex items-center gap-1.5 tracking-widest">
                                                            <Clock className="w-3 h-3" /> Últimas 10 Atividades
                                                        </span>
                                                        <span className="text-[10px] italic text-indigo-400">Dados em tempo real</span>
                                                    </div>
                                                    <div className="divide-y divide-slate-100 max-h-[300px] overflow-y-auto">
                                                        {apiKey.recent_logs && apiKey.recent_logs.length > 0 ? (
                                                            apiKey.recent_logs.map((log: any, idx: number) => (
                                                                <div key={idx} className="px-4 py-2.5 flex items-center justify-between hover:bg-indigo-50/10 transition-colors">
                                                                    <div className="flex items-center gap-3">
                                                                        <div className={`p-1.5 rounded-md ${log.type === 'error' ? 'bg-rose-50 text-rose-500' : 'bg-emerald-50 text-emerald-500'}`}>
                                                                            {log.type === 'error' ? <AlertCircle className="w-3 h-3" /> : <Zap className="w-3 h-3" />}
                                                                        </div>
                                                                        <div className="flex flex-col">
                                                                            <div className="flex items-center gap-2">
                                                                                <span className={`text-[11px] font-bold ${log.status === 'quota_exceeded' ? 'text-rose-700' : log.type === 'error' ? 'text-rose-600' : 'text-slate-700'}`}>
                                                                                    {log.status === 'quota_exceeded' ? 'Quota Exceeded' : (log.module || 'System')}
                                                                                </span>
                                                                                <span className="text-[9px] text-slate-400 font-mono">
                                                                                    {new Date(log.created_at).toLocaleTimeString('pt-BR')}
                                                                                </span>
                                                                            </div>
                                                                            {log.message && (
                                                                                <div className="text-[10px] text-slate-500 mt-0.5 max-w-[400px] truncate" title={log.message}>
                                                                                    {log.message}
                                                                                </div>
                                                                            )}
                                                                        </div>
                                                                    </div>
                                                                    <div className="flex items-center gap-4">
                                                                        {log.tokens > 0 && (
                                                                            <div className="flex flex-col items-end">
                                                                                <span className="text-[9px] font-bold text-slate-500">TOKENS</span>
                                                                                <span className="text-[10px] font-mono text-slate-400">{log.tokens}</span>
                                                                            </div>
                                                                        )}
                                                                        <div className="flex flex-col items-end min-w-[70px]">
                                                                            <span className="text-[9px] font-bold text-slate-500">LATÊNCIA</span>
                                                                            <span className={`text-[10px] font-mono ${log.execution_time > 2000 ? 'text-amber-500' : 'text-slate-400'}`}>
                                                                                {log.execution_time > 0 ? `${log.execution_time.toFixed(0)}ms` : '-'}
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            ))
                                                        ) : (
                                                            <div className="px-4 py-12 text-center">
                                                                <Clock className="w-8 h-8 text-slate-200 mx-auto mb-2" />
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
                )}
            </div>
        </div>
    );
};

export default ApiKeysHealthPanel;
