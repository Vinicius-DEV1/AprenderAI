/**
 * analyticsService — Centralized analytics hub.
 *
 * All GA4 event calls in public-facing pages MUST go through this module.
 * Benefits:
 *  - Single route guard: PRIVATE_ROUTE_PREFIXES is enforced in one place
 *  - Easy to swap/add analytics destinations (e.g. Amplitude, Mixpanel)
 *  - Uniform naming convention (snake_case noun_verb pattern)
 *  - Silent no-op when GA4 is disabled or not loaded (e.g. blocked by ad blockers)
 *
 * RULE: Never call window.gtag() directly from components — use this service.
 * RULE: Never call this service from admin or authenticated-app routes.
 */

/** Routes that must never be tracked by external analytics. */
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

/** Returns true when the current page must NOT be tracked externally. */
export function isPrivateRoute(pathname: string = window.location.pathname): boolean {
    return PRIVATE_ROUTE_PREFIXES.some((prefix) => pathname.startsWith(prefix));
}

/**
 * Sends a GA4 event.
 * Silently no-ops if:
 *  - The current route is private/admin
 *  - window.gtag is not loaded (e.g. GA disabled or ad-blocked)
 */
export function gtrack(
    eventName: string,
    params?: Record<string, string | number | boolean | null | undefined>,
): void {
    if (isPrivateRoute()) return;
    if (typeof window.gtag !== 'function') return;
    window.gtag('event', eventName, params);
}

/**
 * Reads UTM attribution data captured by Analytics.tsx from sessionStorage.
 * Returns an empty object if no UTM data is present.
 */
export function getStoredUTM(): Record<string, string> {
    try {
        const raw = sessionStorage.getItem('utm_attrs');
        if (!raw) return {};
        const parsed = JSON.parse(raw);
        // Return only the keys that are non-null strings
        return Object.fromEntries(
            Object.entries(parsed).filter(([, v]) => typeof v === 'string' && v)
        ) as Record<string, string>;
    } catch {
        return {};
    }
}

// ---------------------------------------------------------------------------
// Typed event helpers — preferred over calling gtrack() directly.
// Add strongly-typed helpers here as new events are defined.
// ---------------------------------------------------------------------------

export const Analytics = {
    /** User clicked a CTA button on the landing page. */
    ctaClicked(buttonLocation: string, planName = 'n/a'): void {
        gtrack('cta_click', { button_location: buttonLocation, plan_name: planName });
    },

    /** User completed registration via email. */
    signedUp(method: 'email' | 'google' = 'email'): void {
        const utm = getStoredUTM();
        gtrack('sign_up', {
            method,
            ...(utm.source   && { utm_source:   utm.source }),
            ...(utm.medium   && { utm_medium:   utm.medium }),
            ...(utm.campaign && { utm_campaign: utm.campaign }),
        });
        sessionStorage.removeItem('utm_attrs');
    },

    /** User logged in successfully. */
    loggedIn(method: 'email' | 'google' = 'email'): void {
        gtrack('login', { method });
    },

    /** User viewed the pricing/plans page. */
    pricesViewed(sourcePage: string): void {
        gtrack('prices_viewed', { source_page: sourcePage });
    },

    /** User completed a purchase (subscription). GA4 standard e-commerce event. */
    purchase(params: {
        transactionId: string;
        planSlug: string;
        planName: string;
        value: number;
        currency?: string;
    }): void {
        gtrack('purchase', {
            transaction_id: params.transactionId,
            value: params.value,
            currency: params.currency ?? 'BRL',
            items: JSON.stringify([{
                item_id: params.planSlug,
                item_name: params.planName,
                price: params.value,
                quantity: 1,
            }]),
        });
    },

    /**
     * ATIVAÇÃO: Disparado quando o usuário cria/inicia um novo simulado
     */
    simulationCreated(type: 'enem' | 'concurso', totalQuestions: number) {
        gtrack('simulation_created', {
            simulation_type: type,
            total_questions: totalQuestions
        });
    },

    /**
     * ATIVAÇÃO: Disparado apenas na PRIMEIRA vez que o usuário criar um simulado
     */
    simulationFirstCreated(type: 'enem' | 'concurso') {
        if (!localStorage.getItem('activation_first_sim')) {
            gtrack('simulation_first_created', { simulation_type: type });
            localStorage.setItem('activation_first_sim', 'true');
        }
    },

    /**
     * ATIVAÇÃO: Disparado quando o usuário cria um novo rascunho de redação (ENEM ou Concurso)
     */
    essayCreated(type: string, hasAutomatedTheme: boolean) {
        gtrack('essay_created', {
            essay_type: type,
            automated_theme: hasAutomatedTheme
        });
    },

    /**
     * ATIVAÇÃO: Disparado apenas na PRIMEIRA vez que o usuário enviar redação
     */
    essayFirstCreated(type: string) {
        if (!localStorage.getItem('activation_first_essay')) {
            gtrack('essay_first_created', { essay_type: type });
            localStorage.setItem('activation_first_essay', 'true');
        }
    },

    /**
     * ENGAJAMENTO: Disparado quando uma questão é respondida (usado para medir volume de engajamento core)
     */
    questionAnswered(subject: string, isCorrect: boolean) {
        gtrack('question_answered', {
            subject: subject || 'unknown',
            is_correct: isCorrect
        });
    },

    /**
     * ATIVAÇÃO: Disparado apenas na PRIMEIRA vez que o usuário responder uma questão
     */
    questionFirstAnswered(subject: string) {
        if (!localStorage.getItem('activation_first_question')) {
            gtrack('question_first_answered', { subject: subject || 'unknown' });
            localStorage.setItem('activation_first_question', 'true');
        }
    }
} as const;
