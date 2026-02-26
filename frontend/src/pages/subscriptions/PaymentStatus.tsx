import { useEffect, useState } from 'react';
import { useSearchParams, useNavigate, Link } from 'react-router-dom';
import api from '../../api/axios';

export default function PaymentStatus() {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const status = searchParams.get('status') || 'pending'; // success, failure, pending

    // States for 'pending' status resolving PIX
    const [pixPayload, setPixPayload] = useState<string | null>(searchParams.get('pix_payload'));
    const [pixImage, setPixImage] = useState<string | null>(searchParams.get('pix_image'));
    const [isChecking, setIsChecking] = useState(false);
    const [copyFeedback, setCopyFeedback] = useState(false);

    // Simulated check for pending status
    useEffect(() => {
        let interval: NodeJS.Timeout;
        if (status === 'pending') {
            interval = setInterval(async () => {
                if (isChecking) return;
                setIsChecking(true);
                try {
                    const res = await api.get('/api/v1/plans/check-status');
                    if (res.data.active) {
                        navigate('/payment-status?status=success', { replace: true });
                    }
                } catch (e) {
                    console.error('Error checking status:', e);
                } finally {
                    setIsChecking(false);
                }
            }, 5000);
        }
        return () => clearInterval(interval);
    }, [status, navigate, isChecking]);

    const copyPixCode = () => {
        if (pixPayload) {
            navigator.clipboard.writeText(pixPayload);
            setCopyFeedback(true);
            setTimeout(() => setCopyFeedback(false), 3000);
        }
    };

    if (status === 'success') {
        return (
            <div className="py-12 bg-gray-50 min-h-screen">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center max-w-2xl mx-auto border border-gray-100">
                        <div className="mb-4 text-green-500">
                            <svg className="h-16 w-16 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <h2 className="text-2xl font-bold mb-2 text-gray-800">Pagamento Concluído!</h2>
                        <p className="text-gray-600 mb-6">Sua assinatura foi ativada com sucesso. Aproveite todos os recursos.</p>
                        <Link to="/panel" className="inline-block px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-medium">
                            Ir para o Dashboard
                        </Link>
                    </div>
                </div>
            </div>
        );
    }

    if (status === 'failure') {
        return (
            <div className="py-12 bg-gray-50 min-h-screen">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center max-w-2xl mx-auto border border-gray-100">
                        <div className="mb-4 text-red-500">
                            <svg className="h-16 w-16 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </div>
                        <h2 className="text-2xl font-bold mb-2 text-gray-800">Pagamento Falhou ou Cancelado</h2>
                        <p className="text-gray-600 mb-6">Não foi possível processar seu pagamento. Tente novamente.</p>
                        <Link to="/plans" className="inline-block px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 font-medium">
                            Tentar Outro Plano
                        </Link>
                    </div>
                </div>
            </div>
        );
    }

    // Pending Status (Default)
    return (
        <div className="py-12 bg-gray-50 min-h-screen">
            <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center max-w-2xl mx-auto border border-gray-100">
                    {pixPayload ? (
                        <>
                            <div className="mb-4 text-green-500">
                                <svg className="h-16 w-16 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                </svg>
                            </div>
                            <h2 className="text-2xl font-bold mb-2 text-gray-800">Pagamento via Pix Gerado!</h2>
                            <p className="text-gray-600 mb-6">Escaneie o QR Code abaixo ou use o código Copia e Cola para finalizar.</p>

                            {pixImage && (
                                <div className="flex justify-center mb-6">
                                    <img src={`data:image/jpeg;base64,${pixImage}`} alt="QR Code Pix" className="border p-2 rounded-lg shadow-sm max-w-[300px]" />
                                </div>
                            )}

                            <div className="max-w-xl mx-auto mb-6">
                                <label className="block text-sm font-medium text-gray-700 mb-2">Pix Copia e Cola</label>
                                <div className="flex">
                                    <input
                                        type="text"
                                        readOnly
                                        value={pixPayload}
                                        className="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-l-md bg-gray-50"
                                    />
                                    <button
                                        onClick={copyPixCode}
                                        className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-r-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Copiar
                                    </button>
                                </div>
                                {copyFeedback && <p className="text-sm text-green-600 mt-2">Código copiado com sucesso!</p>}
                            </div>
                        </>
                    ) : (
                        <>
                            <div className="mb-4 text-yellow-500">
                                <svg className="h-16 w-16 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <h2 className="text-2xl font-bold mb-2 text-gray-800">Pagamento Pendente</h2>
                            <p className="text-gray-600 mb-6">
                                Estamos processando seu pagamento. Assim que confirmado, seu plano será ativado.
                            </p>
                        </>
                    )}

                    <div className="mb-6 flex flex-col gap-3 items-center">
                        <button onClick={() => window.location.reload()} className="text-blue-600 font-semibold hover:underline bg-transparent border-none cursor-pointer">
                            {pixPayload ? 'Já paguei? Clique aqui para atualizar' : 'Clique aqui para verificar status agora'}
                        </button>
                    </div>

                    <Link to="/panel" className="inline-block px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 font-medium">
                        Voltar ao Dashboard
                    </Link>
                </div>
            </div>
        </div>
    );
}
