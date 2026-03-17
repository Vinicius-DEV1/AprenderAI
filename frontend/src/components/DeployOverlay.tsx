import { useEffect, useState } from 'react';
import { DeployState } from '../hooks/useDeployDetection';

interface DeployOverlayProps {
    state: DeployState;
}

/**
 * DeployOverlay
 *
 * Tela de manutenção exibida durante deploys.
 * - NÃO desloga o usuário
 * - NÃO redireciona para /login
 * - Desaparece automaticamente quando o servidor voltar
 * - Animação de progresso indeterminado para sinalizar atividade
 */
export default function DeployOverlay({ state }: DeployOverlayProps) {
    const [dots, setDots] = useState('');

    // Animação de pontinhos no texto durante deploy
    useEffect(() => {
        if (state !== 'deploying') {
            setDots('');
            return;
        }
        const timer = setInterval(() => {
            setDots(d => (d.length >= 3 ? '' : d + '.'));
        }, 500);
        return () => clearInterval(timer);
    }, [state]);

    // Não renderiza nada quando está idle
    if (state === 'idle') return null;

    const isRecovering = state === 'recovering';

    return (
        <>
            <style>{`
                @keyframes deploy-slide {
                    0%   { transform: translateX(-100%); }
                    100% { transform: translateX(350%); }
                }
                @keyframes deploy-pulse {
                    0%, 100% { opacity: 1; }
                    50%       { opacity: 0.6; }
                }
                @keyframes deploy-spin {
                    to { transform: rotate(360deg); }
                }
                @keyframes deploy-fadein {
                    from { opacity: 0; transform: scale(0.95); }
                    to   { opacity: 1; transform: scale(1); }
                }
                .deploy-overlay {
                    position: fixed;
                    inset: 0;
                    z-index: 99999;
                    background: rgba(10, 15, 28, 0.94);
                    backdrop-filter: blur(12px);
                    -webkit-backdrop-filter: blur(12px);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: opacity 0.6s ease;
                }
                .deploy-card {
                    text-align: center;
                    color: #f1f5f9;
                    max-width: 420px;
                    padding: 48px 40px;
                    animation: deploy-fadein 0.4s ease;
                }
                .deploy-icon {
                    font-size: 52px;
                    margin-bottom: 20px;
                    display: block;
                }
                .deploy-title {
                    font-size: 22px;
                    font-weight: 700;
                    color: #f8fafc;
                    margin: 0 0 10px;
                    letter-spacing: -0.3px;
                }
                .deploy-subtitle {
                    font-size: 14px;
                    color: #94a3b8;
                    line-height: 1.7;
                    margin: 0 0 32px;
                }
                .deploy-bar-track {
                    height: 4px;
                    border-radius: 99px;
                    background: rgba(99, 102, 241, 0.2);
                    overflow: hidden;
                    margin: 0 auto;
                    width: 200px;
                }
                .deploy-bar-fill {
                    height: 100%;
                    width: 35%;
                    background: linear-gradient(90deg, #6366f1, #818cf8, #a5b4fc);
                    border-radius: 99px;
                    animation: deploy-slide 1.6s ease-in-out infinite;
                }
                .deploy-spinner {
                    width: 44px;
                    height: 44px;
                    border: 3px solid rgba(99, 102, 241, 0.25);
                    border-top-color: #6366f1;
                    border-radius: 50%;
                    animation: deploy-spin 0.9s linear infinite;
                    margin: 0 auto 20px;
                }
                .deploy-badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    background: rgba(99, 102, 241, 0.12);
                    border: 1px solid rgba(99, 102, 241, 0.3);
                    border-radius: 99px;
                    padding: 6px 14px;
                    font-size: 12px;
                    color: #a5b4fc;
                    margin-bottom: 28px;
                    animation: deploy-pulse 2s ease-in-out infinite;
                }
                .deploy-badge::before {
                    content: '';
                    width: 6px;
                    height: 6px;
                    border-radius: 50%;
                    background: #6366f1;
                    flex-shrink: 0;
                }
            `}</style>

            <div
                className="deploy-overlay"
                style={{ opacity: isRecovering ? 0 : 1 }}
                role="alert"
                aria-live="polite"
                aria-label="Sistema em atualização"
            >
                <div className="deploy-card">
                    {isRecovering ? (
                        /* Estado: Recuperado */
                        <>
                            <span className="deploy-icon">✅</span>
                            <h2 className="deploy-title">Sistema atualizado!</h2>
                            <p className="deploy-subtitle">
                                Continuando de onde você parou...
                            </p>
                        </>
                    ) : (
                        /* Estado: Deploy em andamento */
                        <>
                            <div className="deploy-spinner" aria-hidden="true" />
                            <div className="deploy-badge">Deploy em andamento</div>
                            <h2 className="deploy-title">
                                Atualizando o sistema{dots}
                            </h2>
                            <p className="deploy-subtitle">
                                Estamos implantando melhorias.<br />
                                Sua sessão será mantida e você voltará<br />
                                à mesma página automaticamente.
                            </p>
                            <div className="deploy-bar-track" aria-hidden="true">
                                <div className="deploy-bar-fill" />
                            </div>
                        </>
                    )}
                </div>
            </div>
        </>
    );
}
