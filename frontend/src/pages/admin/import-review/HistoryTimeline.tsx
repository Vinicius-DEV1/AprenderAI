/**
 * HistoryTimeline
 * Displays the audit trail and triage history for the question.
 */
import React from 'react';
import { TriageLog } from './Types';

interface HistoryTimelineProps {
  historyData: TriageLog[] | undefined;
}

const HistoryTimeline: React.FC<HistoryTimelineProps> = ({ historyData }) => {
  if (!historyData || historyData.length === 0) return null;

  return (
    <div className="bg-white rounded-lg shadow-sm p-5 space-y-4">
      <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wide flex items-center gap-2">
        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Histórico de Triagem
      </h3>
      <div className="space-y-4 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-slate-200 before:to-transparent">
        {historyData.map((log) => (
          <div key={log.id} className="relative flex items-start gap-3">
            <div className="flex items-center justify-center w-8 h-8 rounded-full border-2 border-white bg-indigo-50 text-indigo-600 z-10 shrink-0 ring-1 ring-slate-200">
              {log.triage_type === 'ai_batch' ? '🤖' : '👤'}
            </div>
            <div className="bg-slate-50 border border-slate-100 p-3 rounded-lg flex-1 min-w-0 shadow-sm text-sm">
              <div className="flex justify-between items-start mb-1 gap-2">
                <div className="font-semibold text-slate-800 break-words">
                  {log.status === 'approved' ? (
                    <span className="text-green-600">✅ Aprovado</span>
                  ) : (
                    <span className="text-orange-600">⚠️ Status: Revisão</span>
                  )}
                </div>
                <div className="text-[10px] text-slate-400 font-medium whitespace-nowrap shrink-0">
                  {new Date(log.created_at).toLocaleString()}
                </div>
              </div>
              <div className="text-slate-600 text-xs mb-2">
                Por: <span className="font-medium">{log.processed_by === 'system' ? 'IA Batch Triage' : log.processed_by}</span>
              </div>
              {log.issues_detected && log.issues_detected.length > 0 && (
                <div className="flex flex-wrap gap-1 mt-2">
                  {log.issues_detected.map((iss) => (
                    <span key={iss} className="px-2 py-0.5 bg-red-100 text-red-700 text-[10px] uppercase font-bold tracking-wider rounded">
                      🚫 {iss.replace(/_/g, ' ')}
                    </span>
                  ))}
                </div>
              )}
              {log.quality_score !== null && (
                <div className="mt-2 text-xs">
                  <span className="font-semibold text-slate-500">Qualidade Pedagógica:</span>
                  <span className={`ml-1 font-bold ${log.quality_score >= 80 ? 'text-green-600' : log.quality_score >= 60 ? 'text-amber-600' : 'text-red-600'}`}>
                    {log.quality_score}/100
                  </span>
                </div>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

export default HistoryTimeline;
