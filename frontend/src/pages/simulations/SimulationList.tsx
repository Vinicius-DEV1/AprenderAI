import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useSimulations } from '../../hooks/useSimulations';
import QuotaLimitModal from '../../components/QuotaLimitModal';

export default function SimulationList() {
    const [page, setPage] = useState(1);
    const [isQuotaModalOpen, setIsQuotaModalOpen] = useState(false);
    const { data, isLoading } = useSimulations(page);

    if (isLoading) {
        return <div className="flex justify-center py-20"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div></div>;
    }

    const simulations = data?.data || [];
    const meta = data?.meta || {};
    const canCreate = data?.simulationLimit?.can_create ?? true;
    const used = data?.simulationLimit?.remaining !== undefined && data?.simulationLimit?.total !== undefined
        ? data.simulationLimit.total - data.simulationLimit.remaining
        : 0;
    const limit = data?.simulationLimit?.total ?? 0;

    const handlePageChange = (newPage: number) => {
        if (newPage >= 1 && newPage <= (meta.last_page || 1)) {
            setPage(newPage);
        }
    };

    const getStatusText = (status: string) => {
        const map: Record<string, string> = {
            'pending': 'Pendente',
            'in_progress': 'Em Andamento',
            'finished': 'Finalizada',
            'corrected': 'Corrigida'
        };
        return map[status] || 'Desconhecido';
    };

    const formatTime = (seconds: number) => {
        if (!seconds) return '-';
        return new Date(seconds * 1000).toISOString().substring(11, 19);
    };

    return (
        <>
            <style>{`
        .simulations-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .btn-new {
            padding: 12px 24px;
            background: #2563EB;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-block;
        }

        .btn-new:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .simulations-list {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .simulation-row {
            padding: 20px;
            border-bottom: 1px solid #f1f5f9;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 16px;
            align-items: center;
        }

        .simulation-row:last-child {
            border-bottom: none;
        }

        .simulation-title h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .simulation-title p {
            font-size: 13px;
            color: #64748b;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-in_progress {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-finished {
            background: #fde68a;
            color: #78350f;
        }

        .status-corrected {
            background: #d1fae5;
            color: #065f46;
        }

        .score-display {
            font-size: 24px;
            font-weight: 700;
            color: #2563EB;
        }

        .btn-view {
            padding: 8px 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #334155;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-view:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }

        /* DARK MODE */
        :root.dark .simulations-list {
            background: #1e293b;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        :root.dark .simulation-row {
            border-bottom-color: rgba(255, 255, 255, 0.08);
        }

        :root.dark .simulation-title h4,
        :root.dark .simulations-header h1 {
            color: #f1f5f9;
        }

        :root.dark .simulation-title p {
            color: #94a3b8;
        }

        :root.dark .btn-view {
            background: #0f172a;
            border-color: rgba(255, 255, 255, 0.1);
            color: #e2e8f0;
        }

        :root.dark .btn-view:hover {
            background: #334155;
            border-color: rgba(255, 255, 255, 0.2);
        }

        :root.dark .score-display {
            color: #60a5fa;
        }

        :root.dark .empty-state {
            color: #64748b;
        }

        :root.dark .status-badge.status-pending {
            background: rgba(254, 243, 199, 0.1);
            color: #fde68a;
        }

        :root.dark .status-badge.status-in_progress {
            background: rgba(219, 234, 254, 0.1);
            color: #93c5fd;
        }

        :root.dark .status-badge.status-finished {
            background: rgba(253, 230, 138, 0.1);
            color: #fcd34d;
        }

        :root.dark .status-badge.status-corrected {
            background: rgba(209, 250, 229, 0.1);
            color: #6ee7b7;
        }
        .usage-card {
            background: white;
            border-radius: 12px;
            padding: 16px 24px;
            margin-bottom: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .usage-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
            color: #374151;
        }

        .usage-subtitle {
            font-size: 13px;
            color: #6b7280;
        }

        .usage-link {
            font-size: 13px;
            color: #2563eb;
            font-weight: 600;
            text-decoration: none;
        }

        :root.dark .usage-card {
            background: #1e293b;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        :root.dark .usage-title {
            color: #f3f4f6;
        }

        :root.dark .usage-subtitle {
            color: #9ca3af;
        }
      `}</style>

            <div className="simulations-header">
                <h1 style={{ fontSize: '28px', fontWeight: 700 }}>Minhas Provas</h1>

                {canCreate ? (
                    <Link to="/simulados/configurar" className="btn-new">+ Nova Prova</Link>
                ) : (
                    <button type="button"
                        onClick={() => setIsQuotaModalOpen(true)}
                        className="btn-new" style={{ background: '#64748b', cursor: 'not-allowed' }}>
                        Limite Atingido
                    </button>
                )}
            </div>

            <div className="usage-card">
                <div>
                    <p className="usage-title">Uso Mensal de Provas</p>
                    <p className="usage-subtitle">
                        Você criou <strong>{used}</strong> de <strong>{limit === 9999 ? 'ilimitadas' : limit}</strong> provas disponíveis neste ciclo.
                    </p>
                </div>
                {!canCreate && (
                    <Link to="/planos" className="usage-link">
                        Ver Planos &rarr;
                    </Link>
                )}
            </div>

            <div className="simulations-list">
                {simulations.length > 0 ? (
                    simulations.map((simulation: any) => (
                        <div key={simulation.id} className="simulation-row flex flex-col md:grid">
                            <div className="simulation-title">
                                <h4>{String(simulation.type).charAt(0).toUpperCase() + String(simulation.type).slice(1)} - {simulation.questions_count ?? 0} questões</h4>
                                <p>Criada em {simulation.created_at || simulation.formatted_date}</p>
                            </div>

                            <div>
                                <span className={`status-badge status-${simulation.status}`}>
                                    {getStatusText(simulation.status)}
                                </span>
                            </div>

                            <div>
                                {simulation.time_elapsed ? formatTime(simulation.time_elapsed) : '-'}
                            </div>

                            <div>
                                {simulation.score !== null ? (
                                    <span className="score-display">{Number(simulation.score).toLocaleString('pt-BR', { minimumFractionDigits: 1 })}%</span>
                                ) : (
                                    <span style={{ color: '#94a3b8' }}>-</span>
                                )}
                            </div>

                            <div>
                                {simulation.status === 'in_progress' ? (
                                    <Link to={`/simulados/${simulation.id}`} className="btn-view w-full md:w-auto text-center block md:inline-block">Continuar</Link>
                                ) : (simulation.status === 'corrected' || simulation.status === 'finished') ? (
                                    <Link to={`/simulados/${simulation.id}/result`} className="btn-view w-full md:w-auto text-center block md:inline-block">Ver Resultado</Link>
                                ) : (
                                    <Link to={`/simulados/${simulation.id}`} className="btn-view w-full md:w-auto text-center block md:inline-block">Ver</Link>
                                )}
                            </div>
                        </div>
                    ))
                ) : (
                    <div className="empty-state">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style={{ width: '64px', height: '64px', margin: '0 auto 16px', opacity: 0.3 }}>
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p>Você ainda não criou nenhuma prova.</p>
                        {canCreate ? (
                            <Link to="/simulados/configurar" className="btn-new" style={{ marginTop: '16px' }}>Criar Primeira Prova</Link>
                        ) : (
                            <button type="button" onClick={() => setIsQuotaModalOpen(true)} className="btn-new" style={{ marginTop: '16px', background: '#64748b', cursor: 'not-allowed' }}>
                                Ver Planos
                            </button>
                        )}
                    </div>
                )}

                {meta.last_page > 1 && (
                    <div style={{ marginTop: '24px' }} className="flex justify-center gap-2">
                        <button
                            onClick={() => handlePageChange(page - 1)}
                            disabled={page === 1}
                            className="px-3 py-1 border rounded disabled:opacity-50"
                        >
                            Anterior
                        </button>
                        <span className="px-3 py-1">Página {page} de {meta.last_page}</span>
                        <button
                            onClick={() => handlePageChange(page + 1)}
                            disabled={page === meta.last_page}
                            className="px-3 py-1 border rounded disabled:opacity-50"
                        >
                            Próxima
                        </button>
                    </div>
                )}
            </div>

            <QuotaLimitModal
                isOpen={isQuotaModalOpen}
                onClose={() => setIsQuotaModalOpen(false)}
                resource="Provas"
                used={used}
                limit={limit}
                upgradeRoute="/planos"
            />
        </>
    );
}
