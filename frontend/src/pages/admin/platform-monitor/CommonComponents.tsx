/**
 * Common Platform Components
 * Reusable UI elements for the Platform Monitor dashboard.
 */
import React, { useEffect } from 'react';

/**
 * StatCard: Colorful metric display with gradient icon.
 */
export function StatCard({
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

/**
 * SectionHeader: Title and optional action (right side).
 */
export function SectionHeader({ title, children }: { title: string; children?: React.ReactNode }) {
  return (
    <div className="flex items-center justify-between mb-4">
      <h2 className="text-lg font-bold text-slate-800 dark:text-slate-100">{title}</h2>
      {children}
    </div>
  );
}

/**
 * PeriodTabs: Styled toggle for Today / 7 Days / 30 Days.
 */
export function PeriodTabs({ value, onChange }: { value: string; onChange: (p: any) => void }) {
  const tabs: { key: string; label: string }[] = [
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

/**
 * Avatar: Rounded user image or initial.
 */
export function Avatar({ name, avatarUrl }: { name: string; avatarUrl?: string | null }) {
  if (avatarUrl) {
    return <img src={avatarUrl} className="w-8 h-8 rounded-full object-cover" alt={name} />;
  }
  return (
    <div className="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300 flex items-center justify-center text-sm font-bold flex-shrink-0">
      {name.charAt(0).toUpperCase()}
    </div>
  );
}

/**
 * Badge: Small status indicator.
 */
export function Badge({ children, color = 'slate' }: { children: React.ReactNode; color?: string }) {
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

/**
 * ModalWrapper: Reusable backdrop and focus trap (Esc key listener).
 */
export function ModalWrapper({ title, onClose, children, wide = false }: {
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

/**
 * Spinner: Simple animated loader.
 */
export function Spinner() {
  return <div className="w-6 h-6 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin" />;
}

/**
 * DetailButton: Indigo link-style button for drill-downs.
 */
export function DetailButton({ onClick }: { onClick: () => void }) {
  return (
    <button
      onClick={onClick}
      className="px-3 py-1.5 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 rounded-lg transition-colors border border-indigo-200 dark:border-indigo-800"
    >
      Detalhar →
    </button>
  );
}
