import { Component, ErrorInfo, ReactNode } from 'react';

interface Props {
    children?: ReactNode;
}

interface State {
    hasError: boolean;
    error: Error | null;
}

class ErrorBoundary extends Component<Props, State> {
    public state: State = {
        hasError: false,
        error: null
    };

    public static getDerivedStateFromError(error: Error): State {
        return { hasError: true, error };
    }

    public componentDidCatch(error: Error, errorInfo: ErrorInfo) {
        console.error('Uncaught error:', error, errorInfo);
    }

    public render() {
        if (this.state.hasError) {
            return (
                <div className="min-h-screen flex items-center justify-center bg-slate-50 dark:bg-slate-900 p-4">
                    <div className="bg-white dark:bg-slate-800 p-8 rounded-2xl shadow-xl max-w-md w-full border border-red-100 dark:border-red-900/30">
                        <div className="w-16 h-16 bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">🛑</div>
                        <h2 className="text-xl font-bold text-center text-slate-800 dark:text-slate-100 mb-2">Ops! Algo deu errado</h2>
                        <p className="text-center text-slate-600 dark:text-slate-400 mb-6">
                            Ocorreu um erro inesperado na interface. Tente atualizar a página.
                        </p>
                        {this.state.error && (
                            <pre className="text-xs font-mono text-red-500 mb-6 bg-red-50 dark:bg-red-900/10 p-3 rounded overflow-auto max-h-40">
                                {this.state.error.message}
                            </pre>
                        )}
                        <button
                            onClick={() => window.location.reload()}
                            className="w-full bg-indigo-600 text-white font-bold py-3 px-4 rounded-xl hover:bg-indigo-700 transition"
                        >
                            Recarregar Sistema
                        </button>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}

export default ErrorBoundary;
