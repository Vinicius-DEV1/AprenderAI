import { useState } from 'react';
import { submitFeedback } from '../api/feedback';

interface FeedbackWidgetProps {
    /** Developer-defined key identifying the feature (e.g. "simulado-resultado") */
    featureKey: string;
    /** The question to display to the user */
    question?: string;
    /** Extra CSS classes for the container */
    className?: string;
}

/**
 * FeedbackWidget — compact 👍/👎 inline feedback prompt.
 *
 * Usage example:
 *   <FeedbackWidget featureKey="simulado-resultado" question="Esse simulado foi útil?" />
 *
 * After the user votes once, the widget shows a thank-you message and disappears.
 * One vote per user per featureKey is enforced server-side.
 */
export default function FeedbackWidget({ featureKey, question = 'Esse recurso foi útil?', className = '' }: FeedbackWidgetProps) {
    const [state, setState] = useState<'idle' | 'loading' | 'done'>('idle');

    const handleVote = async (positive: boolean) => {
        setState('loading');
        try {
            await submitFeedback(featureKey, positive);
        } catch {
            // Silent fail — feedback is non-critical
        } finally {
            setState('done');
        }
    };

    if (state === 'done') {
        return (
            <div className={`flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 ${className}`}>
                <span>Obrigado pelo feedback! 🙏</span>
            </div>
        );
    }

    return (
        <div className={`flex items-center gap-3 ${className}`}>
            <span className="text-xs text-slate-500 dark:text-slate-400 font-medium">{question}</span>
            <div className="flex items-center gap-1.5">
                <button
                    onClick={() => handleVote(true)}
                    disabled={state === 'loading'}
                    className="flex items-center gap-1.5 px-2.5 py-1 rounded-full border border-slate-200 dark:border-slate-600 text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-green-50 hover:border-green-300 hover:text-green-700 dark:hover:bg-green-950/30 dark:hover:border-green-700 dark:hover:text-green-400 transition-colors disabled:opacity-50"
                    title="Sim, foi útil"
                >
                    <span className="text-sm">👍</span>
                    <span>Sim</span>
                </button>
                <button
                    onClick={() => handleVote(false)}
                    disabled={state === 'loading'}
                    className="flex items-center gap-1.5 px-2.5 py-1 rounded-full border border-slate-200 dark:border-slate-600 text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-red-50 hover:border-red-300 hover:text-red-700 dark:hover:bg-red-950/30 dark:hover:border-red-700 dark:hover:text-red-400 transition-colors disabled:opacity-50"
                    title="Não foi útil"
                >
                    <span className="text-sm">👎</span>
                    <span>Não</span>
                </button>
            </div>
        </div>
    );
}
