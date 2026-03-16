import { useState, useEffect, useCallback } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
  getPlatformOverview,
  getPlatformOnline,
  getPlatformLogins,
  getPlatformQuestions,
  getPlatformSimulations,
  getPlatformEssays,
  getPlatformActivity,
  getPlatformUserDetail,
} from '../../api/platformMonitor';

// ─── Helpers ──────────────────────────────────────────────────────────────────

type Period = 'today' | 'week' | 'month';

function fmtDate(iso: string | null | undefined): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('pt-BR', {
    day: '2-digit', month: '2-digit', year: '2-digit',
    hour: '2-digit', minute: '2-digit',
  });
}

function fmtMinutes(mins: number | null | undefined): string {
  if (mins === null || mins === undefined) return '—';
  if (mins < 60) return `${mins}m`;
  return `${Math.floor(mins / 60)}h ${mins % 60}m`;
}

function eventLabel(type: string): string {
  const labels: Record<string, string> = {
    'session.started': '🔐 Login',
    'session.ended': '🚪 Logout',
    'question.answered': '✅ Questão respondida',
    'question.viewed': '👁 Questão visualizada',
    'simulation.created': '📋 Simulado criado',
    'simulation.started': '▶ Simulado iniciado',
    'simulation.finished': '🏁 Simulado finalizado',
    'essay.created': '✏️ Redação criada',
    'essay.submitted': '📤 Redação enviada',
    'essay.evaluated': '🎯 Redação avaliada',
    'page.visited': '📄 Página visitada',
  };
  return labels[type] ?? type;
}

// ─── Sub-components ───────────────────────────────────────────────────────────

function StatCard({
  label, value, sub, color = 'blue', icon,
}: {
  label: string; value: string | number; sub?: string; color?: string; icon: React.ReactNode;
}) {
  const colors: Record<string, string> = {
    blue: 'from-blue-500 to-blue-600',
    green: 'from-emerald-500 to-emerald-600',
    violet: 'from-violet-500 to-violet-600',
    amber: 'from-amber-500 to-amber-600',
    rose: 'from-rose-500 to-rose-600',
    cyan: 'from-cyan-500 to-cyan-600',
    indigo: 'from-indigo-500 to-indigo-600',
    teal: 'from-teal-500 to-teal-600',
  };
  return (
    <div className="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-md transition-shadow">
      <div className="flex items-center justify-between mb-3">
        <div className={`w-10 h-10 rounded-xl bg-gradient-to-br ${colors[color] ?? colors.blue} flex items-center justify-center text-white text-lg shadow-sm`}>
          {icon}
        </div>
      </div>
      <p className="text-3xl font-bold text-slate-900 dark:text-slate-100">{value}</p>
      <p className="text-sm font-medium text-slate-600 dark:text-slate-400 mt-1">{label}</p>
      {sub && <p className="text-xs text-slate-400 dark:text-slate-500 mt-0.5">{sub}</p>}
    </div>
  );
}

function SectionHeader({ title, children }: { title: string; children?: React.ReactNode }) {
  return (
    <div className="flex items-center justify-between mb-4">
      <h2 className="text-lg font-bold text-slate-800 dark:text-slate-100">{title}</h2>
      {children}
    </div>
  );
}

function PeriodTabs({ value, onChange }: { value: Period; onChange: (p: Period) => void }) {
  const tabs: { key: Period; label: string }[] = [
    { key: 'today', label: 'Hoje' },
    { key: 'week', label: '7 Dias' },
    { key: 'month', label: '30 Dias' },
  ];
  return (
    <div className="flex bg-slate-100 dark:bg-slate-700 rounded-lg p-1 gap-1">
      {tabs.map(t => (
        <button
          key={t.key}
          onClick={() => onChange(t.key)}
          className={`px-3 py-1 rounded-md text-xs font-semibold transition-all ${value === t.key
            ? 'bg-white dark:bg-slate-600 text-slate-900 dark:text-slate-100 shadow-sm'
            : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'
            }`}
        >
          {t.label}
        </button>
      ))}
    </div>
  );
}

