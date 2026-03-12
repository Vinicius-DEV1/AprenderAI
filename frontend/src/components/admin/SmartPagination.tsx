import React from 'react';

interface SmartPaginationProps {
    currentPage: number;
    lastPage: number;
    onPageChange: (page: number) => void;
}

export default function SmartPagination({ currentPage, lastPage, onPageChange }: SmartPaginationProps) {
    if (lastPage <= 1) return null;

    const getPages = () => {
        const pages: (number | string)[] = [];
        const range = 2; // Number of pages to show before and after current page

        // Always show the first page
        pages.push(1);

        if (currentPage > range + 2) {
            pages.push('...');
        }

        // Show range around current page
        for (let i = Math.max(2, currentPage - range); i <= Math.min(lastPage - 1, currentPage + range); i++) {
            pages.push(i);
        }

        if (currentPage < lastPage - range - 1) {
            pages.push('...');
        }

        // Always show the last page
        if (lastPage > 1) {
            pages.push(lastPage);
        }

        return pages;
    };

    return (
        <div className="flex flex-wrap items-center justify-center gap-1.5 mt-6 px-4">
            {/* Previous Button */}
            <button
                onClick={() => onPageChange(Math.max(1, currentPage - 1))}
                disabled={currentPage === 1}
                className="px-3 py-1.5 rounded-lg bg-white text-gray-600 border border-gray-200 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed text-xs font-bold transition-all"
            >
                Anterior
            </button>

            {/* Page Numbers */}
            <div className="flex items-center gap-1.5 overflow-x-auto no-scrollbar py-1">
                {getPages().map((p, idx) => (
                    <React.Fragment key={idx}>
                        {typeof p === 'number' ? (
                            <button
                                onClick={() => onPageChange(p)}
                                className={`min-w-[32px] h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all border ${
                                    currentPage === p
                                        ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm'
                                        : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'
                                }`}
                            >
                                {p}
                            </button>
                        ) : (
                            <span className="px-1 text-gray-400 font-bold select-none">{p}</span>
                        )}
                    </React.Fragment>
                ))}
            </div>

            {/* Next Button */}
            <button
                onClick={() => onPageChange(Math.min(lastPage, currentPage + 1))}
                disabled={currentPage === lastPage}
                className="px-3 py-1.5 rounded-lg bg-white text-gray-600 border border-gray-200 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed text-xs font-bold transition-all"
            >
                Próxima
            </button>
        </div>
    );
}
