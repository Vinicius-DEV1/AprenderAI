import { useNavigate } from 'react-router-dom';
import { useState } from 'react';
import { useConfigStore } from '../../stores/configStore';
import { useAuthStore } from '../../stores/authStore';
import PlanConfirmationModal from '../../components/PlanConfirmationModal';

export default function PlanList() {
    const navigate = useNavigate();
    const { plans } = useConfigStore();
    const { user } = useAuthStore();

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [selectedPlanForModal, setSelectedPlanForModal] = useState<any>(null);

    const userPlanId = user?.plan_id;

    const handlePlanClick = (plan: any) => {
        setSelectedPlanForModal(plan);
        setIsModalOpen(true);
    };

    const handleConfirm = () => {
        if (selectedPlanForModal) {
            navigate(`/plans/${selectedPlanForModal.id}/checkout`);
        }
    };

    return (
        <div className="py-12">
            <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <div className="text-center mb-10">
                    <h3 className="text-3xl font-bold text-gray-900 dark:text-slate-100">Escolha o plano ideal para sua aprovação</h3>
                    <p className="mt-2 text-gray-600 dark:text-slate-400">Faça upgrade e desbloqueie correção detalhada por IA e planos de estudo.</p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
                    {plans?.map((plan: any) => (
                        <div
                            key={plan.id}
                            className={`bg-white dark:bg-slate-900 rounded-lg shadow-lg dark:shadow-none dark:border dark:border-slate-700 overflow-hidden flex flex-col ${userPlanId === plan.id ? 'border-2 border-blue-500 ring-2 ring-blue-200 dark:ring-blue-900' : ''}`}
                        >
                            {userPlanId === plan.id && (
                                <div className="bg-blue-500 text-white text-xs font-bold uppercase py-1 text-center">
                                    Seu Plano Atual
                                </div>
                            )}

                            <div className="p-8 flex-1">
                                <h4 className="text-2xl font-bold text-gray-900 dark:text-slate-100 text-center mb-4">{plan.name}</h4>
                                <div className="text-center mb-6">
                                    <span className="text-4xl font-extrabold text-blue-600 dark:text-blue-400">
                                        R$ {new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(plan.price)}
                                    </span>
                                    <span className="text-gray-500 dark:text-slate-400 ml-1">/mês</span>
                                </div>

                                <ul className="space-y-4 text-gray-600 dark:text-slate-300 mb-8">
                                    <li className="flex items-center">
                                        <svg className="h-5 w-5 text-green-500 dark:text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        {plan.simulations_limit > 0 ? `${plan.simulations_limit} provas mensais` : 'Provas Ilimitadas'}
                                    </li>
                                    <li className="flex items-center">
                                        <svg className="h-5 w-5 text-green-500 dark:text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        {plan.essays_limit > 0 ? `${plan.essays_limit} redações mensais` : 'Redações Ilimitadas'}
                                    </li>
                                    {plan.features?.includes('ai_correction_detailed') && (
                                        <li className="flex items-center">
                                            <svg className="h-5 w-5 text-green-500 dark:text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            Correção detalhada por IA
                                        </li>
                                    )}
                                    {plan.features?.includes('study_plan') && (
                                        <li className="flex items-center">
                                            <svg className="h-5 w-5 text-green-500 dark:text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            Plano de estudos personalizado
                                        </li>
                                    )}
                                </ul>
                            </div>

                            <div className="p-8 bg-gray-50 dark:bg-slate-800 border-t border-gray-100 dark:border-slate-700">
                                {userPlanId === plan.id ? (
                                    <button disabled className="w-full block text-center bg-gray-300 dark:bg-slate-600 text-gray-600 dark:text-slate-400 font-bold py-3 px-4 rounded cursor-not-allowed">
                                        Plano Atual
                                    </button>
                                ) : (
                                    <button
                                        onClick={() => handlePlanClick(plan)}
                                        className="w-full block text-center bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded transition duration-200"
                                    >
                                        {plan.price > 0 ? 'Assinar Agora' : 'Mudar para Gratuito'}
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>

                <div className="mt-12 text-center text-gray-500 dark:text-slate-400 text-sm">
                    <p>Pagamento seguro via Mercado Pago. Cancele quando quiser.</p>
                </div>

                <PlanConfirmationModal
                    isOpen={isModalOpen}
                    onClose={() => setIsModalOpen(false)}
                    onConfirm={handleConfirm}
                    currentPlan={plans?.find((p: any) => p.id === userPlanId)}
                    selectedPlan={selectedPlanForModal}
                />
            </div>
        </div>
    );
}
