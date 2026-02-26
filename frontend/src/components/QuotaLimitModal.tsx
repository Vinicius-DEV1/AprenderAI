import { Link } from 'react-router-dom';

interface QuotaLimitModalProps {
    isOpen: boolean;
    onClose: () => void;
    resource?: string;
    used?: number;
    limit?: number;
    upgradeRoute?: string;
}

export default function QuotaLimitModal({
    isOpen,
    onClose,
    resource = 'recurso',
    used = 0,
    limit = 0,
    upgradeRoute = '/plans'
}: QuotaLimitModalProps) {
    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto overflow-x-hidden p-4 sm:p-0">
            {/* Backdrop */}
            <div
                className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
                onClick={onClose}
            ></div>

            {/* Modal Panel */}
            <div className="relative w-full max-w-md transform overflow-hidden rounded-2xl bg-white dark:bg-slate-800 p-6 text-left align-middle shadow-xl transition-all animate-fade-in-up">

                {/* Icon */}
                <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <svg className="h-7 w-7 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>

                {/* Title */}
                <h3 className="mb-1 text-center text-xl font-bold text-gray-900 dark:text-slate-100">
                    Limite de {resource} Atingido
                </h3>

                {/* Usage indicator */}
                <p className="mb-4 text-center text-sm text-gray-500 dark:text-slate-400">
                    Você utilizou <strong className="text-gray-800 dark:text-slate-200">{used}</strong> de <strong className="text-gray-800 dark:text-slate-200">{limit === 0 ? 'ilimitadas' : limit}</strong> {resource.toLowerCase()} disponíveis neste ciclo mensal.
                </p>

                {/* Progress bar */}
                <div className="mb-6 h-2 w-full rounded-full bg-gray-200 dark:bg-slate-700">
                    <div className="h-2 rounded-full bg-red-500 transition-all duration-500" style={{ width: '100%' }}></div>
                </div>

                {/* Actions */}
                <div className="flex flex-col gap-3 sm:flex-row">
                    <Link
                        to={upgradeRoute}
                        onClick={onClose}
                        className="flex flex-1 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                        </svg>
                        Fazer Upgrade de Plano
                    </Link>

                    <button
                        type="button"
                        onClick={onClose}
                        className="flex-1 rounded-lg bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600 dark:focus:ring-slate-500"
                    >
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    );
}
