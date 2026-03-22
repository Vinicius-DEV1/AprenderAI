import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';
import { useConfigStore } from '../stores/configStore';

declare global {
    interface Window {
        gtag?: (...args: any[]) => void;
        dataLayer?: any[];
    }
}

/**
 * Routes that must NEVER be tracked by external analytics (GA4).
 * This guard protects admin operations, user data and internal metrics
 * from being sent to Google — required for LGPD compliance.
 */
const PRIVATE_ROUTE_PREFIXES = [
    '/admin',
    '/dashboard',
    '/simulados',
    '/questoes',
    '/redacoes',
    '/redacao',
    '/essays',
    '/plano-de-estudo',
    '/concursos',
    '/perfil',
    '/cadernos',
    '/planos',
    '/notificacoes',
    '/welcome',
    '/checkout',
];

/**
 * Returns true if the given pathname belongs to a private/authenticated area
 * that should NEVER be measured by external analytics tools.
 */
function isPrivateRoute(pathname: string): boolean {
    return PRIVATE_ROUTE_PREFIXES.some((prefix) => pathname.startsWith(prefix));
}

/**
 * Captures UTM parameters from the URL query string and persists them in
 * sessionStorage so attribution data survives the full registration funnel.
 * Only runs on public pages (landing, login, register, etc.).
 */
function captureUTM(search: string): void {
    const params = new URLSearchParams(search);
    const utm = {
        source: params.get('utm_source'),
        medium: params.get('utm_medium'),
        campaign: params.get('utm_campaign'),
        term: params.get('utm_term'),
        content: params.get('utm_content'),
    };

    // Only persist if at least one UTM parameter is present
    if (Object.values(utm).some(Boolean)) {
        sessionStorage.setItem('utm_attrs', JSON.stringify(utm));
    }
}

export default function Analytics() {
    const location = useLocation();
    const { analytics } = useConfigStore();

    // Load GA4 script once — only on initial visit to a public page
    useEffect(() => {
        if (!analytics.enabled || !analytics.measurementId) return;
        if (isPrivateRoute(location.pathname)) return;

        if (!window.gtag) {
            const script = document.createElement('script');
            script.async = true;
            script.src = `https://www.googletagmanager.com/gtag/js?id=${analytics.measurementId}`;
            document.head.appendChild(script);

            window.dataLayer = window.dataLayer || [];
            window.gtag = function () {
                window.dataLayer!.push(arguments);
            };
            window.gtag('js', new Date());
            window.gtag('config', analytics.measurementId);
        }
    }, [analytics]); // eslint-disable-line react-hooks/exhaustive-deps

    // Track page_view on every PUBLIC route change; capture UTM on first hit
    useEffect(() => {
        if (isPrivateRoute(location.pathname)) return;

        // Capture UTM attribution on any public page (persists in sessionStorage)
        captureUTM(location.search);

        if (analytics.enabled && analytics.measurementId && window.gtag) {
            window.gtag('event', 'page_view', {
                page_path: location.pathname + location.search,
            });
        }
    }, [location, analytics]);

    return null;
}
