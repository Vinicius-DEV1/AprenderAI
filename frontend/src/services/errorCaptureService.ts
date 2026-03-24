import api from '../api/axios';
import { toast as sonnerToast } from 'sonner';

/**
 * Service to capture global errors, including unhandled exceptions,
 * promise rejections, and toasts, and send them to the backend backend.
 */
class ErrorCaptureService {
    private static instance: ErrorCaptureService;
    private buffer: any[] = [];
    private debounceTimer: ReturnType<typeof setTimeout> | null = null;
    private isInitialized = false;

    private originalSonnerError: any;

    private constructor() {}

    public static getInstance() {
        if (!ErrorCaptureService.instance) {
            ErrorCaptureService.instance = new ErrorCaptureService();
        }
        return ErrorCaptureService.instance;
    }

    public init() {
        if (this.isInitialized) return;
        this.isInitialized = true;

        this.setupWindowListeners();
        this.monkeyPatchToasts();
    }

    private setupWindowListeners() {
        window.addEventListener('error', (event) => {
            if (this.shouldIgnoreError(event.message)) return;
            this.captureError({
                message: event.message || 'Window Error',
                stack_trace: event.error?.stack || null,
                type: 'window_error',
            });
        });

        window.addEventListener('unhandledrejection', (event) => {
            const reason = event.reason;
            let message = 'Unhandled Promise Rejection';
            let stack = null;

            if (reason instanceof Error) {
                if (this.shouldIgnoreError(reason.message)) return;
                message = reason.message;
                stack = reason.stack;
            } else if (typeof reason === 'string') {
                if (this.shouldIgnoreError(reason)) return;
                message = reason;
            } else if (reason && reason.message) {
                if (this.shouldIgnoreError(reason.message)) return;
                message = reason.message;
            }

            this.captureError({
                message,
                stack_trace: stack,
                type: 'unhandled_rejection'
            });
        });
    }

    private monkeyPatchToasts() {
        // We monkey-patch the sonner `toast.error` to intercept manual alerts.
        // It's vital we call the original function so the UI toast still appears.
        this.originalSonnerError = sonnerToast.error;

        sonnerToast.error = (message: any, data?: any): string | number => {
            const returnedId = this.originalSonnerError(message, data);
            
            // Only capture string messages, ignore complex React nodes to prevent circular JSON / huge payloads
            if (typeof message === 'string') {
                this.captureError({
                    message: message,
                    type: 'toast_error',
                    additional_data: data,
                });
            }

            return returnedId as any;
        };
    }

    public captureError(errorData: {
        message: string;
        type?: string;
        stack_trace?: string | null;
        user_action?: string;
        additional_data?: any;
    }) {
        const payload = {
            ...errorData,
            url: window.location.href,
        };

        this.buffer.push(payload);

        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        // Debounce to prevent flooding the network if thousands of errors happen
        this.debounceTimer = setTimeout(() => {
            this.flush();
        }, 2000);
    }

    private flush() {
        if (this.buffer.length === 0) return;

        const errorsToSend = [...this.buffer];
        this.buffer = [];

        // In a real scenario we could send a batch payload. We'll iterate for now
        // to conform to the single POST endpoint.
        errorsToSend.forEach(error => {
            api.post('/api/v1/log-error', error).catch(() => {
                // Ignore errors related to sending error tracking to avoid infinite loops
            });
        });
    }

    private shouldIgnoreError(message: string): boolean {
        // Ignore benign React DevTools errors or resizing errors, extensions, etc
        if (!message) return true;
        const msgPattern = message.toLowerCase();
        if (msgPattern.includes('resizeobserver loop')) return true;
        if (msgPattern.includes('the window closure')) return true;
        if (msgPattern.includes('script error')) return true;
        return false;
    }
}

export const errorCaptureService = ErrorCaptureService.getInstance();
