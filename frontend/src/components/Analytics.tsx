import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';
import { useConfigStore } from '../stores/configStore';

declare global {
    interface Window {
        gtag?: (...args: any[]) => void;
        dataLayer?: any[];
    }
}

export default function Analytics() {
    const location = useLocation();
    const { analytics } = useConfigStore();

    useEffect(() => {
        if (!analytics.enabled || !analytics.measurementId) return;

        // Check if script is already loaded
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
    }, [analytics]);

    useEffect(() => {
        if (analytics.enabled && analytics.measurementId && window.gtag) {
            window.gtag('event', 'page_view', {
                page_path: location.pathname + location.search,
            });
        }
    }, [location, analytics]);

    return null;
}
