/**
 * OnlineStatusPanel
 * Compact card showing real-time online count and detail trigger.
 */
import React from 'react';
import { SectionHeader, DetailButton } from './CommonComponents';
import { PlatformOverview } from './Types';

interface OnlineStatusPanelProps {
  overview: PlatformOverview | undefined;
  onOpenOnline: () => void;
}

const OnlineStatusPanel: React.FC<OnlineStatusPanelProps> = ({ overview, onOpenOnline }) => {
  return (
    <section>
      <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
        <SectionHeader title="2 · Usuários Online">
          {overview !== undefined && (
            <div className="flex items-center gap-3">
              <div className="flex items-center gap-1.5">
                <span className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse inline-block"></span>
                <span className="text-sm font-semibold text-emerald-600 dark:text-emerald-400">{overview.online_now} online</span>
              </div>
              <DetailButton onClick={onOpenOnline} />
            </div>
          )}
        </SectionHeader>
        <p className="text-sm text-slate-500 dark:text-slate-400">
          Usuários considerados online quando o heartbeat chegou nos últimos 3 minutos.
        </p>
      </div>
    </section>
  );
};

export default OnlineStatusPanel;
