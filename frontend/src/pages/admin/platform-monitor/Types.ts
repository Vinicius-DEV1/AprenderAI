/**
 * Platform Monitor Types
 * Centralized interfaces for platform-wide metrics, activity logs, and modal states.
 */

export type Period = 'today' | 'week' | 'month';

/**
 * Platform Overview stats for the top-level cards.
 */
export interface PlatformOverview {
  users_today: number;
  online_now: number;
  questions_today: number;
  simulations_today: number;
  essays_today: number;
  avg_session_minutes: number | null;
  total_users: number;
  sessions_today: number;
}

/**
 * Real-time event in the activity feed.
 */
export interface ActivityEvent {
  user_id: number;
  name: string;
  avatar_url: string | null;
  event_type: string;
  page: string | null;
  created_at: string;
}

/**
 * Online user entry.
 */
export interface OnlineUser {
  user_id: number;
  name: string;
  avatar_url: string | null;
  current_page: string | null;
  online_minutes: number;
  login_at: string | null;
}

/**
 * Modal state discriminating union.
 */
export type PlatformModalState =
  | { type: 'online' }
  | { type: 'logins'; period: Period }
  | { type: 'questions'; period: Period }
  | { type: 'simulations'; period: Period }
  | { type: 'essays'; period: Period }
  | { type: 'user'; id: number };

/**
 * Labels for human-readable events.
 */
export function getEventLabel(type: string): string {
  const labels: Record<string, string> = {
    'session.started': '🔐 Login',
    'session.ended': '🚪 Logout',
    'question.answered': '✅ Question Answered',
    'question.viewed': '👁 Question Viewed',
    'simulation.created': '📋 Simulation Created',
    'simulation.started': '▶ Simulation Started',
    'simulation.finished': '🏁 Simulation Finished',
    'essay.created': '✏️ Essay Created',
    'essay.submitted': '📤 Essay Submitted',
    'essay.evaluated': '🎯 Essay Evaluated',
    'page.visited': '📄 Page Visited',
  };
  return labels[type] ?? type;
}

/**
 * Format ISO date to BR locale (English comments but locale stays pt-BR for users).
 */
export function fmtDate(iso: string | null | undefined): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('pt-BR', {
    day: '2-digit', month: '2-digit', year: '2-digit',
    hour: '2-digit', minute: '2-digit',
  });
}

/**
 * Format minutes to h/m format.
 */
export function fmtMinutes(mins: number | null | undefined): string {
  if (mins === null || mins === undefined) return '—';
  if (mins < 60) return `${mins}m`;
  return `${Math.floor(mins / 60)}h ${mins % 60}m`;
}
