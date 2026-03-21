/**
 * StatsPanels
 * Grouped sections for Logins, Questions, Simulations, and Essays with period filtering.
 */
import React from 'react';
import { SectionHeader, PeriodTabs, DetailButton } from './CommonComponents';
import { Period } from './Types';

interface MetricPanelProps {
  title: string;
  period: Period;
  setPeriod: (p: Period) => void;
  metrics: { label: string; value: string | number; colorClass?: string }[];
  onDetail: () => void;
}

const MetricPanel: React.FC<MetricPanelProps> = ({ title, period, setPeriod, metrics, onDetail }) => (
  <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
    <SectionHeader title={title}>
      <PeriodTabs value={period} onChange={setPeriod} />
    </SectionHeader>
    <div className={`grid grid-cols-${Math.min(metrics.length, 4)} gap-4 mb-4`}>
      {metrics.map(s => (
        <div key={s.label} className="text-center p-4 rounded-xl bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600">
          <p className={`text-3xl font-bold ${s.colorClass ?? 'text-slate-900 dark:text-slate-100'}`}>{s.value}</p>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">{s.label}</p>
        </div>
      ))}
    </div>
    <DetailButton onClick={onDetail} />
  </div>
);

interface StatsPanelsProps {
  loginPeriod: Period;
  setLoginPeriod: (p: Period) => void;
  loginData: any;
  onOpenLogins: () => void;

  qPeriod: Period;
  setQPeriod: (p: Period) => void;
  qData: any;
  onOpenQuestions: () => void;

  simPeriod: Period;
  setSimPeriod: (p: Period) => void;
  simData: any;
  onOpenSimulations: () => void;

  essayPeriod: Period;
  setEssayPeriod: (p: Period) => void;
  essayData: any;
  onOpenEssays: () => void;
}

const StatsPanels: React.FC<StatsPanelsProps> = ({
  loginPeriod, setLoginPeriod, loginData, onOpenLogins,
  qPeriod, setQPeriod, qData, onOpenQuestions,
  simPeriod, setSimPeriod, simData, onOpenSimulations,
  essayPeriod, setEssayPeriod, essayData, onOpenEssays
}) => {
  return (
    <div className="space-y-8">
      {/* 3. Logins */}
      <MetricPanel
        title="3 · Logins"
        period={loginPeriod}
        setPeriod={setLoginPeriod}
        onDetail={onOpenLogins}
        metrics={[
          { label: 'Hoje', value: loginData?.counts?.today ?? 0 },
          { label: '7 Dias', value: loginData?.counts?.week ?? 0 },
          { label: '30 Dias', value: loginData?.counts?.month ?? 0 },
        ]}
      />

      {/* 4. Questions */}
      <MetricPanel
        title="4 · Uso de Questões"
        period={qPeriod}
        setPeriod={setQPeriod}
        onDetail={onOpenQuestions}
        metrics={[
          { label: 'Total', value: qData?.total ?? 0 },
          { label: 'Acertos', value: qData?.correct ?? 0, colorClass: 'text-emerald-600 dark:text-emerald-400' },
          { label: 'Erros', value: qData?.wrong ?? 0, colorClass: 'text-rose-600 dark:text-rose-400' },
          { label: 'Taxa de Acerto', value: `${qData?.accuracy ?? 0}%`, colorClass: 'text-blue-600 dark:text-blue-400' },
        ]}
      />

      {/* 5. Simulations */}
      <MetricPanel
        title="5 · Simulados"
        period={simPeriod}
        setPeriod={setSimPeriod}
        onDetail={onOpenSimulations}
        metrics={[
          { label: 'Criados', value: simData?.created ?? 0 },
          { label: 'Iniciados', value: simData?.started ?? 0 },
          { label: 'Finalizados', value: simData?.finished ?? 0 },
        ]}
      />

      {/* 6. Essays */}
      <MetricPanel
        title="6 · Redações"
        period={essayPeriod}
        setPeriod={setEssayPeriod}
        onDetail={onOpenEssays}
        metrics={[
          { label: 'Criadas', value: essayData?.created ?? 0 },
          { label: 'Enviadas', value: essayData?.submitted ?? 0 },
          { label: 'Avaliadas', value: essayData?.evaluated ?? 0 },
        ]}
      />
    </div>
  );
};

export default StatsPanels;
