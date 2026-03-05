import { useState, useEffect, useCallback } from 'react';
import { toast } from 'sonner';
import api from '../../api/axios';

interface BackupJob {
    id: number;
    status: 'pending' | 'running' | 'completed' | 'failed';
    s3_bucket: string | null;
    s3_key: string | null;
    file_size_bytes: number | null;
    file_size_human: string | null;
    duration_seconds: number | null;
    error_message: string | null;
    started_at: string | null;
    completed_at: string | null;
    created_at: string;
    triggered_by: { id: number; name: string } | null;
}

interface BackupSettings {
    s3_bucket: string;
    s3_prefix: string;
    schedule_enabled: boolean;
    schedule_time: string;
}

const StatusBadge = ({ status }: { status: BackupJob['status'] }) => {
    const map = {
        pending: { label: 'Aguardando', cls: 'bg-yellow-100 text-yellow-700', dot: 'bg-yellow-400' },
        running: { label: 'Em Progresso', cls: 'bg-blue-100 text-blue-700', dot: 'bg-blue-500 animate-pulse' },
        completed: { label: 'Concluído', cls: 'bg-green-100 text-green-700', dot: 'bg-green-500' },
        failed: { label: 'Falhou', cls: 'bg-red-100 text-red-700', dot: 'bg-red-500' },
    };
    const { label, cls, dot } = map[status] ?? map.pending;
    return (
        <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ${cls}`}>
            <span className={`w-1.5 h-1.5 rounded-full ${dot}`} />
            {label}
        </span>
    );
};

export default function AdminBackups() {
    const [jobs, setJobs] = useState<BackupJob[]>([]);
    const [settings, setSettings] = useState<BackupSettings>({
        s3_bucket: '',
        s3_prefix: 'backups/',
        schedule_enabled: true,
        schedule_time: '00:00',
    });
    const [loading, setLoading] = useState(true);
    const [triggering, setTriggering] = useState(false);
    const [savingSettings, setSavingSettings] = useState(false);
    const [activeJobId, setActiveJobId] = useState<number | null>(null);
    const [downloadingId, setDownloadingId] = useState<number | null>(null);

    // -------------------------------------------------------------------------
    // Fetch history + settings
    // -------------------------------------------------------------------------
    const fetchData = useCallback(async () => {
        try {
            const res = await api.get('/api/v1/admin/backups');
            setJobs(res.data.jobs ?? []);
            const s = res.data.settings;
            if (s) setSettings(s);
        } catch {
            // silent
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    // -------------------------------------------------------------------------
    // Poll active job status
    // -------------------------------------------------------------------------
    useEffect(() => {
        if (!activeJobId) return;

        const interval = setInterval(async () => {
            try {
                const res = await api.get(`/api/v1/admin/backups/${activeJobId}/status`);
                const job: BackupJob = res.data.job;

                setJobs(prev => prev.map(j => j.id === job.id ? job : j));

                if (job.status === 'completed' || job.status === 'failed') {
                    setActiveJobId(null);
                    setTriggering(false);
                    if (job.status === 'completed') {
                        toast.success(`Backup concluído com sucesso! Tamanho: ${job.file_size_human}`);
                    } else {
                        toast.error(`Backup falhou: ${job.error_message}`);
                    }
                }
            } catch { /* silent */ }
        }, 3000);

        return () => clearInterval(interval);
    }, [activeJobId]);

    // -------------------------------------------------------------------------
    // Trigger manual backup
    // -------------------------------------------------------------------------
    const handleTrigger = async () => {
        if (!settings.s3_bucket) {
            toast.error('Configure o Bucket S3 antes de fazer o backup.');
            return;
        }
        setTriggering(true);
        try {
            const res = await api.post('/api/v1/admin/backups/trigger');
            const newJob: BackupJob = {
                id: res.data.backup_id,
                status: 'pending',
                s3_bucket: settings.s3_bucket,
                s3_key: null,
                file_size_bytes: null,
                file_size_human: null,
                duration_seconds: null,
                error_message: null,
                started_at: null,
                completed_at: null,
                created_at: new Date().toISOString(),
                triggered_by: null,
            };
            setJobs(prev => [newJob, ...prev]);
            setActiveJobId(res.data.backup_id);
            toast.info('Backup iniciado. Acompanhe o progresso abaixo.');
        } catch (err: any) {
            setTriggering(false);
            toast.error(err?.response?.data?.message ?? 'Erro ao iniciar backup.');
        }
    };

    // -------------------------------------------------------------------------
    // Save settings
    // -------------------------------------------------------------------------
    const handleSaveSettings = async (e: React.FormEvent) => {
        e.preventDefault();
        setSavingSettings(true);
        try {
            await api.post('/api/v1/admin/backups/settings', settings);
            toast.success('Configurações salvas com sucesso!');
        } catch (err: any) {
            toast.error(err?.response?.data?.message ?? 'Erro ao salvar configurações.');
        } finally {
            setSavingSettings(false);
        }
    };

    // -------------------------------------------------------------------------
    // Download pre-signed URL
    // -------------------------------------------------------------------------
    const handleDownload = async (jobId: number) => {
        setDownloadingId(jobId);
        try {
            const res = await api.get(`/api/v1/admin/backups/${jobId}/download`);
            window.open(res.data.download_url, '_blank');
        } catch {
            toast.error('Não foi possível gerar o link de download.');
        } finally {
            setDownloadingId(null);
        }
    };

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------
    const formatDate = (dt: string | null) => {
        if (!dt) return '—';
        return new Date(dt).toLocaleString('pt-BR');
    };

    const formatDuration = (secs: number | null) => {
        if (secs === null) return '—';
        if (secs < 60) return `${secs}s`;
        return `${Math.floor(secs / 60)}m ${secs % 60}s`;
    };

    const isActive = (status: string) => status === 'pending' || status === 'running';

    return (
        <div className="py-6 px-4 md:px-6 w-full max-w-7xl">
            {/* Header */}
            <div className="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-bold text-gray-800 mb-1 flex items-center gap-3">
                        <span className="p-2 bg-indigo-100 rounded-xl text-indigo-600">
                            <svg className="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                            </svg>
                        </span>
                        Backup do Banco de Dados
                    </h1>
                    <p className="text-gray-500 text-sm">Gerencie backups MySQL → S3. O processo é assíncrono e não impacta a performance do sistema.</p>
                </div>

                <button
                    onClick={handleTrigger}
                    disabled={triggering || !!activeJobId || !settings.s3_bucket}
                    className="flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white rounded-lg font-semibold text-sm hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm"
                >
                    {triggering ? (
                        <>
                            <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Backup em andamento...
                        </>
                    ) : (
                        <>
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            Fazer Backup Agora
                        </>
                    )}
                </button>
            </div>

            {/* IAM Role info banner */}
            <div className="mb-6 bg-blue-50 border border-blue-200 rounded-xl p-4 flex gap-3">
                <svg className="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <div className="text-sm text-blue-800">
                    <p className="font-semibold mb-0.5">Autenticação via IAM Role (Segurança Máxima)</p>
                    <p className="text-blue-700">A autenticação com o S3 é feita automaticamente pela IAM Role da instância EC2. Não é necessário configurar Access Keys aqui — e isso é intencional.</p>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Settings Panel */}
                <div className="lg:col-span-1">
                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h2 className="text-base font-bold text-gray-800 mb-5 flex items-center gap-2">
                            <svg className="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Configurações
                        </h2>

                        <form onSubmit={handleSaveSettings} className="space-y-5">
                            <div>
                                <label className="block text-xs font-semibold text-gray-600 mb-1.5">Nome do Bucket S3 *</label>
                                <input
                                    type="text"
                                    placeholder="meu-bucket-de-backup"
                                    value={settings.s3_bucket}
                                    onChange={e => setSettings(s => ({ ...s, s3_bucket: e.target.value }))}
                                    className="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-gray-50"
                                    required
                                />
                                <p className="text-xs text-gray-400 mt-1">Apenas o nome do bucket, sem s3:// ou ARN.</p>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-gray-600 mb-1.5">Prefixo / Pasta no Bucket</label>
                                <input
                                    type="text"
                                    placeholder="backups/"
                                    value={settings.s3_prefix}
                                    onChange={e => setSettings(s => ({ ...s, s3_prefix: e.target.value }))}
                                    className="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-gray-50"
                                />
                            </div>

                            <div className="border-t border-gray-100 pt-4">
                                <label className="block text-xs font-semibold text-gray-600 mb-3">Agendamento Automático</label>

                                <div className="flex items-center justify-between mb-3">
                                    <span className="text-sm text-gray-700">Backup diário automático</span>
                                    <button
                                        type="button"
                                        onClick={() => setSettings(s => ({ ...s, schedule_enabled: !s.schedule_enabled }))}
                                        className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none ${settings.schedule_enabled ? 'bg-indigo-600' : 'bg-gray-300'}`}
                                    >
                                        <span className={`inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform ${settings.schedule_enabled ? 'translate-x-6' : 'translate-x-1'}`} />
                                    </button>
                                </div>

                                {settings.schedule_enabled && (
                                    <div>
                                        <label className="block text-xs text-gray-500 mb-1">Horário (UTC)</label>
                                        <input
                                            type="time"
                                            value={settings.schedule_time}
                                            onChange={e => setSettings(s => ({ ...s, schedule_time: e.target.value }))}
                                            className="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-gray-50"
                                        />
                                    </div>
                                )}
                            </div>

                            <button
                                type="submit"
                                disabled={savingSettings}
                                className="w-full py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50 transition-all"
                            >
                                {savingSettings ? 'Salvando...' : 'Salvar Configurações'}
                            </button>
                        </form>
                    </div>
                </div>

                {/* History Table */}
                <div className="lg:col-span-2">
                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h2 className="text-base font-bold text-gray-800 flex items-center gap-2">
                                <svg className="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                Histórico de Backups
                            </h2>
                            <button
                                onClick={fetchData}
                                className="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors"
                                title="Atualizar"
                            >
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>
                        </div>

                        {loading ? (
                            <div className="flex items-center justify-center py-16">
                                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600" />
                            </div>
                        ) : jobs.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-16 text-gray-400">
                                <svg className="w-12 h-12 mb-3 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                                </svg>
                                <p className="font-medium text-sm">Nenhum backup realizado ainda</p>
                                <p className="text-xs mt-1">Configure o bucket S3 e clique em "Fazer Backup Agora"</p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        <tr>
                                            <th className="px-5 py-3 text-left">Data</th>
                                            <th className="px-5 py-3 text-left">Status</th>
                                            <th className="px-5 py-3 text-right">Tamanho</th>
                                            <th className="px-5 py-3 text-right">Duração</th>
                                            <th className="px-5 py-3 text-left">Acionado por</th>
                                            <th className="px-5 py-3 text-right">Ação</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-50">
                                        {jobs.map(job => (
                                            <tr key={job.id} className={`hover:bg-gray-50/50 transition-colors ${isActive(job.status) ? 'bg-blue-50/30' : ''}`}>
                                                <td className="px-5 py-3.5">
                                                    <span className="font-medium text-gray-800 text-xs">{formatDate(job.created_at)}</span>
                                                    {job.s3_key && (
                                                        <p className="text-[10px] text-gray-400 font-mono mt-0.5 truncate max-w-[180px]" title={job.s3_key}>
                                                            {job.s3_key.split('/').pop()}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-5 py-3.5">
                                                    <StatusBadge status={job.status} />
                                                    {isActive(job.status) && (
                                                        <p className="text-[10px] text-blue-500 mt-1 flex items-center gap-1">
                                                            <span className="animate-pulse">●</span> Enviando para S3...
                                                        </p>
                                                    )}
                                                    {job.status === 'failed' && job.error_message && (
                                                        <p className="text-[10px] text-red-500 mt-1 max-w-[200px] truncate" title={job.error_message}>
                                                            {job.error_message}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-5 py-3.5 text-right">
                                                    <span className="font-medium text-gray-700">{job.file_size_human ?? '—'}</span>
                                                </td>
                                                <td className="px-5 py-3.5 text-right">
                                                    <span className="text-gray-600">{formatDuration(job.duration_seconds)}</span>
                                                </td>
                                                <td className="px-5 py-3.5">
                                                    {job.triggered_by ? (
                                                        <span className="inline-flex items-center gap-1 text-xs text-gray-600">
                                                            <svg className="w-3 h-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                            </svg>
                                                            {job.triggered_by.name}
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1 text-xs text-indigo-600">
                                                            <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                            Agendado
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-5 py-3.5 text-right">
                                                    {job.status === 'completed' ? (
                                                        <button
                                                            onClick={() => handleDownload(job.id)}
                                                            disabled={downloadingId === job.id}
                                                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded-lg transition-colors disabled:opacity-50"
                                                        >
                                                            {downloadingId === job.id ? (
                                                                <svg className="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                                </svg>
                                                            ) : (
                                                                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                                </svg>
                                                            )}
                                                            Download
                                                        </button>
                                                    ) : (
                                                        <span className="text-gray-300 text-xs">—</span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
