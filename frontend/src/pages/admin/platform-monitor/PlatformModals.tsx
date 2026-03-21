/**
 * PlatformModals
 * Drill-down modals for Online Users, Logins, Questions, Simulations, Essays, and User Details.
 */
import { useQuery } from '@tanstack/react-query';
import {
  getPlatformOnline,
  getPlatformLogins,
  getPlatformQuestions,
  getPlatformSimulations,
  getPlatformEssays,
  getPlatformUserDetail,
} from '../../../api/platformMonitor';
import { 
  ModalWrapper, 
  Spinner, 
  Avatar, 
  Badge 
} from './CommonComponents';
import { 
  Period, 
  fmtDate, 
  fmtMinutes, 
  getEventLabel 
} from './Types';

/** --- User Detail Modal --- */
export function UserDetailModal({ userId, onClose }: { userId: number; onClose: () => void }) {
  const { data, isLoading } = useQuery({
    queryKey: ['platform-user-detail', userId],
    queryFn: () => getPlatformUserDetail(userId),
    staleTime: 30_000,
  });

  return (
    <ModalWrapper title="Perfil de Uso do Usuário" onClose={onClose} wide>
      {isLoading ? (
        <div className="flex justify-center py-12"><Spinner /></div>
      ) : data ? (
        <div className="space-y-6">
          <div className="flex items-center gap-4 p-4 bg-indigo-50 dark:bg-indigo-950/40 rounded-xl border border-indigo-100 dark:border-indigo-900">
            <Avatar name={data.user.name} avatarUrl={data.user.avatar_url} />
            <div>
              <p className="font-bold text-slate-900 dark:text-slate-100">{data.user.name}</p>
              <p className="text-sm text-slate-500 dark:text-slate-400">{data.user.email}</p>
              <div className="flex gap-2 mt-1">
                {data.user.plan && <Badge color="blue">{data.user.plan}</Badge>}
                <Badge color="slate">{data.user.role}</Badge>
              </div>
            </div>
            <div className="ml-auto text-right text-xs text-slate-400 dark:text-slate-500 space-y-1">
              <p>Membro desde {fmtDate(data.user.created_at).split(',')[0]}</p>
              <p>Último login: {fmtDate(data.user.last_login)}</p>
            </div>
          </div>

          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            {[
              { l: 'Questões', v: data.stats.questions_total, color: 'blue' },
              { l: 'Acertos', v: `${data.stats.accuracy}%`, color: 'green' },
              { l: 'Simulados', v: data.stats.simulations_total, color: 'violet' },
              { l: 'Redações', v: data.stats.essays_total, color: 'amber' },
              { l: 'Tempo Total', v: fmtMinutes(data.stats.total_time_minutes), color: 'cyan' },
              { l: 'Dias Ativos (30d)', v: data.stats.active_days_30, color: 'teal' },
              { l: 'Simulados Finalizados', v: data.stats.simulations_finished, color: 'indigo' },
              { l: 'Redações Enviadas', v: data.stats.essays_submitted, color: 'rose' },
            ].map(s => (
              <div key={s.l} className="bg-white dark:bg-slate-800 rounded-xl p-3 border border-slate-200 dark:border-slate-700 text-center">
                <p className="text-xl font-bold text-slate-900 dark:text-slate-100">{s.v}</p>
                <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{s.l}</p>
              </div>
            ))}
          </div>

          <div>
            <p className="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">Atividade Recente</p>
            <div className="space-y-2 max-h-64 overflow-y-auto pr-1">
              {data.timeline.length === 0 && (
                <p className="text-sm text-slate-400 dark:text-slate-500">Nenhuma atividade registrada ainda.</p>
              )}
              {data.timeline.map((ev: any, i: number) => (
                <div key={i} className="flex items-start gap-3 px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-700/50">
                  <div className="mt-0.5 w-2 h-2 rounded-full bg-indigo-400 dark:bg-indigo-500 flex-shrink-0 mt-2"></div>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm text-slate-700 dark:text-slate-200">{getEventLabel(ev.event_type)}</p>
                    {ev.page && <p className="text-xs text-slate-400 dark:text-slate-500 truncate">{ev.page}</p>}
                  </div>
                  <p className="text-xs text-slate-400 dark:text-slate-500 flex-shrink-0">{fmtDate(ev.created_at)}</p>
                </div>
              ))}
            </div>
          </div>
        </div>
      ) : null}
    </ModalWrapper>
  );
}

