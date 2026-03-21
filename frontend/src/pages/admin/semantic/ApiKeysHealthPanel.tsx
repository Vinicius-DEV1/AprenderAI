import React from 'react';
import { Key, AlertCircle, CheckCircle2, RefreshCw } from 'lucide-react';
import { DashboardStats } from './Types';

interface ApiKeysHealthPanelProps {
    stats: DashboardStats;
    loading?: boolean;
}

const ApiKeysHealthPanel: React.FC<ApiKeysHealthPanelProps> = ({ stats, loading }) => {
    const keys = stats?.api_keys || [];
    
    // Quick summarize
    const activeKeys = keys.filter(k => k.status === 'active' || k.status === 'online').length;
    const rateLimitedKeys = keys.filter(k => k.status === 'rate_limit').length;
    const errorKeys = keys.filter(k => k.status !== 'active' && k.status !== 'online' && k.status !== 'rate_limit').length;
    
    return (
        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mt-6">
            <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                <div className="flex items-center gap-3">
                    <div className="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                        <Key className="w-5 h-5" />
                    </div>
                    <div>
                        <h3 className="font-bold text-gray-800">Health Check: Chaves de IA</h3>
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
                    {errorKeys > 0 && (
                        <div className="flex items-center gap-1.5 px-2.5 py-1 bg-red-50 text-red-700 rounded-lg">
                            <div className="w-2 h-2 rounded-full bg-red-500"></div>
                            <span>{errorKeys} Erro</span>
                        </div>
                    )}
                </div>
            </div>

            <div className="p-4 overflow-x-auto">
                {loading ? (
                    <div className="flex items-center justify-center p-8">
                        <RefreshCw className="w-6 h-6 animate-spin text-gray-300" />
                    </div>
                ) : keys.length === 0 ? (
                    <div className="text-center py-8 text-gray-500 italic text-sm">
                        Nenhuma chave de API configurada no sistema.
                    </div>
                ) : (
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="text-xs uppercase tracking-wider text-gray-500 border-b border-gray-50 mb-2">
                                <th className="pb-3 px-2 font-black">Nome / Modelo</th>
                                <th className="pb-3 px-2 font-black">Provedor</th>
                                <th className="pb-3 px-2 font-black">Status</th>
                                <th className="pb-3 px-2 font-black text-right">Taxa de Erro</th>
                                <th className="pb-3 px-2 font-black text-right">Requisições</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {keys.map((apiKey) => (
                                <tr key={apiKey.id} className="hover:bg-gray-50/50 transition-colors">
                                    <td className="py-3 px-2">
                                        <div className="font-bold text-gray-800">{apiKey.name}</div>
                                        <div className="text-[10px] font-mono text-gray-400 mt-0.5">{apiKey.model || 'Padrão'}</div>
                                    </td>
                                    <td className="py-3 px-2">
                                        <span className="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-gray-100 text-gray-600">
                                            {apiKey.provider}
                                        </span>
                                    </td>
                                    <td className="py-3 px-2">
                                        {apiKey.status === 'active' || apiKey.status === 'online' ? (
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
                                    <td className="py-3 px-2 text-right">
                                        <span className={`font-mono text-xs ${apiKey.error_rate > 10 ? 'text-red-600 font-bold' : apiKey.error_rate > 0 ? 'text-orange-500' : 'text-green-500'}`}>
                                            {apiKey.error_rate}%
                                        </span>
                                    </td>
                                    <td className="py-3 px-2 text-right font-mono text-xs text-gray-500">
                                        {apiKey.total_requests.toLocaleString()}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
};

export default ApiKeysHealthPanel;
