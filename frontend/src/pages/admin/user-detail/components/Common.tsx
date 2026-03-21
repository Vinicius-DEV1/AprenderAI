import React from 'react';

export const Tooltip = ({ content }: { content: string }) => (
    <div className="group relative inline-flex items-center ml-1 cursor-help">
        <svg className="w-4 h-4 text-gray-400 hover:text-gray-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block w-56 p-2 bg-gray-900 text-white text-[11px] font-normal leading-tight rounded shadow-xl z-10 text-center pointer-events-none">
            {content}
            <div className="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
        </div>
    </div>
);

export const EmptyState = ({ title, message, icon }: { title: string, message: string, icon?: React.ReactNode }) => (
    <div className="flex flex-col items-center justify-center p-8 text-gray-400 bg-gray-50/50 rounded-xl border border-dashed border-gray-200 my-4">
        <div className="mb-3 text-gray-300">
            {icon || (
                <svg className="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
            )}
        </div>
        <h4 className="text-sm font-bold text-gray-600 mb-1">{title}</h4>
        <p className="text-xs text-center">{message}</p>
    </div>
);