/** --- Online Users Modal --- */
export function OnlineModal({ onClose, onUser }: { onClose: () => void; onUser: (id: number) => void }) {
  const { data, isLoading } = useQuery({
    queryKey: ['platform-online'],
    queryFn: getPlatformOnline,
    refetchInterval: 15_000,
    staleTime: 10_000,
  });

  return (
    <ModalWrapper title={`Usuários Online Agora (${data?.count ?? 0})`} onClose={onClose}>
      {isLoading ? <div className="flex justify-center py-8"><Spinner /></div> : (
        <div className="divide-y divide-slate-100 dark:divide-slate-700">
          {(!data?.users?.length) && (
            <p className="py-8 text-center text-slate-400 dark:text-slate-500">Nenhum usuário online no momento.</p>
          )}
          {data?.users.map((u: any) => (
            <div key={u.user_id} className="flex items-center gap-3 py-3">
              <div className="relative">
                <Avatar name={u.name} avatarUrl={u.avatar_url} />
                <span className="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-emerald-500 rounded-full border-2 border-white dark:border-slate-800" />
              </div>
              <div className="flex-1 min-w-0">
                <button className="text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:underline truncate text-left" onClick={() => onUser(u.user_id)}>
                  {u.name}
                </button>
                <p className="text-xs text-slate-400 dark:text-slate-500 truncate">{u.current_page ?? '—'}</p>
              </div>
              <div className="text-right text-xs text-slate-400 dark:text-slate-500 flex-shrink-0 space-y-0.5">
                <p>Online: {fmtMinutes(u.online_minutes)}</p>
                <p>Entrou: {u.login_at ? fmtDate(u.login_at) : '—'}</p>
              </div>
            </div>
          ))}
        </div>
      )}
    </ModalWrapper>
  );
}

