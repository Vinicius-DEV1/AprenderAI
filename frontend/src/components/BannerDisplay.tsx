import { useState, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { getActiveBanners, trackBannerInteraction } from '../api/banners';

interface Banner {
    id: number;
    title: string;
    body: string | null;
    image_url: string | null;
    background_color: string | null;
    display_type: 'modal' | 'bar' | 'notification' | 'card';
    button_text: string | null;
    button_url: string | null;
}

/**
 * BannerDisplay — fetches active banners and renders them by type.
 *
 * Renders the first relevant banner on load. Subsequent banners (if any)
 * are shown after the current one is dismissed.
 *
 * Supported types:
 *   modal        — full overlay modal
 *   bar          — fixed top bar below the header
 *   notification — bottom-left toast card
 *   card         — (handled inline in Dashboard, not here)
 */
export default function BannerDisplay() {
    const [currentIndex, setCurrentIndex] = useState(0);
    const [dismissed, setDismissed] = useState(false);
    const [reported, setReported] = useState(false);

    const { data } = useQuery({
        queryKey: ['active-banners'],
        queryFn: () => getActiveBanners().then(r => r.data.banners as Banner[]),
        staleTime: 5 * 60 * 1000, // Cache 5 min — banners don't change frequently
    });

    const banners = (data ?? []).filter(b => b.display_type !== 'card');
    const banner = banners[currentIndex];

    // Track view once per banner per mount
    useEffect(() => {
        if (banner && !reported) {
            trackBannerInteraction(banner.id, 'view').catch(() => {});
            setReported(true);
        }
    }, [banner, reported]);

    const handleClose = () => {
        if (banner) trackBannerInteraction(banner.id, 'close').catch(() => {});
        setDismissed(true);
        // Try next banner
        if (currentIndex + 1 < banners.length) {
            setCurrentIndex(i => i + 1);
            setDismissed(false);
            setReported(false);
        }
    };

    const handleCta = () => {
        if (banner) trackBannerInteraction(banner.id, 'click').catch(() => {});
    };

    if (!banner || dismissed) return null;

    const bg = banner.background_color || undefined;

    // ── Modal ─────────────────────────────────────────────────────────────────
    if (banner.display_type === 'modal') {
        return (
            <div className="fixed inset-0 z-[55] flex items-center justify-center p-4">
                <div className="absolute inset-0 bg-slate-900/70 backdrop-blur-sm" onClick={handleClose} />
                <div className="relative w-full max-w-lg bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden"
                    style={bg ? { background: bg } : undefined}>
                    <button
                        onClick={handleClose}
                        className="absolute top-4 right-4 w-8 h-8 flex items-center justify-center rounded-full bg-black/10 hover:bg-black/20 text-white transition-colors z-10"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                    {banner.image_url && (
                        <img src={banner.image_url} alt={banner.title} className="w-full h-48 object-cover" />
                    )}
                    <div className="p-6">
                        <h2 className={`text-xl font-bold mb-2 ${bg ? 'text-white' : 'text-slate-900 dark:text-slate-100'}`}>
                            {banner.title}
                        </h2>
                        {banner.body && (
                            <p className={`text-sm leading-relaxed mb-4 ${bg ? 'text-white/90' : 'text-slate-600 dark:text-slate-300'}`}>
                                {banner.body}
                            </p>
                        )}
                        {banner.button_text && banner.button_url && (
                            <a
                                href={banner.button_url}
                                onClick={handleCta}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-2 px-5 py-2.5 bg-white text-slate-900 rounded-xl font-semibold text-sm hover:bg-slate-50 transition-colors shadow-sm"
                            >
                                {banner.button_text}
                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                </svg>
                            </a>
                        )}
                    </div>
                </div>
            </div>
        );
    }

    // ── Bar ───────────────────────────────────────────────────────────────────
    if (banner.display_type === 'bar') {
        return (
            <div
                className="w-full flex items-center justify-center gap-4 px-4 py-2.5 text-white text-sm font-medium shadow-md relative z-40"
                style={{ background: bg || 'linear-gradient(to right, #2563eb, #4f46e5)' }}
            >
                <span>{banner.title}</span>
                {banner.body && <span className="hidden sm:inline opacity-80">— {banner.body}</span>}
                {banner.button_text && banner.button_url && (
                    <a
                        href={banner.button_url}
                        onClick={handleCta}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="px-3 py-1 bg-white/20 hover:bg-white/30 rounded-lg text-white text-xs font-semibold transition-colors"
                    >
                        {banner.button_text}
                    </a>
                )}
                <button onClick={handleClose} className="absolute right-4 top-1/2 -translate-y-1/2 text-white/70 hover:text-white">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        );
    }

    // ── Notification (toast-style) ─────────────────────────────────────────
    if (banner.display_type === 'notification') {
        return (
            <div className="fixed bottom-24 left-6 z-50 w-72 bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden"
                style={bg ? { border: `2px solid ${bg}` } : undefined}>
                <div className="flex items-start gap-3 p-4">
                    {banner.image_url && (
                        <img src={banner.image_url} alt="" className="w-10 h-10 rounded-lg object-cover flex-shrink-0" />
                    )}
                    <div className="flex-1 min-w-0">
                        <p className="text-sm font-semibold text-slate-900 dark:text-slate-100">{banner.title}</p>
                        {banner.body && <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-2">{banner.body}</p>}
                        {banner.button_text && banner.button_url && (
                            <a href={banner.button_url} onClick={handleCta} target="_blank" rel="noopener noreferrer"
                                className="mt-2 inline-block text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                                {banner.button_text} →
                            </a>
                        )}
                    </div>
                    <button onClick={handleClose} className="flex-shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        );
    }

    return null;
}
