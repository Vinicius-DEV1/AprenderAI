import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import './styles/index.css'
import App from './App.tsx'
import ErrorBoundary from './components/ErrorBoundary'
import { errorCaptureService } from './services/errorCaptureService'

errorCaptureService.init();

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            refetchOnWindowFocus: false,
            retry: 1,
        },
    },
})

createRoot(document.getElementById('root')!).render(
    <StrictMode>
        <QueryClientProvider client={queryClient}>
            <ErrorBoundary>
                <App />
            </ErrorBoundary>
        </QueryClientProvider>
    </StrictMode>,
)
