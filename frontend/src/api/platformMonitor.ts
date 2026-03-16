import axios from './axios';

// ─── Types ────────────────────────────────────────────────────────────────────

export interface OverviewData {
  users_today: number;
  online_now: number;
  questions_today: number;
  simulations_today: number;
  essays_today: number;
  avg_session_minutes: number;
  total_users: number;
  sessions_today: number;
}

export interface OnlineUser {
  user_id: number;
  name: string;
  email: string;
  avatar_url: string | null;
  current_page: string | null;
  last_activity: string;
  login_at: string | null;
  online_minutes: number | null;
  ip_address: string | null;
}

export interface LoginEntry {
  user_id: number;
  name: string;
  email: string;
  avatar_url: string | null;
  login_at: string;
  ip_address: string | null;
  session_minutes: number | null;
  session_status: string;
}

export interface LoginCounts {
  today: number;
  week: number;
  month: number;
}

export interface QuestionUserRow {
  user_id: number;
  name: string;
  email: string;
  avatar_url: string | null;
  total: number;
  correct: number;
  wrong: number;
  accuracy: number;
}

export interface SimulationRow {
  id: number;
  user_id: number;
  name: string;
  email: string;
  avatar_url: string | null;
  type: string;
  status: string;
  score: number | null;
  created_at: string;
  started_at: string | null;
  finished_at: string | null;
  duration_minutes: number | null;
}

export interface EssayRow {
  id: number;
  user_id: number;
  name: string;
  email: string;
  avatar_url: string | null;
  title: string;
  status: string;
  score: number | null;
  created_at: string;
  submitted_at: string | null;
  evaluated_at: string | null;
  time_spent_minutes: number | null;
}

export interface ActivityEvent {
  id: string | number;
  user_id: number;
  name: string;
  email: string;
  avatar_url: string | null;
  event_type: string;
  page: string | null;
  resource_id: number | null;
  resource_type: string | null;
  metadata: Record<string, unknown> | null;
  created_at: string;
}

export interface UserDetailStats {
  questions_total: number;
  questions_correct: number;
  questions_wrong: number;
  accuracy: number;
  simulations_total: number;
  simulations_finished: number;
  sim_avg_score: number | null;
  essays_total: number;
  essays_submitted: number;
  essays_evaluated: number;
  total_time_minutes: number;
  active_days_30: number;
}

export interface UserDetail {
  user: {
    id: number;
    name: string;
    email: string;
    avatar_url: string | null;
    role: string;
    plan: string | null;
    created_at: string | null;
    last_login: string | null;
  };
  stats: UserDetailStats;
  timeline: ActivityEvent[];
}

// ─── API Calls ────────────────────────────────────────────────────────────────

const BASE = '/api/v1/admin/platform-monitor';

export const getPlatformOverview = () =>
  axios.get<OverviewData>(`${BASE}/overview`).then(r => r.data);

export const getPlatformOnline = () =>
  axios.get<{ count: number; users: OnlineUser[] }>(`${BASE}/online`).then(r => r.data);

export const getPlatformLogins = (period: 'today' | 'week' | 'month' = 'today') =>
  axios
    .get<{ period: string; label: string; counts: LoginCounts; list: LoginEntry[] }>(
      `${BASE}/logins`,
      { params: { period } }
    )
    .then(r => r.data);

export const getPlatformQuestions = (period: 'today' | 'week' | 'month' = 'today') =>
  axios
    .get<{
      period: string;
      label: string;
      counts: { today: number; week: number; month: number };
      total: number;
      correct: number;
      wrong: number;
      accuracy: number;
      error_rate: number;
      per_user: QuestionUserRow[];
    }>(`${BASE}/questions`, { params: { period } })
    .then(r => r.data);

export const getPlatformSimulations = (period: 'today' | 'week' | 'month' = 'today') =>
  axios
    .get<{
      period: string;
      label: string;
      counts: { today: number; week: number; month: number };
      created: number;
      started: number;
      finished: number;
      list: SimulationRow[];
    }>(`${BASE}/simulations`, { params: { period } })
    .then(r => r.data);

export const getPlatformEssays = (period: 'today' | 'week' | 'month' = 'today') =>
  axios
    .get<{
      period: string;
      label: string;
      counts: { today: number; week: number; month: number };
      created: number;
      submitted: number;
      evaluated: number;
      list: EssayRow[];
    }>(`${BASE}/essays`, { params: { period } })
    .then(r => r.data);

export const getPlatformActivity = () =>
  axios.get<{ feed: ActivityEvent[] }>(`${BASE}/activity`).then(r => r.data);

export const getPlatformUserDetail = (id: number) =>
  axios.get<UserDetail>(`${BASE}/user/${id}`).then(r => r.data);
