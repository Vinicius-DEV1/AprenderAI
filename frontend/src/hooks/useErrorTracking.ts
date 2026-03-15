import { useEffect } from 'react';
import { trackFrontendError } from '../api/checkoutTracking';

let installed = false;

/**
 * Singleton hook — installs global error handlers once.
 * Reports unhandled JS errors and promise rejections to the tracking API.
 * Mount once at the app root level (App.tsx).
 */
export function useErrorTracking() {
    useEffect(() => {
        if (installed) return;
        installed = true;

        const handleError = (event: ErrorEvent) => {
            trackFrontendError(
                event.message || 'Unknown error',
                event.error?.stack,
                undefined,
                window.location.pathname
            );
        };

        const handleRejection = (event: PromiseRejectionEvent) => {
            const reason = event.reason;
            const msg = reason instanceof Error ? reason.message : String(reason ?? 'Unhandled promise rejection');
            const stack = reason instanceof Error ? reason.stack : undefined;
            trackFrontendError(msg, stack, undefined, window.location.pathname);
        };

        window.addEventListener('error', handleError);
        window.addEventListener('unhandledrejection', handleRejection);

        return () => {
            window.removeEventListener('error', handleError);
            window.removeEventListener('unhandledrejection', handleRejection);
            installed = false;
        };
    }, []);
}
