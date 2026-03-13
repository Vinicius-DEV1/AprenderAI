import { toast } from 'sonner';
import { useState, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import api from '../../api/axios';

export default function ImportIndex() {
    const [uploading, setUploading] = useState(false);
    const [file, setFile] = useState<File | null>(null);
    const [fileName, setFileName] = useState('');
    const [fileSizeMB, setFileSizeMB] = useState('');

    const [progressMode, setProgressMode] = useState(false);
    const [showBanner, setShowBanner] = useState(false);
    const [progressPercent, setProgressPercent] = useState(0);
    const [statusText, setStatusText] = useState('Iniciando Processo...');
    const [importId, setImportId] = useState<number | null>(null);

    const { data, isLoading, refetch } = useQuery({
        queryKey: ['admin-imports'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/import');
            return res.data;
        }
    });

    useEffect(() => {
        const checkActive = async () => {
            try {
                const res = await api.get('/api/v1/admin/import/active-job');
                if (res.data?.active) {
                    setImportId(res.data.import_id);
                    setProgressMode(false);
                    setShowBanner(true);
                }
            } catch (e) {
                // silent
            }
        };
        checkActive();
    }, []);

    useEffect(() => {
        let interval: ReturnType<typeof setInterval>;
        if (importId) {
            interval = setInterval(async () => {
                try {
                    const res = await api.get(`/api/v1/admin/import/${importId}/progress`);
                    const data = res.data;

                    if (data?.status === 'processing' || data?.status === 'completed' || data?.status === 'pending') {
                        if (data.total > 0) {
                            const pct = Math.round((data.processed / data.total) * 100);
                            setProgressPercent(pct);
                            setStatusText(`Inserindo via Job: ${data.processed} de ${data.total} questões (${pct}%).`);
                        } else {
                            setStatusText('Extraindo o ZIP e aguardando processamento inicial...');
                        }

                        if (data.status === 'completed') {
                            setProgressPercent(100);
                            setStatusText('✅ Importação finalizada com sucesso!');
                            setShowBanner(false);
                            clearInterval(interval);
                            toast.success('Lote importado e 100% processado! Clique no Painel de Revisão para gerenciar as novas imagens.');
                            setTimeout(() => {
                                setProgressMode(false);
                                setImportId(null);
                                refetch();
                            }, 3000);
                        }
                    } else if (data?.status === 'failed') {
                        clearInterval(interval);
                        toast.error('A importação falhou no Job em Background: ' + (data.error_message || 'Erro Desconhecido'));
                        resetUpload();
                    }
                } catch (e) {
                    // silent
                }
            }, 2500);
        }
        return () => clearInterval(interval);
    }, [importId, refetch]);

    const selectFile = (e: React.ChangeEvent<HTMLInputElement>) => {
        const selected = e.target.files?.[0];
        if (selected) {
            setFile(selected);
            setFileName(selected.name);
            setFileSizeMB((selected.size / 1024 / 1024).toFixed(2) + ' MB');
        }
    };

    const submit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!file || uploading || importId) {
            toast.info('Já existe uma operação em andamento ou falta arquivo.');
            return;
        }

        setUploading(true);
        setProgressMode(true);
        setShowBanner(false);
        setStatusText('Fazendo upload do arquivo .zip (Demorará conforme a internet)...');

        const formData = new FormData();
        formData.append('zip_file', file);

        try {
            const res = await api.post('/api/v1/admin/import', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            if (res.data?.success) {
                setImportId(res.data.import_id);
                setStatusText('Criando lote e enviando para o Worker extrair...');
                setUploading(false);
                setFile(null);
                setFileName('');
            } else {
                toast.info(res.data?.error || 'Falha ao processar arquivo.');
                resetUpload();
            }
        } catch (e: any) {
            toast.error('Erro crítico ao comunicar com o servidor.');
            resetUpload();
        }
    };

    const moveToBackground = () => {
        setProgressMode(false);
        setShowBanner(true);
    };

    const resetUpload = () => {
        setUploading(false);
        setProgressMode(false);
        setShowBanner(false);
        setFileName('');
        setFileSizeMB('');
        setProgressPercent(0);
        setImportId(null);
        setFile(null);
    };

    const handleDeleteBatch = async (id: number) => {
        if (!window.confirm('ATENÇÃO: Desfazer um lote apagará TODAS as questões vinculadas a ele de forma permanente, incluindo respostas, marcações de favoritos e notas, se houver. O sistema reverterá eventuais quebras em cascata no banco. Tem certeza absoluta que deseja EXCLUIR?')) return;

        try {
            const res = await api.delete(`/api/v1/admin/import/${id}`);
            if (res.data.success) {
                toast.success('Lote revertido e excluído com sucesso.');
                refetch();
            } else {
                toast.error(res.data.message || 'Falha ao reverter lote.');
            }
        } catch (e: any) {
            toast.error(e.response?.data?.message || 'Erro ao reverter lote no servidor.');
        }
    };

    if (isLoading) return <div className="p-8">Carregando painel de importação...</div>;

    const pendingCount = data?.stats?.pending_import || 0;
    const imports = data?.imports || [];

    const getStatusInfo = (status: string) => {
        switch (status) {
            case 'processing': return { class: 'bg-blue-100 text-blue-700', label: '⏳ Processando' };
            case 'completed': return { class: 'bg-green-100 text-green-700', label: '✅ Concluído' };
            case 'failed': return { class: 'bg-red-100 text-red-700', label: '❌ Falhou' };
            case 'reverted': return { class: 'bg-amber-100 text-amber-700', label: '⏪ Revertido' };
            default: return { class: 'bg-gray-100 text-gray-600', label: status || 'N/A' };
        }
    };

    return (
        <div className="py-12">
            <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                {/* Header */}
                <div className="flex justify-between items-center mb-6">
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                        📦 Importação de Questões
                    </h2>
                    <Link to="/admin/import/review" className="px-4 py-2 bg-yellow-500 text-white rounded-md hover:bg-yellow-600 text-sm font-medium flex items-center gap-2">
                        🔍 Painel de Revisão
                        {pendingCount > 0 && (
                            <span className="bg-white text-yellow-600 text-xs font-bold px-2 py-0.5 rounded-full">{pendingCount}</span>
                        )}
                    </Link>
                </div>

                {/* Persist Banner */}
                {showBanner && (
                    <div className="bg-indigo-600 text-white rounded-t-lg rounded-b-none px-6 py-4 flex items-center justify-between shadow-md mb-0">
                        <div className="flex items-center gap-4 w-full">
                            <svg className="animate-spin w-6 h-6 text-white" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <div className="flex-1">
                                <h4 className="font-bold text-sm uppercase tracking-wide">Importação em Background (Lote)</h4>
                                <p className="text-xs text-indigo-200 mt-0.5">{statusText}</p>
                                <div className="w-full bg-indigo-800 rounded-full h-1.5 mt-2">
                                    <div className="bg-blue-300 h-1.5 rounded-full transition-all duration-300" style={{ width: `${progressPercent}%` }}></div>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Upload Card */}
                <div className={`bg-white overflow-hidden shadow-sm p-6 ${showBanner ? 'rounded-t-none border-t border-indigo-400' : 'rounded-lg'}`}>
                    <div className="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
                        <div className="p-3 rounded-full bg-indigo-50">
                            <svg className="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 className="text-lg font-semibold text-gray-800">Novo Upload de Questões</h3>
                            <p className="text-sm text-gray-500">Envie o arquivo <code className="bg-gray-100 px-1 rounded">.zip</code> gerado pelo <code className="bg-gray-100 px-1 rounded">scraper.py</code></p>
                        </div>
                    </div>

                    <form onSubmit={submit}>
                        <label className={`flex flex-col items-center justify-center w-full h-40 border-2 border-dashed rounded-lg cursor-pointer transition-colors ${fileName ? 'border-indigo-400 bg-indigo-50' : 'border-gray-300 bg-gray-50 hover:bg-gray-100'}`}>
                            {!fileName ? (
                                <div className="flex flex-col items-center gap-2 text-gray-400">
                                    <svg className="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <p className="text-sm font-medium">Clique para selecionar o arquivo <strong>.zip</strong></p>
                                    <p className="text-xs">Exportação do scraper.py (máx. 200MB)</p>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center gap-2 text-indigo-600">
                                    <svg className="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <p className="text-sm font-medium">{fileName}</p>
                                    <p className="text-xs text-indigo-400">{fileSizeMB}</p>
                                </div>
                            )}
                            <input type="file" accept=".zip" className="hidden" onChange={selectFile} />
                        </label>

                        <div className="mt-4 p-3 bg-blue-50 rounded-lg text-sm text-blue-700 space-y-1">
                            <p className="font-medium">📋 Estrutura esperada do .zip:</p>
                            <ul className="list-disc list-inside space-y-0.5 text-blue-600">
                                <li>Um arquivo <code className="bg-blue-100 px-1 rounded">banco_[banca].db</code> na raiz</li>
                                <li>Uma pasta <code className="bg-blue-100 px-1 rounded">imagens/</code> com os arquivos .jpg das questões</li>
                            </ul>
                            <p className="text-xs text-blue-500 mt-1">Questões com imagens ou alternativas visuais ficarão como <strong>Pendentes</strong> até a revisão manual.</p>
                        </div>

                        <div className="mt-6 flex justify-end">
                            <button type="submit" disabled={!fileName || uploading} className="px-6 py-2.5 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 font-medium text-sm flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                {uploading && (
                                    <svg className="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                )}
                                <span>{uploading ? 'Processando... Aguarde' : 'Enviar e Importar'}</span>
                            </button>
                        </div>
                    </form>
                </div>

                {/* Progress Modal */}
                {progressMode && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-75 transition-opacity backdrop-blur-sm">
                        <div className="bg-white rounded-xl shadow-2xl p-8 max-w-lg w-full mx-4 transform transition-all text-center">
                            <div className="mb-6 flex justify-center">
                                <div className="p-3 bg-indigo-50 rounded-full">
                                    <svg className="animate-spin w-12 h-12 text-indigo-600" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                </div>
                            </div>
                            <h3 className="text-xl font-bold text-gray-900 mb-2">Processando Importação</h3>
                            <p className="text-sm text-gray-500 mb-6 font-medium bg-gray-50 py-2 px-3 rounded-lg border border-gray-100">{statusText}</p>

                            <div className="relative w-full h-4 bg-gray-200 rounded-full overflow-hidden shadow-inner mb-2">
                                <div className="absolute top-0 left-0 h-full bg-indigo-600 transition-all duration-500 ease-out" style={{ width: `${progressPercent}%` }}>
                                    <div className="w-full h-full opacity-20 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4IiBoZWlnaHQ9IjgiPjxwYXRoIGQ9Ik0tMSsxbDgtOFptMi0yTDggNW0tMiAyTDggNyIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjZmZmIiBzdHJva2Utd2lkdGg9IjEuNSIvPjwvc3ZnPg==')] bg-repeat" style={{ backgroundSize: '16px' }}></div>
                                </div>
                            </div>

                            <div className="flex justify-between text-xs text-gray-500 font-bold uppercase tracking-wider mt-3">
                                <span>0%</span>
                                <span className="text-indigo-600">{progressPercent}%</span>
                                <span>100%</span>
                            </div>

                            <p className="mt-5 text-xs text-gray-400 mb-4">Você também pode enviar este processo para background caso queira sair ou navegar em outras abas.</p>

                            <button onClick={moveToBackground} type="button" className="w-full inline-flex justify-center flex-row items-center gap-2 py-2.5 px-4 text-sm font-medium border border-gray-300 rounded-md shadow-sm bg-white text-gray-700 hover:bg-gray-50 focus:outline-none transition-colors">
                                <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"></path></svg>
                                Ocultar Modal e Rodar em Solitário
                            </button>
                        </div>
                    </div>
                )}

                {/* Histórico */}
                {imports.length > 0 && (
                    <div className="bg-white overflow-hidden shadow-sm rounded-lg">
                        <div className="px-6 py-4 border-b border-gray-100">
                            <h3 className="text-base font-semibold text-gray-800">Histórico de Importações</h3>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lote</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Arquivo</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Admin</th>
                                        <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Total</th>
                                        <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Pendentes</th>
                                        <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Aprovadas</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                                        <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {imports.map((imp: any, idx: number) => {
                                        const statusInfo = getStatusInfo(imp.status);
                                        return (
                                            <tr key={idx} className="hover:bg-gray-50">
                                                <td className="px-4 py-3 font-medium text-gray-800">{imp.batch_name || '—'}</td>
                                                <td className="px-4 py-3 text-gray-500 text-xs">{imp.original_filename || '—'}</td>
                                                <td className="px-4 py-3 text-gray-600">{imp.uploader?.name || '—'}</td>
                                                <td className="px-4 py-3 text-center font-semibold text-gray-700">{imp.total_questions || 0}</td>
                                                <td className="px-4 py-3 text-center">
                                                    {(imp.pending_count || 0) > 0 ? (
                                                        <span className="px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded-full text-xs font-medium">{imp.pending_count}</span>
                                                    ) : (
                                                        <span className="text-gray-400">0</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    <span className="px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-xs font-medium">{imp.approved_count || 0}</span>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${statusInfo.class}`}>{statusInfo.label}</span>
                                                </td>
                                                <td className="px-4 py-3 text-xs text-gray-500">{imp.created_at ? new Date(imp.created_at).toLocaleDateString() : 'N/A'}</td>
                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex items-center gap-2 justify-end">
                                                        <Link to={`/admin/import/${imp.id}/summary`} className="px-3 py-1 bg-indigo-50 text-indigo-600 border border-indigo-200 text-xs rounded hover:bg-indigo-100 font-medium whitespace-nowrap">
                                                            Visualizar Importação
                                                        </Link>
                                                        {imp.status !== 'reverted' && (imp.pending_count || 0) > 0 && (
                                                            <Link to={`/admin/import/review?import_id=${imp.id}`} className="px-3 py-1 bg-yellow-500 text-white text-xs rounded hover:bg-yellow-600 font-medium">
                                                                Revisar
                                                            </Link>
                                                        )}
                                                        {imp.status !== 'reverted' && imp.status !== 'processing' && (
                                                            <button
                                                                onClick={() => handleDeleteBatch(imp.id)}
                                                                className="px-3 py-1 bg-red-50 text-red-600 text-xs rounded hover:bg-red-100 font-medium flex items-center gap-1 border border-red-200 transition-colors whitespace-nowrap"
                                                                title="Desfazer e excluir pacote completo"
                                                            >
                                                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                                Desfazer
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
