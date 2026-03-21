import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
    base: '/',
    plugins: [react(), tailwindcss()],
    appType: 'spa',
    server: {
        port: 5173,
        strictPort: true,
        host: true,
        allowedHosts: true,
        proxy: {
            '/api': {
                target: 'http://webserver:80',
                changeOrigin: true,
            },
            '/sanctum': {
                target: 'http://webserver:80',
                changeOrigin: true,
            },
            '/auth': {
                target: 'http://webserver:80',
                changeOrigin: true,
            },
            '/storage': {
                target: 'http://webserver:80',
                changeOrigin: true,
            },
        },
        watch: {
            usePolling: true
        }
    }
})