function Avatar({ name, avatarUrl }: { name: string; avatarUrl?: string | null }) {
  if (avatarUrl) {
    return <img src={avatarUrl} className="w-8 h-8 rounded-full object-cover" alt={name} />;
  }
  return (
    <div className="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300 flex items-center justify-center text-sm font-bold flex-shrink-0">
      {name.charAt(0).toUpperCase()}
    </div>
  );
}

function Badge({ children, color = 'slate' }: { children: React.ReactNode; color?: string }) {
  const colors: Record<string, string> = {
    slate: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    green: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    red: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
  };
  return (
    <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${colors[color] ?? colors.slate}`}>
      {children}
    </span>
  );
}

// ─── User Detail Modal ─────────────────────────────────────────────────────────

function UserDetailModal({ userId, onClose }: { userId: number; onClose: () => void }) {
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
          {/* Header */}
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

          {/* Stats Grid */}
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

          {/* Timeline */}
          <div>
            <p className="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">Atividade Recente</p>
            <div className="space-y-2 max-h-64 overflow-y-auto pr-1">
              {data.timeline.length === 0 && (
                <p className="text-sm text-slate-400 dark:text-slate-500">Nenhuma atividade registrada ainda.</p>
              )}
              {data.timeline.map((ev, i) => (
                <div key={i} className="flex items-start gap-3 px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-700/50">
                  <div className="mt-0.5 w-2 h-2 rounded-full bg-indigo-400 dark:bg-indigo-500 flex-shrink-0 mt-2"></div>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm text-slate-700 dark:text-slate-200">{eventLabel(ev.event_type)}</p>
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

// ─── Generic Modal Wrapper ─────────────────────────────────────────────────────

function ModalWrapper({ title, onClose, children, wide = false }: {
  title: string; onClose: () => void; children: React.ReactNode; wide?: boolean;
}) {
  useEffect(() => {
    const handler = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [onClose]);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
      <div className={`bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full overflow-hidden flex flex-col max-h-[90vh] ${wide ? 'max-w-3xl' : 'max-w-2xl'}`}>
        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
          <h3 className="text-lg font-bold text-slate-900 dark:text-slate-100">{title}</h3>
          <button onClick={onClose} className="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 dark:text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
            ✕
          </button>
        </div>
        <div className="overflow-y-auto p-6 flex-1">
          {children}
        </div>
      </div>
    </div>
  );
}

function Spinner() {
  return <div className="w-6 h-6 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin" />;
}

function DetailButton({ onClick }: { onClick: () => void }) {
  return (
    <button
      onClick={onClick}
      className="px-3 py-1.5 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 rounded-lg transition-colors border border-indigo-200 dark:border-indigo-800"
    >
      Detalhar →
    </button>
  );
}

// ─── Online Users Modal ────────────────────────────────────────────────────────

function OnlineModal({ onClose, onUser }: { onClose: () => void; onUser: (id: number) => void }) {
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
          {data?.users.map(u => (
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

// ─── Logins Modal ─────────────────────────────────────────────────────────────

function LoginsModal({ period, onClose, onUser }: { period: Period; onClose: () => void; onUser: (id: number) => void }) {
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
          {data?.list.map((l, i) => (
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

// ─── Questions Modal ──────────────────────────────────────────────────────────

function QuestionsModal({ period, onClose, onUser }: { period: Period; onClose: () => void; onUser: (id: number) => void }) {
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
            {data?.per_user.map((u, i) => (
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

// ─── Simulations Modal ────────────────────────────────────────────────────────

function SimulationsModal({ period, onClose, onUser }: { period: Period; onClose: () => void; onUser: (id: number) => void }) {
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
            {data?.list.map((s, i) => (
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

// ─── Essays Modal ─────────────────────────────────────────────────────────────

function EssaysModal({ period, onClose, onUser }: { period: Period; onClose: () => void; onUser: (id: number) => void }) {
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
            {data?.list.map((e, i) => (
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

// ─── Main Page ────────────────────────────────────────────────────────────────

type Modal =
  | { type: 'online' }
  | { type: 'logins'; period: Period }
  | { type: 'questions'; period: Period }
  | { type: 'simulations'; period: Period }
  | { type: 'essays'; period: Period }
  | { type: 'user'; id: number };

export default function PlatformMonitor() {
  const [loginPeriod, setLoginPeriod] = useState<Period>('today');
  const [qPeriod, setQPeriod] = useState<Period>('today');
  const [simPeriod, setSimPeriod] = useState<Period>('today');
  const [essayPeriod, setEssayPeriod] = useState<Period>('today');
  const [modal, setModal] = useState<Modal | null>(null);

  const openUser = useCallback((id: number) => setModal({ type: 'user', id }), []);
  const closeModal = useCallback(() => setModal(null), []);

  // Overview
  const { data: overview, isLoading: ovLoading } = useQuery({
    queryKey: ['platform-overview'],
    queryFn: getPlatformOverview,
    refetchInterval: 30_000,
    staleTime: 20_000,
  });

  // Login counts
  const { data: loginData } = useQuery({
    queryKey: ['platform-logins', loginPeriod],
    queryFn: () => getPlatformLogins(loginPeriod),
    staleTime: 60_000,
  });

  // Question counts
  const { data: qData } = useQuery({
    queryKey: ['platform-questions', qPeriod],
    queryFn: () => getPlatformQuestions(qPeriod),
    staleTime: 60_000,
  });

  // Sim counts
  const { data: simData } = useQuery({
    queryKey: ['platform-simulations', simPeriod],
    queryFn: () => getPlatformSimulations(simPeriod),
    staleTime: 60_000,
  });

  // Essay counts
  const { data: essayData } = useQuery({
    queryKey: ['platform-essays', essayPeriod],
    queryFn: () => getPlatformEssays(essayPeriod),
    staleTime: 60_000,
  });

  // Activity feed
  const { data: activityData, isLoading: actLoading } = useQuery({
    queryKey: ['platform-activity'],
    queryFn: getPlatformActivity,
    refetchInterval: 30_000,
    staleTime: 20_000,
  });

  return (
    <div className="py-6 space-y-8">
      {/* Page Title */}
      <div>
        <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100">Monitoramento da Plataforma</h1>
        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Rastreamento interno e nativo — sessões, eventos e métricas de uso em tempo real
        </p>
      </div>

      {/* ── 1. Visão Geral ── */}
      <section>
        <SectionHeader title="1 · Visão Geral" />
        {ovLoading ? (
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

      {/* ── 2. Usuários Online ── */}
      <section>
        <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
          <SectionHeader title="2 · Usuários Online">
            {overview !== undefined && (
              <div className="flex items-center gap-3">
                <div className="flex items-center gap-1.5">
                  <span className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse inline-block"></span>
                  <span className="text-sm font-semibold text-emerald-600 dark:text-emerald-400">{overview.online_now} online</span>
                </div>
                <DetailButton onClick={() => setModal({ type: 'online' })} />
              </div>
            )}
          </SectionHeader>
          <p className="text-sm text-slate-500 dark:text-slate-400">
            Usuários considerados online quando o heartbeat chegou nos últimos 3 minutos.
          </p>
        </div>
      </section>

      {/* ── 3. Logins ── */}
      <section>
        <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
          <SectionHeader title="3 · Logins">
            <PeriodTabs value={loginPeriod} onChange={setLoginPeriod} />
          </SectionHeader>
          <div className="grid grid-cols-3 gap-4 mb-4">
            {([
              { label: 'Hoje', value: loginData?.counts?.today ?? 0 },
              { label: '7 Dias', value: loginData?.counts?.week ?? 0 },
              { label: '30 Dias', value: loginData?.counts?.month ?? 0 },
            ] as const).map(s => (
              <div key={s.label} className="text-center p-4 rounded-xl bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600">
                <p className="text-3xl font-bold text-slate-900 dark:text-slate-100">{s.value}</p>
                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">{s.label}</p>
              </div>
            ))}
          </div>
          <DetailButton onClick={() => setModal({ type: 'logins', period: loginPeriod })} />
        </div>
      </section>

      {/* ── 4. Questões ── */}
      <section>
        <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
          <SectionHeader title="4 · Uso de Questões">
            <PeriodTabs value={qPeriod} onChange={setQPeriod} />
          </SectionHeader>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
            {[
              { l: 'Total', v: qData?.total ?? 0, c: 'text-slate-900 dark:text-slate-100' },
              { l: 'Acertos', v: qData?.correct ?? 0, c: 'text-emerald-600 dark:text-emerald-400' },
              { l: 'Erros', v: qData?.wrong ?? 0, c: 'text-rose-600 dark:text-rose-400' },
              { l: 'Taxa de Acerto', v: `${qData?.accuracy ?? 0}%`, c: 'text-blue-600 dark:text-blue-400' },
            ].map(s => (
              <div key={s.l} className="text-center p-4 rounded-xl bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600">
                <p className={`text-3xl font-bold ${s.c}`}>{s.v}</p>
                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">{s.l}</p>
              </div>
            ))}
          </div>
          <DetailButton onClick={() => setModal({ type: 'questions', period: qPeriod })} />
        </div>
      </section>

      {/* ── 5. Simulados ── */}
      <section>
        <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
          <SectionHeader title="5 · Simulados">
            <PeriodTabs value={simPeriod} onChange={setSimPeriod} />
          </SectionHeader>
          <div className="grid grid-cols-3 gap-4 mb-4">
            {[
              { l: 'Criados', v: simData?.created ?? 0 },
              { l: 'Iniciados', v: simData?.started ?? 0 },
              { l: 'Finalizados', v: simData?.finished ?? 0 },
            ].map(s => (
              <div key={s.l} className="text-center p-4 rounded-xl bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600">
                <p className="text-3xl font-bold text-slate-900 dark:text-slate-100">{s.v}</p>
                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">{s.l}</p>
              </div>
            ))}
          </div>
          <DetailButton onClick={() => setModal({ type: 'simulations', period: simPeriod })} />
        </div>
      </section>

      {/* ── 6. Redações ── */}
      <section>
        <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
          <SectionHeader title="6 · Redações">
            <PeriodTabs value={essayPeriod} onChange={setEssayPeriod} />
          </SectionHeader>
          <div className="grid grid-cols-3 gap-4 mb-4">
            {[
              { l: 'Criadas', v: essayData?.created ?? 0 },
              { l: 'Enviadas', v: essayData?.submitted ?? 0 },
              { l: 'Avaliadas', v: essayData?.evaluated ?? 0 },
            ].map(s => (
              <div key={s.l} className="text-center p-4 rounded-xl bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600">
                <p className="text-3xl font-bold text-slate-900 dark:text-slate-100">{s.v}</p>
                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">{s.l}</p>
              </div>
            ))}
          </div>
          <DetailButton onClick={() => setModal({ type: 'essays', period: essayPeriod })} />
        </div>
      </section>

      {/* ── 7. Atividade Recente ── */}
      <section>
        <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
          <SectionHeader title="7 · Atividade Recente">
            <span className="text-xs text-slate-400 dark:text-slate-500 italic">Atualiza a cada 30s</span>
          </SectionHeader>
          {actLoading ? (
            <div className="flex justify-center py-8"><Spinner /></div>
          ) : (
            <div className="divide-y divide-slate-100 dark:divide-slate-700 -mx-5 px-5">
              {(!activityData?.feed?.length) && (
                <p className="py-8 text-center text-slate-400 dark:text-slate-500">Nenhuma atividade registrada ainda.</p>
              )}
              {activityData?.feed.map((ev, i) => (
                <div key={i} className="flex items-center gap-3 py-3">
                  <Avatar name={ev.name} avatarUrl={ev.avatar_url} />
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                      <button className="text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:underline" onClick={() => openUser(ev.user_id)}>
                        {ev.name}
                      </button>
                      <span className="text-sm text-slate-600 dark:text-slate-300">{eventLabel(ev.event_type)}</span>
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

      {/* ── Modals ── */}
      {modal?.type === 'online' && <OnlineModal onClose={closeModal} onUser={openUser} />}
      {modal?.type === 'logins' && <LoginsModal period={modal.period} onClose={closeModal} onUser={openUser} />}
      {modal?.type === 'questions' && <QuestionsModal period={modal.period} onClose={closeModal} onUser={openUser} />}
      {modal?.type === 'simulations' && <SimulationsModal period={modal.period} onClose={closeModal} onUser={openUser} />}
      {modal?.type === 'essays' && <EssaysModal period={modal.period} onClose={closeModal} onUser={openUser} />}
      {modal?.type === 'user' && <UserDetailModal userId={modal.id} onClose={closeModal} />}
    </div>
  );
}
