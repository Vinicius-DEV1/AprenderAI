/**
 * ActivityFeed
 * Displays a live list of recent user interactions.
 */
import React from 'react';
import { SectionHeader, Avatar, Spinner } from './CommonComponents';
import { fmtDate, getEventLabel } from './Types';

interface ActivityFeedProps {
  activityData: any;
  isLoading: boolean;
  onOpenUser: (id: number) => void;
}

const ActivityFeed: React.FC<ActivityFeedProps> = ({ activityData, isLoading, onOpenUser }) => {
  return (
    <section>
      <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
        <SectionHeader title="7 · Atividade Recente">
          <span className="text-xs text-slate-400 dark:text-slate-500 italic">Atualiza a cada 30s</span>
        </SectionHeader>
        {isLoading ? (
          <div className="flex justify-center py-8"><Spinner /></div>
        ) : (
          <div className="divide-y divide-slate-100 dark:divide-slate-700 -mx-5 px-5">
            {(!activityData?.feed?.length) && (
              <p className="py-8 text-center text-slate-400 dark:text-slate-500">Nenhuma atividade registrada ainda.</p>
            )}
            {activityData?.feed.map((ev: any, i: number) => (
              <div key={i} className="flex items-center gap-3 py-3">
                <Avatar name={ev.name} avatarUrl={ev.avatar_url} />
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 flex-wrap">
                    <button 
                        className="text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:underline" 
                        onClick={() => onOpenUser(ev.user_id)}
                    >
                      {ev.name}
                    </button>
                    <span className="text-sm text-slate-600 dark:text-slate-300">{getEventLabel(ev.event_type)}</span>
                  </div>
                  {ev.page && <p className="text-xs text-slate-400 dark:text-slate-500 truncate">{ev.page}</p>}
                </div>
                <p className="text-xs text-slate-400 dark:text-slate-500 flex-shrink-0">{fmtDate(ev.created_at)}</p>
              </div>
            ))}
          </div>
        )}
      </div>
    </section>
  );
};

export default ActivityFeed;
