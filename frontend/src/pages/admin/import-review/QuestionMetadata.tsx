/**
 * QuestionMetadata
 * Displays question metadata (Organization, Institution, Year, Subjects) and AI audit logs.
 */
import React from 'react';
import { ImportQuestion, ImportItem, TriageLog } from './Types';

interface QuestionMetadataProps {
  question: ImportQuestion;
  importItem?: ImportItem;
  latestAILog?: TriageLog;
}

const QuestionMetadata: React.FC<QuestionMetadataProps> = ({ question, importItem, latestAILog }) => {
  return (
    <div className="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
      <h3 className="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
        <span className="w-1 h-1 bg-indigo-400 rounded-full"></span>
        Metadados
      </h3>
      <div className="grid grid-cols-2 gap-x-4 gap-y-2 text-[11px]">
        {question.organization && (
          <div className="flex flex-col">
            <dt className="text-gray-400 font-bold uppercase text-[9px]">Banca</dt>
            <dd className="font-bold text-gray-800">{question.organization}</dd>
          </div>
        )}
        {question.institution && (
          <div className="flex flex-col">
            <dt className="text-gray-400 font-bold uppercase text-[9px]">Órgão</dt>
            <dd className="text-gray-700 font-medium">{question.institution}</dd>
          </div>
        )}
        {question.role && (
          <div className="flex flex-col">
            <dt className="text-gray-400 font-bold uppercase text-[9px]">Cargo</dt>
            <dd className="text-gray-700 font-medium truncate">{question.role}</dd>
          </div>
        )}
        {question.year && (
          <div className="flex flex-col">
            <dt className="text-gray-400 font-bold uppercase text-[9px]">Ano</dt>
            <dd className="text-gray-700 font-medium">{question.year}</dd>
          </div>
        )}
        {question.subjects && question.subjects.length > 0 && (
          <div className="flex flex-col col-span-2">
            <dt className="text-gray-400 font-bold uppercase text-[9px] mb-1">Matérias</dt>
            <dd className="flex flex-wrap gap-1">
              {question.subjects.map((s) => (
                <span key={s.id || s.name} className="px-1.5 py-0.5 bg-green-50 text-green-700 text-[10px] rounded font-bold border border-green-100">
                  {s.name}
                </span>
              ))}
            </dd>
          </div>
        )}
        {importItem && (
          <div className="flex flex-col col-span-2 border-t border-gray-50 pt-2 mt-1">
            <dt className="text-gray-400 font-bold uppercase text-[9px]">Lote</dt>
            <dd className="text-gray-500 font-medium italic truncate">
              {importItem.import?.batch_name ?? '—'}
            </dd>
          </div>
        )}
        {latestAILog && (
          <div className="flex flex-col col-span-2 border-t border-gray-100 pt-3 mt-2">
            <div className="flex justify-between items-center mb-1.5">
              <dt className="text-indigo-500 font-bold uppercase text-[10px] flex items-center gap-1.5 tracking-wider">
                <span>🤖</span> Análise da IA
              </dt>
              {latestAILog.quality_score !== null && (
                <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${
                  latestAILog.quality_score >= 80 ? 'bg-green-100 text-green-700' : 
                  latestAILog.quality_score >= 60 ? 'bg-amber-100 text-amber-700' : 
                  'bg-red-100 text-red-700'
                }`}>
                  Score: {latestAILog.quality_score}/100
                </span>
              )}
            </div>
            <dd className="flex flex-wrap gap-1 mt-1">
              {latestAILog.issues_detected && latestAILog.issues_detected.length > 0 ? (
                latestAILog.issues_detected.map((iss) => (
                  <span key={iss} className="px-1.5 py-0.5 bg-red-50 text-red-700 text-[10px] uppercase font-bold tracking-wider rounded border border-red-100 flex items-center gap-1">
                    <span>🚫</span> {iss.replace(/_/g, ' ')}
                  </span>
                ))
              ) : (
                <span className="px-1.5 py-0.5 bg-green-50 text-green-700 text-[10px] uppercase font-bold tracking-wider rounded border border-green-100 flex items-center gap-1">
                  <span>✅</span> Sem problemas
                </span>
              )}
            </dd>
          </div>
        )}
      </div>
    </div>
  );
};

export default QuestionMetadata;
