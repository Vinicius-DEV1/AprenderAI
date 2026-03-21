/**
 * OverviewGrid
 * Top section displaying platform-wide summary metrics.
 */
import React from 'react';
import { SectionHeader, StatCard, Spinner } from './CommonComponents';
import { PlatformOverview } from './Types';

interface OverviewGridProps {
  overview: PlatformOverview | undefined;
  isLoading: boolean;
}

const OverviewGrid: React.FC<OverviewGridProps> = ({ overview, isLoading }) => {
  return (
    <section>
      <SectionHeader title="1 · Visão Geral" />
      {isLoading ? (
        <div className="flex justify-center py-10"><Spinner /></div>
      ) : (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <StatCard label="Usuários Hoje" value={overview?.users_today ?? 0} color="blue" icon="👥" sub="Logins únicos" />
          <StatCard label="Online Agora" value={overview?.online_now ?? 0} color="green" icon="🟢" sub="Últimos 3 min" />
          <StatCard label="Questões Hoje" value={overview?.questions_today ?? 0} color="violet" icon="✅" />
          <StatCard label="Simulados Hoje" value={overview?.simulations_today ?? 0} color="amber" icon="📋" />
          <StatCard label="Redações Hoje" value={overview?.essays_today ?? 0} color="rose" icon="✏️" />
          <StatCard label="Sessão Média" value={overview?.avg_session_minutes ? `${overview.avg_session_minutes}m` : '—'} color="cyan" icon="⏱" sub="Esta semana" />
          <StatCard label="Total de Usuários" value={overview?.total_users ?? 0} color="indigo" icon="👤" />
          <StatCard label="Sessões Hoje" value={overview?.sessions_today ?? 0} color="teal" icon="🔑" />
        </div>
      )}
    </section>
  );
};

export default OverviewGrid;
