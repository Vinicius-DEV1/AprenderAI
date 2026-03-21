import React from 'react';
import { 
    Activity, 
    Layers, 
    CheckCircle2, 
    AlertCircle, 
    Box, 
    Database, 
    Hash, 
    Clock 
} from 'lucide-react';
import StatCard from './StatCard';
import { DashboardStats } from './Types';

interface MonitoringCenterProps {
    stats: DashboardStats;
}

const MonitoringCenter: React.FC<MonitoringCenterProps> = ({ stats }) => {
    return (
        <div className="space-y-6">
            {/* MONITORING CENTER HEADER */}
            <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-2">
                <h2 className="text-sm font-black text-slate-500 uppercase tracking-widest flex items-center gap-2">
                    <Activity className="w-4 h-4" /> Centro de Monitoramento
                </h2>
                <div className="flex items-center gap-4 text-[10px] font-bold">
                    <span className="flex items-center gap-1.5 text-indigo-500 bg-indigo-50 dark:bg-indigo-900/40 px-2 py-1 rounded-lg border border-indigo-100 dark:border-indigo-800">
                        <Layers className="w-3 h-3" /> Pipeline: {stats.config.pipeline_version || 'v7'}
                    </span>
                </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                {/* INFRA GROUP */}
                <StatCard
                    title="Saúde do Qdrant"
                    value={stats.qdrant.status.toUpperCase()}
                    subtitle={`${stats.qdrant.questions_points.toLocaleString()} pontos vetoriais`}
                    icon={stats.qdrant.status === 'online' ? CheckCircle2 : AlertCircle}
                    colorClass={stats.qdrant.status === 'online' ? "bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30" : "bg-red-100 text-red-600 dark:bg-red-900/30"}
                    helpText="O Qdrant é o nosso banco de dados vetorial. Ele armazena o 'conhecimento' matemático das questões para permitir buscas por contexto."
                />
                <StatCard
                    title="Questões no Xavier"
                    value={`${stats.overview.mysql_indexed_questions.toLocaleString()} / ${stats.overview.mysql_published_questions.toLocaleString()}`}
                    subtitle={`${stats.overview.mysql_published_questions - stats.overview.mysql_indexed_questions} questões pendentes`}
                    icon={Box}
                    colorClass="bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400"
                    helpText="Representa o percentual da sua base de questões que já está 'consciente' para a IA. Questões não indexadas só aparecem por busca de texto simples."
                />
                <StatCard
                    title="Matérias/Disciplinas"
                    value={`${stats.overview.mysql_indexed_subjects} / ${stats.overview.mysql_total_subjects}`}
                    subtitle={`${stats.overview.mysql_total_subjects - stats.overview.mysql_indexed_subjects} pendentes`}
                    icon={Database}
                    colorClass="bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400"
                    helpText="Matérias/Disciplinas detectadas no Qdrant atuam como filtros rígidos na busca semântica."
                />
                <StatCard
                    title="Assuntos"
                    value={`${stats.overview.mysql_indexed_topics} / ${stats.overview.mysql_total_topics}`}
                    subtitle={`${stats.overview.mysql_total_topics - stats.overview.mysql_indexed_topics} pendentes`}
                    icon={Hash}
                    colorClass="bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400"
                    helpText="Assuntos (Topics) vinculados às questões. Permitem maior precisão no re-ranking."
                />
                <StatCard
                    title="Fila de Embedding"
                    value={stats.jobs.pending}
                    subtitle={`${stats.jobs.failed} falhas | ${stats.jobs.waiting_list?.length || 0} em espera`}
                    icon={stats.jobs.failed > 0 ? AlertCircle : Clock}
                    colorClass={stats.jobs.failed > 0 ? "bg-amber-100 text-amber-600 dark:bg-amber-900/30" : "bg-blue-100 text-blue-600 dark:bg-blue-900/30"}
                    helpText="Mostra quantos processos de 'transformar texto em vetor' estão aguardando no servidor. Falhas geralmente ocorrem por limite de cota da IA."
                />
            </div>
        </div>
    );
};

export default MonitoringCenter;
