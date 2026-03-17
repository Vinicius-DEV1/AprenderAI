import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import api from '../../api/axios';
export default function Curadoria() {
    const { data, isLoading } = useQuery({
        queryKey: ['admin-curadoria'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/curadoria');
            return res.data;
        }
    });

    if (isLoading) return <div className="p-8">Carregando portal de curadoria...</div>;
    // Fallback para evitar tela branca se data for nulo
    const stats = data?.stats || { pending_import: 0, pending_triage: 0, total_batches: 0 };
    const recentImports = data?.recent_imports || [];

    return (
        <div className="py-6 px-4 md:px-6 w-full">
            <div className="w-full">

                {/* Header (Originalmente injetado via x-slot no Blade) */}
                <div className="mb-6">
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                        🎯 Portal de Curadoria & Produção
                    </h2>
                </div>

                {/* Overview Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                    {/* Card: Triagem IA */}
                    <div className="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-purple-500">
                        <div className="flex items-center justify-between mb-4">
                            <div className="p-3 bg-purple-50 rounded-lg">
                                <svg className="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <span className="text-xs font-bold text-purple-600 bg-purple-50 px-2 py-1 rounded-full uppercase">IA Ativa</span>
                        </div>
                        <h3 className="text-gray-500 text-sm font-medium">Pendências de IA</h3>
                        <p className="text-3xl font-bold text-gray-800 mt-1">{stats?.pending_triage || 0}</p>
                        <Link to="/admin/questions" className="mt-4 inline-flex items-center text-sm font-semibold text-purple-600 hover:text-purple-700">
                            Ir para Triagem →
                        </Link>
                    </div>

                    {/* Card: Revisão de Imagens */}
                    <div className="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-yellow-500">
                        <div className="flex items-center justify-between mb-4">
                            <div className="p-3 bg-yellow-50 rounded-lg">
                                <svg className="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <span className="text-xs font-bold text-yellow-600 bg-yellow-50 px-2 py-1 rounded-full uppercase">Manual</span>
                        </div>
                        <h3 className="text-gray-500 text-sm font-medium">Imagens Perto de Revisão</h3>
                        <p className="text-3xl font-bold text-gray-800 mt-1">{stats?.pending_import || 0}</p>
                        <Link to="/admin/import/review" className="mt-4 inline-flex items-center text-sm font-semibold text-yellow-600 hover:text-yellow-700">
                            Ver Painel de Revisão →
                        </Link>
                    </div>

                    {/* Card: Histórico de Lotes */}
                    <div className="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-blue-500">
                        <div className="flex items-center justify-between mb-4">
                            <div className="p-3 bg-blue-50 rounded-lg">
                                <svg className="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <h3 className="text-gray-500 text-sm font-medium">Lotes IA Processados</h3>
                        <p className="text-3xl font-bold text-gray-800 mt-1">{stats?.total_batches || 0} total</p>
                        <Link to="/admin/triage/history" className="mt-4 inline-flex items-center text-sm font-semibold text-blue-600 hover:text-blue-700">
                            Histórico de Triagem →
                        </Link>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    {/* Métodos de Entrada */}
                    <div className="bg-white rounded-2xl shadow-sm p-8">
                        <h3 className="text-lg font-bold text-gray-800 mb-6 flex items-center gap-2">
                            📥 Métodos de Entrada de Conteúdo
                        </h3>
                        <div className="space-y-4">
                            <Link to="/admin/import" className="flex items-center gap-4 p-4 rounded-xl border border-gray-100 hover:bg-slate-50 transition-all group">
                                <div className="w-12 h-12 bg-indigo-50 rounded-lg flex items-center justify-center text-indigo-600 group-hover:scale-110 transition-transform">
                                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 className="font-bold text-gray-800">Importação de Arquivo (.ZIP)</h4>
                                    <p className="text-xs text-gray-500">Carregue bancos SQLite e pastas de imagens do scraper.</p>
                                </div>
                            </Link>

                            <Link to="/admin/enem-import" className="flex items-center gap-4 p-4 rounded-xl border border-gray-100 hover:bg-slate-50 transition-all group">
                                <div className="w-12 h-12 bg-emerald-50 rounded-lg flex items-center justify-center text-emerald-600 group-hover:scale-110 transition-transform">
                                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 className="font-bold text-gray-800">API ENEM Dev</h4>
                                    <p className="text-xs text-gray-500">Sincronize questões diretamente da API externa.</p>
                                </div>
                            </Link>

                            <Link to="/admin/questions/create" className="flex items-center gap-4 p-4 rounded-xl border border-gray-100 hover:bg-slate-50 transition-all group">
                                <div className="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center text-blue-600 group-hover:scale-110 transition-transform">
                                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 className="font-bold text-gray-800">Cadastro Manual</h4>
                                    <p className="text-xs text-gray-500">Crie uma nova questão individualmente no banco.</p>
                                </div>
                            </Link>
                        </div>
                    </div>

                    {/* Histórico Recente */}
                    <div className="bg-white rounded-2xl shadow-sm p-8">
                        <h3 className="text-lg font-bold text-gray-800 mb-6 flex items-center gap-2">
                            🕒 Lotes Recentes
                        </h3>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-left text-gray-400 font-medium pb-4">
                                        <th className="pb-3 uppercase text-[10px] tracking-wider">Lote</th>
                                        <th className="pb-3 uppercase text-[10px] tracking-wider text-center">Progresso</th>
                                        <th className="pb-3 uppercase text-[10px] tracking-wider text-right">Data</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50">
                                    {recentImports?.length > 0 ? (
                                        recentImports.map((imp: any) => (
                                            <tr key={imp.id}>
                                                <td className="py-3">
                                                    <div className="font-semibold text-gray-800">{imp?.batch_name || imp?.filename || 'Lote sem nome'}</div>
                                                    <div className="text-[10px] text-gray-400">{imp?.status || 'N/A'}</div>
                                                </td>
                                                <td className="py-3 text-center">
                                                    <span className="px-2 py-1 bg-green-50 text-green-600 rounded text-xs font-bold">
                                                        {imp?.total_questions || imp?.total_count || 0} qst
                                                    </span>
                                                </td>
                                                <td className="py-3 text-right text-gray-500 text-xs">
                                                    {imp?.created_at ? new Date(imp.created_at).toLocaleDateString() : 'N/A'}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={3} className="py-10 text-center text-gray-400 italic">Nenhum lote recente processado.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <div className="mt-6">
                            <Link to="/admin/import" className="block text-center py-2 bg-gray-50 text-gray-600 rounded-lg text-sm font-semibold hover:bg-gray-100 transition-colors">
                                Ver Histórico Completo
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
