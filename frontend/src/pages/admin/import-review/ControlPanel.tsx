/**
 * ControlPanel
 * Triage actions (Approve, Revert), Navigation (Prev/Next), and External Links.
 */
import React from 'react';
import { Link } from 'react-router-dom';
import { ImportQuestion } from './Types';

interface ControlPanelProps {
  question: ImportQuestion;
  completion: {
    total: boolean;
    hasExplanation: boolean;
    hasReasoning: boolean;
    hasDifficulty: boolean;
    hasSubject: boolean;
    hasTopic: boolean;
  };
  approveMutation: any;
  revertMutation: any;
  sendToTriageMutation: any;
  prevId: number | null;
  nextId: number | null;
  navigate: (path: string) => void;
  searchParams: URLSearchParams;
  isCompact?: boolean;
}

const ControlPanel: React.FC<ControlPanelProps> = ({
  question,
  completion,
  approveMutation,
  revertMutation,
  sendToTriageMutation,
  prevId,
  nextId,
  navigate,
  searchParams,
  isCompact = false
}) => {
  const isPending = question.review_status === 'pending' || question.review_status === 'review';

  const handleEditClick = () => {
    sessionStorage.setItem('import_review_return_url', window.location.pathname + window.location.search);
    sessionStorage.setItem('import_review_scroll_y', window.scrollY.toString());
  };

  return (
    <div className={`space-y-3 ${isCompact ? '' : 'p-5 bg-white rounded-xl shadow-sm border border-gray-100'}`}>
      {!isCompact && (
        <h3 className="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
          <span className="w-1 h-1 bg-indigo-400 rounded-full"></span>
          Painel de Controle
        </h3>
      )}

      {isPending ? (
        <>
          {!completion.total && (
            <div className="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-3">
              <p className="text-[10px] font-bold text-amber-700 uppercase mb-1.5 flex items-center gap-1">
                ⚠️ Pendências:
              </p>
              <ul className="text-[10px] space-y-0.5 text-amber-600 font-medium">
                {!completion.hasExplanation && <li>• Falta Explicação</li>}
                {!completion.hasReasoning && <li>• Falta Raciocínio</li>}
                {!completion.hasDifficulty && <li>• Selecione Dificuldade</li>}
                {!completion.hasSubject && <li>• Falta Disciplina</li>}
                {!completion.hasTopic && <li>• Falta Assunto</li>}
              </ul>
            </div>
          )}
          <button
            onClick={() => approveMutation.mutate()}
            disabled={approveMutation.isPending || !completion.total}
            className={`w-full px-4 py-3 text-white rounded-lg font-bold text-sm flex items-center justify-center gap-2 shadow-sm transition-all active:scale-[0.98] ${
                completion.total ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-300 cursor-not-allowed'
            }`}
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            {approveMutation.isPending ? 'Aprovando...' : 'Aprovar e Publicar'}
          </button>
        </>
      ) : (
        <div className="space-y-2">
          <Link
            to={`/questoes?id=${question.id}`}
            target="_blank"
            className="w-full px-4 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-bold text-sm flex items-center justify-center gap-2 shadow-sm transition-all active:scale-[0.98]"
          >
            🔍 Abrir no Portal
          </Link>
          <Link
            to={`/admin/questions/${question.id}/edit`}
            onClick={handleEditClick}
            className="w-full px-4 py-3 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 font-bold text-sm flex items-center justify-center gap-2 shadow-sm transition-all active:scale-[0.98]"
          >
            ✏️ Editar Questão
          </Link>
          <div className="grid grid-cols-2 gap-2">
            <button
              onClick={() => revertMutation.mutate()}
              disabled={revertMutation.isPending}
              className="w-full px-3 py-3 bg-orange-500 text-white rounded-lg hover:bg-orange-600 font-bold text-[11px] flex flex-col items-center justify-center gap-1 shadow-sm transition-all active:scale-[0.98]"
            >
              <svg className="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
              </svg>
              <span>Retornar p/ Revisão</span>
            </button>
            <button
              onClick={() => sendToTriageMutation.mutate()}
              disabled={sendToTriageMutation.isPending}
              className="w-full px-3 py-3 bg-fuchsia-600 text-white rounded-lg hover:bg-fuchsia-700 font-bold text-[11px] flex flex-col items-center justify-center gap-1 shadow-sm transition-all active:scale-[0.98]"
            >
              <span className="text-lg leading-none">🤖</span>
              <span>Re-enviar p/ IA</span>
            </button>
          </div>
        </div>
      )}

      {isPending && (
          <div className="grid grid-cols-2 gap-2">
            <Link
              to={`/admin/questions/${question.id}/edit`}
              onClick={handleEditClick}
              className="px-3 py-2 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 font-semibold text-[11px] flex items-center justify-center gap-1.5 transition-colors"
            >
              ✍️ Editar
            </Link>
            <Link
              to={`/admin/import/review?${searchParams.toString()}`}
              className="px-3 py-2 bg-gray-50 text-gray-500 rounded-lg text-[11px] font-semibold text-center hover:bg-gray-100 transition-colors"
            >
              ⬅️ Sair
            </Link>
          </div>
      )}

      {/* Navigation Buttons */}
      <div className="grid grid-cols-2 gap-2 mt-2">
        <button
          disabled={!prevId}
          onClick={() => navigate(`/admin/import/review/${prevId}?${searchParams.toString()}`)}
          className="px-3 py-2 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed font-bold text-[11px] flex items-center justify-center gap-1.5 transition-colors"
        >
          ◄ Ant.
        </button>
        <button
          disabled={!nextId}
          onClick={() => navigate(`/admin/import/review/${nextId}?${searchParams.toString()}`)}
          className="px-3 py-2 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed font-bold text-[11px] flex items-center justify-center gap-1.5 transition-colors"
        >
          Próx. ►
        </button>
      </div>

      {/* View Full Exam Button */}
      {(() => {
        const idParam = question.arquivo_origem ? btoa(question.arquivo_origem) : 'null';
        const examParams = new URLSearchParams();
        if (!question.arquivo_origem) {
          if (question.year) examParams.append('year', question.year.toString());
          if (question.organization) examParams.append('organization', question.organization);
          if (question.institution) examParams.append('institution', question.institution);
          if (question.role) examParams.append('role', question.role);
        }
        const examUrl = `/admin/provas/${idParam}${examParams.toString() ? '?' + examParams.toString() : ''}`;

        return (
          <Link
            to={examUrl}
            target="_blank"
            className="w-full px-4 py-2 border border-indigo-200 text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 font-bold text-[11px] flex items-center justify-center gap-2 transition-all mt-2"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            📄 Ver Prova Completa
          </Link>
        );
      })()}
    </div>
  );
};

export default ControlPanel;
