import React, { useState, useRef, useEffect } from 'react';

interface AccordionProps {
    title: React.ReactNode;
    children: React.ReactNode;
    defaultExpanded?: boolean;
    className?: string;
    variant?: 'default' | 'transparent';
}

export default function Accordion({ title, children, defaultExpanded = false, className = '', variant = 'default' }: AccordionProps) {
    const [isExpanded, setIsExpanded] = useState(defaultExpanded);
    const contentRef = useRef<HTMLDivElement>(null);
    const [contentHeight, setContentHeight] = useState('0px');

    useEffect(() => {
        if (contentRef.current) {
            setContentHeight(isExpanded ? `${contentRef.current.scrollHeight}px` : '0px');
        }
    }, [isExpanded, children]);

    const isTransparent = variant === 'transparent';
    const containerClasses = isTransparent
        ? `border border-white/20 rounded-xl overflow-hidden ${className}`
        : `border border-slate-200 rounded-xl overflow-hidden bg-white ${className}`;

    const buttonClasses = isTransparent
        ? `w-full px-5 py-4 flex items-center justify-between text-left hover:bg-white/5 transition-colors focus:outline-none`
        : `w-full px-5 py-4 flex items-center justify-between text-left hover:bg-slate-50 transition-colors focus:outline-none`;

    const titleClasses = isTransparent
        ? `text-sm md:text-base`
        : `font-semibold text-slate-800 text-sm md:text-base`;

    const iconContainerClasses = isTransparent
        ? `transform transition-transform duration-300 flex items-center justify-center p-1 rounded-full bg-white/10 text-white ${isExpanded ? 'rotate-180' : ''}`
        : `transform transition-transform duration-300 flex items-center justify-center p-1 rounded-full bg-slate-100 text-slate-500 ${isExpanded ? 'rotate-180' : ''}`;

    const contentWrapperClasses = isTransparent
        ? `transition-all duration-300 ease-in-out`
        : `transition-all duration-300 ease-in-out bg-slate-50/50`;

    const contentInnerClasses = isTransparent
        ? `px-5 pb-5 pt-1 text-sm leading-relaxed text-blue-100`
        : `px-5 pb-5 pt-1 text-slate-600 text-sm leading-relaxed`;

    return (
        <div className={containerClasses}>
            <button
                type="button"
                className={buttonClasses}
                onClick={() => setIsExpanded(!isExpanded)}
            >
                <div className={titleClasses}>
                    {title}
                </div>
                <div className={iconContainerClasses}>
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </button>

            <div
                className={contentWrapperClasses}
                style={{ maxHeight: contentHeight, opacity: isExpanded ? 1 : 0 }}
            >
                <div ref={contentRef} className={contentInnerClasses}>
                    {children}
                </div>
            </div>
        </div>
    );
}
