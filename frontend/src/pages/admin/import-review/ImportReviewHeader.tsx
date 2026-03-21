/**
 * ImportReviewHeader
 * Navigation and status indicator for the question review page.
 */
import React from 'react';
import { Link } from 'react-router-dom';
import { ImportQuestion } from './Types';

interface ImportReviewHeaderProps {
  question: ImportQuestion;
  searchParams: URLSearchParams;
}

const ImportReviewHeader: React.FC<ImportReviewHeaderProps> = ({ question, searchParams }) => {
  const isPending = question.review_status === 'pending' || question.review_status === 'review';

  return (
    <div className="flex items-center gap-3 mb-4">
      <Link 
        to={`/admin/import/review?${searchParams.toString()}`} 
        className="text-gray-400 hover:text-gray-600 transition-colors"
      >
        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
        </svg>
      </Link>
      <h2 className="font-bold text-lg text-gray-800 leading-tight">
        Questão #{question.id}
      </h2>
      <span className={`px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider ${
        isPending ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'
      }`}>
        {isPending ? 'Em Revisão' : 'Aprovada'}
      </span>
    </div>
  );
};

export default ImportReviewHeader;
