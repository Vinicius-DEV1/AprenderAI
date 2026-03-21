/**
 * QuestionContent
 * Renders the question statement (markdown) and its alternatives (text or images).
 */
import React from 'react';
import { renderMd } from '../../../utils/markdown';
import { ImportQuestion } from './Types';

interface QuestionContentProps {
  question: ImportQuestion;
  getCacheBustedUrl: (url: string) => string;
  apiUrl: string;
}

const QuestionContent: React.FC<QuestionContentProps> = ({ question, getCacheBustedUrl, apiUrl }) => {
  return (
    <div className="space-y-3">
      {/* Statement Card */}
      <div className="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
        <h3 className="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
          <span className="w-1 h-1 bg-indigo-400 rounded-full"></span>
          Enunciado
        </h3>
        <div 
          className="prose prose-indigo max-w-none text-gray-800 text-[13px] leading-relaxed bg-slate-50 p-3 rounded-lg border border-slate-100 dark:text-slate-300" 
          dangerouslySetInnerHTML={renderMd(question.statement)} 
        />

        {question.image_path && (
          <div className="mt-4 pt-4 border-t border-gray-100">
            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">🖼️ Imagem do Enunciado (Recorte)</p>
            <img
              src={getCacheBustedUrl(
                question.image_path.startsWith('http') 
                  ? question.image_path 
                  : `${apiUrl}/storage/${question.image_path.replace(/^\//, '').replace(/^storage\//, '')}`.replace(/([^:])\/\//g, '$1/')
              )}
              alt="Imagem do Enunciado"
              className="max-w-full h-auto rounded border border-gray-200"
            />
          </div>
        )}
      </div>

      {/* Alternatives Card */}
      <div className="bg-white rounded-xl shadow-sm p-4 border border-gray-100" id="alternatives-card">
        <h3 className="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
          <span className="w-1 h-1 bg-indigo-400 rounded-full"></span>
          Alternativas
        </h3>
        {question.alternatives && question.alternatives.length > 0 ? (
          <div className="space-y-3">
            {[...question.alternatives].sort((a, b) => a.label.localeCompare(b.label)).map((alt) => (
              <div key={alt.id || alt.label} className={`flex gap-3 p-3 rounded-lg ${alt.is_correct ? 'bg-green-50 border border-green-200' : 'bg-gray-50'}`}>
                <span className={`font-bold text-sm w-6 flex-shrink-0 ${alt.is_correct ? 'text-green-700' : 'text-gray-500'}`}>
                  {alt.label})
                </span>
                <div className="flex-1 min-w-0">
                  {(alt.content?.startsWith('questions_images/') || alt.content?.startsWith('crops/') || alt.content?.startsWith('storage/')) ? (
                    <img
                      src={getCacheBustedUrl(
                        alt.content.startsWith('http') 
                          ? alt.content 
                          : `${apiUrl}/storage/${alt.content.replace(/^\//, '').replace(/^storage\//, '')}`.replace(/([^:])\/\//g, '$1/')
                      )}
                      alt={`Alternativa ${alt.label}`}
                      className="max-w-full h-auto rounded border border-gray-200"
                    />
                  ) : (
                    <div 
                        className="prose prose-indigo max-w-none text-sm text-gray-700 alternatives-markdown dark:text-slate-300" 
                        dangerouslySetInnerHTML={renderMd(alt.content)} 
                    />
                  )}
                </div>
                {alt.is_correct && (
                  <span className="text-green-600 text-xs font-semibold flex-shrink-0">✓ Gabarito</span>
                )}
              </div>
            ))}
          </div>
        ) : (
          <div className="bg-orange-50 border border-orange-200 rounded-lg p-4 text-center">
            <p className="text-orange-700 text-sm font-medium">⚠️ Nenhuma alternativa textual</p>
            <p className="text-orange-600 text-xs mt-1">Use o editor de crop ao lado para recortar as alternativas da imagem.</p>
          </div>
        )}
      </div>
    </div>
  );
};

export default QuestionContent;