/** --- Logins Modal --- */
export function LoginsModal({ period, onClose, onUser }: { period: Period; onClose: () => void; onUser: (id: number) => void }) {
  const { data, isLoading } = useQuery({
    queryKey: ['platform-logins-detail', period],
    queryFn: () => getPlatformLogins(period),
    staleTime: 30_000,
  });

  return (
    <ModalWrapper title={`Logins — ${data?.label ?? '...'}`} onClose={onClose}>
      {isLoading ? <div className="flex justify-center py-8"><Spinner /></div> : (
        <div className="space-y-2 max-h-[50vh] overflow-y-auto pr-1">
          {(!data?.list?.length) && (
            <p className="py-8 text-center text-slate-400 dark:text-slate-500">Nenhum login no período.</p>
          )}
          {data?.list.map((l: any, i: number) => (
            <div key={i} className="flex items-center gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-700/50">
              <Avatar name={l.name} avatarUrl={l.avatar_url} />
              <div className="flex-1 min-w-0">
                <button className="text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:underline text-left" onClick={() => onUser(l.user_id)}>
                  {l.name}
                </button>
                <p className="text-xs text-slate-400 dark:text-slate-500 truncate">{l.email}</p>
              </div>
              <div className="text-right text-xs text-slate-400 dark:text-slate-500 space-y-0.5 flex-shrink-0">
                <p>{fmtDate(l.login_at)}</p>
                <div className="flex gap-1 justify-end">
                  <Badge color={l.session_status === 'ativa' ? 'green' : 'slate'}>{l.session_status}</Badge>
                  {l.session_minutes !== null && <span>{fmtMinutes(l.session_minutes)}</span>}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </ModalWrapper>
  );
}

/** --- Questions Modal --- */
export function QuestionsModal({ period, onClose, onUser }: { period: Period; onClose: () => void; onUser: (id: number) => void }) {
  const { data, isLoading } = useQuery({
    queryKey: ['platform-questions-detail', period],
    queryFn: () => getPlatformQuestions(period),
    staleTime: 30_000,
  });

  return (
    <ModalWrapper title={`Questões — ${data?.label ?? '...'}`} onClose={onClose}>
      {isLoading ? <div className="flex justify-center py-8"><Spinner /></div> : (
        <>
          <div className="grid grid-cols-3 gap-3 mb-4">
            {[
              { l: 'Total', v: data?.total ?? 0, c: 'blue' },
              { l: 'Acertos', v: data?.correct ?? 0, c: 'green' },
              { l: 'Erros', v: data?.wrong ?? 0, c: 'red' },
            ].map(s => (
              <div key={s.l} className="text-center p-3 rounded-xl bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600">
                <p className="text-2xl font-bold text-slate-900 dark:text-slate-100">{s.v}</p>
                <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{s.l}</p>
              </div>
            ))}
          </div>
          <div className="flex gap-2 mb-4">
            <Badge color="green">Taxa de Acerto: {data?.accuracy ?? 0}%</Badge>
            <Badge color="red">Taxa de Erro: {data?.error_rate ?? 0}%</Badge>
          </div>
          <div className="space-y-2 max-h-[40vh] overflow-y-auto pr-1">
            {data?.per_user.map((u: any, i: number) => (
              <div key={i} className="flex items-center gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-700/50">
                <Avatar name={u.name} avatarUrl={u.avatar_url} />
                <div className="flex-1 min-w-0">
                  <button className="text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:underline text-left" onClick={() => onUser(u.user_id)}>
                    {u.name}
                  </button>
                  <p className="text-xs text-slate-400 dark:text-slate-500">{u.email}</p>
                </div>
                <div className="text-right text-xs space-y-0.5 flex-shrink-0">
                  <p className="text-slate-700 dark:text-slate-200 font-semibold">{u.total} questões</p>
                  <div className="flex gap-1 justify-end">
                    <Badge color="green">{u.correct}✓</Badge>
                    <Badge color="red">{u.wrong}✗</Badge>
                    <Badge color="blue">{u.accuracy}%</Badge>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </>
      )}
    </ModalWrapper>
  );
}

/** --- Simulations Modal --- */
export function SimulationsModal({ period, onClose, onUser }: { period: Period; onClose: () => void; onUser: (id: number) => void }) {
  const { data, isLoading } = useQuery({
    queryKey: ['platform-simulations-detail', period],
    queryFn: () => getPlatformSimulations(period),
    staleTime: 30_000,
  });

  return (
    <ModalWrapper title={`Simulados — ${data?.label ?? '...'}`} onClose={onClose}>
      {isLoading ? <div className="flex justify-center py-8"><Spinner /></div> : (
        <>
          <div className="grid grid-cols-3 gap-3 mb-4">
            {[
              { l: 'Criados', v: data?.created ?? 0 },
              { l: 'Iniciados', v: data?.started ?? 0 },
              { l: 'Finalizados', v: data?.finished ?? 0 },
            ].map(s => (
              <div key={s.l} className="text-center p-3 rounded-xl bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600">
                <p className="text-2xl font-bold text-slate-900 dark:text-slate-100">{s.v}</p>
                <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{s.l}</p>
              </div>
            ))}
          </div>
          <div className="space-y-2 max-h-[45vh] overflow-y-auto pr-1">
            {data?.list.map((s: any, i: number) => (
              <div key={i} className="flex items-center gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-700/50">
                <Avatar name={s.name} avatarUrl={s.avatar_url} />
                <div className="flex-1 min-w-0">
                  <button className="text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:underline text-left" onClick={() => onUser(s.user_id)}>
                    {s.name}
                  </button>
                  <p className="text-xs text-slate-400 dark:text-slate-500">{fmtDate(s.created_at)}</p>
                </div>
                <div className="text-right text-xs space-y-0.5 flex-shrink-0">
                  <Badge color={s.status === 'finished' ? 'green' : s.status === 'in_progress' ? 'amber' : 'slate'}>
                    {s.status}
                  </Badge>
                  {s.score !== null && <p className="text-slate-600 dark:text-slate-300 font-semibold">{s.score?.toFixed(1)} pts</p>}
                  {s.duration_minutes !== null && <p className="text-slate-400 dark:text-slate-500">{fmtMinutes(s.duration_minutes)}</p>}
                </div>
              </div>
            ))}
          </div>
        </>
      )}
    </ModalWrapper>
  );
}

/** --- Essays Modal --- */
export function EssaysModal({ period, onClose, onUser }: { period: Period; onClose: () => void; onUser: (id: number) => void }) {
  const { data, isLoading } = useQuery({
    queryKey: ['platform-essays-detail', period],
    queryFn: () => getPlatformEssays(period),
    staleTime: 30_000,
  });

  return (
    <ModalWrapper title={`Redações — ${data?.label ?? '...'}`} onClose={onClose}>
      {isLoading ? <div className="flex justify-center py-8"><Spinner /></div> : (
        <>
          <div className="grid grid-cols-3 gap-3 mb-4">
            {[
              { l: 'Criadas', v: data?.created ?? 0 },
              { l: 'Enviadas', v: data?.submitted ?? 0 },
              { l: 'Avaliadas', v: data?.evaluated ?? 0 },
            ].map(s => (
              <div key={s.l} className="text-center p-3 rounded-xl bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600">
                <p className="text-2xl font-bold text-slate-900 dark:text-slate-100">{s.v}</p>
                <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{s.l}</p>
              </div>
            ))}
          </div>
          <div className="space-y-2 max-h-[45vh] overflow-y-auto pr-1">
            {data?.list.map((e: any, i: number) => (
              <div key={i} className="flex items-center gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-700/50">
                <Avatar name={e.name} avatarUrl={e.avatar_url} />
                <div className="flex-1 min-w-0">
                  <button className="text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:underline text-left" onClick={() => onUser(e.user_id)}>
                    {e.name}
                  </button>
                  <p className="text-xs text-slate-400 dark:text-slate-500 truncate">{e.title}</p>
                </div>
                <div className="text-right text-xs space-y-0.5 flex-shrink-0">
                  <Badge color={e.status === 'evaluated' ? 'green' : e.status === 'submitted' ? 'amber' : 'slate'}>
                    {e.status}
                  </Badge>
                  {e.score !== null && <p className="text-slate-600 dark:text-slate-300 font-semibold">{e.score} pts</p>}
                  {e.time_spent_minutes !== null && <p className="text-slate-400 dark:text-slate-500">{fmtMinutes(e.time_spent_minutes)}</p>}
                </div>
              </div>
            ))}
          </div>
        </>
      )}
    </ModalWrapper>
  );
}
