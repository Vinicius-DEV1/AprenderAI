import { useEffect } from 'react';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { useConfig } from './hooks/useConfig';
import { useAuthStore } from './stores/authStore';
import { getUser } from './api/auth';
import { Toaster } from 'sonner';

// Layouts & Auth
import LoginPage from './pages/auth/LoginPage';
import RegisterPage from './pages/auth/RegisterPage';
import ForgotPassword from './pages/auth/ForgotPassword';
import ResetPassword from './pages/auth/ResetPassword';
import NotFound from './pages/errors/NotFound';
import ServerError from './pages/errors/ServerError';
import PrivateRoute from './components/PrivateRoute';
import AppLayout from './layouts/AppLayout';

// Module Pages
import HomePage from './pages/HomePage';
import Dashboard from './pages/Dashboard';
import Profile from './pages/Profile';
import MetaTags from './components/MetaTags';

import SimulationList from './pages/simulations/SimulationList';
import SimulationCreate from './pages/simulations/SimulationCreate';
import SimulationView from './pages/simulations/SimulationView';
import SimulationResult from './pages/simulations/SimulationResult';
import StudyPlanDashboard from './pages/study_plans/StudyPlanDashboard';
import ConcursoList from './pages/concursos/ConcursoList';
import PrivacyPolicy from './pages/legal/PrivacyPolicy';
import FairUsePolicy from './pages/legal/FairUsePolicy';

import EssayList from './pages/essays/EssayList';
import EssayWrite from './pages/essays/EssayWrite';
import EssayReview from './pages/essays/EssayReview';

import QuestionBank from './pages/questions/QuestionBank';
import PlanList from './pages/plans/PlanList';
import PlanCheckout from './pages/plans/PlanCheckout';

// Admin Pages
import AdminLayout from './layouts/AdminLayout';
import AdminDashboard from './pages/admin/AdminDashboard';
import Curadoria from './pages/admin/Curadoria';
import AdminQuestions from './pages/admin/AdminQuestions';
import AdminQuestionForm from './pages/admin/QuestionForm';
import AdminUsers from './pages/admin/AdminUsers';
import AdminUserDetail from './pages/admin/UserDetail';
import AdminApiKeys from './pages/admin/AdminApiKeys';
import AdminPlans from './pages/admin/Plans';
import AdminPlanForm from './pages/admin/PlanForm';
import AdminCoupons from './pages/admin/Coupons';
import AdminCouponForm from './pages/admin/CouponForm';
import AdminPrompts from './pages/admin/PromptsIndex';
import AdminPromptEditor from './pages/admin/PromptEditor';
import AdminSettings from './pages/admin/CacheSettings';
import AdminPaymentSettings from './pages/admin/PaymentSettings';
import AdminImport from './pages/admin/ImportIndex';
import AdminImportReviewIndex from './pages/admin/ImportReviewIndex';
import AdminImportReview from './pages/admin/ImportReview';
import EnemImport from './pages/admin/EnemImport';
import AdminAnalytics from './pages/admin/Analytics';
import AdminMonitor from './pages/admin/Monitor';
import AdminIntegrations from './pages/admin/Integrations';
import AdminChatLogs from './pages/admin/AdminChatLogs';

function App() {
    const { isLoading: configLoading, error: configError } = useConfig();
    const setUser = useAuthStore((state) => state.setUser);
    const setLoading = useAuthStore((state) => state.setLoading);

    useEffect(() => {
        const checkAuthStatus = async () => {
            try {
                const response = await getUser();
                setUser(response.data.user);
            } catch (error) {
                console.warn('Auth check failed:', error);
                setUser(null);
            } finally {
                setLoading(false);
            }
        };

        checkAuthStatus();
    }, [setUser, setLoading]);

    // Global loading states for bootstrapped config
    if (configLoading) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-slate-50 dark:bg-slate-900">
                <div className="flex flex-col items-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mb-4"></div>
                    <p className="text-slate-600 dark:text-slate-400 font-medium">Iniciando sistema...</p>
                </div>
            </div>
        );
    }

    if (configError) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-slate-50 dark:bg-slate-900 p-4">
                <div className="bg-white dark:bg-slate-800 p-8 rounded-2xl shadow-xl max-w-md w-full border border-red-100 dark:border-red-900/30">
                    <div className="w-16 h-16 bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">⚠️</div>
                    <h2 className="text-xl font-bold text-center text-slate-800 dark:text-slate-100 mb-2">Erro de Conexão</h2>
                    <p className="text-center text-slate-600 dark:text-slate-400 mb-6">Não foi possível conectar à API. Verifique sua conexão e tente novamente.</p>
                    <p className="text-center text-sm font-mono text-red-500 mb-6 bg-red-50 dark:bg-red-900/10 p-2 rounded">{(configError as any).message}</p>
                    <button onClick={() => window.location.reload()} className="w-full bg-indigo-600 text-white font-bold py-3 px-4 rounded-xl hover:bg-indigo-700 transition">
                        Tentar Novamente
                    </button>
                </div>
            </div>
        );
    }

    return (
        <BrowserRouter>
            <Toaster position="top-right" richColors />
            <Routes>
                {/* Public / Auth Routes */}
                <Route path="/" element={<><MetaTags title="Início" description="Prepare-se para o ENEM e concursos com IA." /><HomePage /></>} />
                <Route path="/login" element={<><MetaTags title="Login" /><LoginPage /></>} />
                <Route path="/register" element={<><MetaTags title="Criar Conta" /><RegisterPage /></>} />
                <Route path="/forgot-password" element={<><MetaTags title="Recuperar Senha" /><ForgotPassword /></>} />
                <Route path="/reset-password" element={<><MetaTags title="Redefinir Senha" /><ResetPassword /></>} />
                <Route path="/privacidade" element={<><MetaTags title="Política de Privacidade" /><PrivacyPolicy /></>} />
                <Route path="/uso-justo" element={<><MetaTags title="Termos de Uso" /><FairUsePolicy /></>} />

                <Route path="/500" element={<><MetaTags title="Erro no Servidor" /><ServerError /></>} />
                <Route path="*" element={<><MetaTags title="Página Não Encontrada" /><NotFound /></>} />

                {/* Protected App Routes */}
                <Route element={<PrivateRoute />}>
                    <Route element={<AppLayout />}>
                        {/* Dashboard */}
                        <Route path="/dashboard" element={<><MetaTags title="Dashboard" /><Dashboard /></>} />

                        {/* Simulations */}
                        <Route path="/simulations" element={<><MetaTags title="Minhas Provas" /><SimulationList /></>} />
                        <Route path="/simulations/create" element={<><MetaTags title="Configurar Simulado" /><SimulationCreate /></>} />
                        <Route path="/simulations/:id" element={<SimulationView />} />
                        <Route path="/simulations/:id/result" element={<><MetaTags title="Resultado do Simulado" /><SimulationResult /></>} />
                        <Route path="/study-plan" element={<><MetaTags title="Plano de Estudos" /><StudyPlanDashboard /></>} />
                        <Route path="/concursos" element={<><MetaTags title="Radar de Concursos" /><ConcursoList /></>} />

                        {/* Essays */}
                        <Route path="/essays" element={<><MetaTags title="Minhas Redações" /><EssayList /></>} />
                        <Route path="/essays/create" element={<><MetaTags title="Escrever Redação" /><EssayWrite /></>} />
                        <Route path="/essays/:id" element={<><MetaTags title="Correção de Redação" /><EssayReview /></>} />

                        {/* Question Bank */}
                        <Route path="/questions" element={<><MetaTags title="Banco de Questões" /><QuestionBank /></>} />

                        {/* Plans */}
                        <Route path="/plans" element={<><MetaTags title="Planos e Preços" /><PlanList /></>} />
                        <Route path="/plans/:planId/checkout" element={<><MetaTags title="Checkout" /><PlanCheckout /></>} />

                        {/* Profile */}
                        <Route path="/profile" element={<><MetaTags title="Meu Perfil" /><Profile /></>} />
                    </Route>

                    {/* Admin Portal */}
                    <Route path="/admin" element={<AdminLayout />}>
                        <Route index element={<><MetaTags title="Admin: Dashboard" /><AdminDashboard /></>} />
                        <Route path="dashboard" element={<><MetaTags title="Admin: Dashboard" /><AdminDashboard /></>} />
                        <Route path="curadoria" element={<><MetaTags title="Admin: Curadoria" /><Curadoria /></>} />

                        <Route path="questions" element={<><MetaTags title="Admin: Banco de Questões" /><AdminQuestions /></>} />
                        <Route path="questions/create" element={<><MetaTags title="Admin: Nova Questão" /><AdminQuestionForm /></>} />
                        <Route path="questions/:id/edit" element={<><MetaTags title="Admin: Editar Questão" /><AdminQuestionForm /></>} />

                        <Route path="users" element={<><MetaTags title="Admin: Gestão de Usuários" /><AdminUsers /></>} />
                        <Route path="users/:id" element={<><MetaTags title="Admin: Detalhes do Usuário" /><AdminUserDetail /></>} />

                        <Route path="plans" element={<><MetaTags title="Admin: Planos" /><AdminPlans /></>} />
                        <Route path="plans/create" element={<><MetaTags title="Admin: Novo Plano" /><AdminPlanForm /></>} />
                        <Route path="plans/:id/edit" element={<><MetaTags title="Admin: Editar Plano" /><AdminPlanForm /></>} />

                        <Route path="coupons" element={<><MetaTags title="Admin: Cupons" /><AdminCoupons /></>} />
                        <Route path="coupons/create" element={<><MetaTags title="Admin: Novo Cupom" /><AdminCouponForm /></>} />
                        <Route path="coupons/:id/edit" element={<><MetaTags title="Admin: Editar Cupom" /><AdminCouponForm /></>} />

                        <Route path="prompts" element={<><MetaTags title="Admin: Prompts" /><AdminPrompts /></>} />
                        <Route path="prompts/:id/edit" element={<><MetaTags title="Admin: Editar Prompt" /><AdminPromptEditor /></>} />

                        <Route path="settings" element={<><MetaTags title="Admin: Configurações" /><AdminSettings /></>} />
                        <Route path="payment-settings" element={<><MetaTags title="Admin: Pagamentos" /><AdminPaymentSettings /></>} />
                        <Route path="api-keys" element={<><MetaTags title="Admin: Chaves de API" /><AdminApiKeys /></>} />

                        <Route path="enem-import" element={<><MetaTags title="Admin: Importação ENEM" /><EnemImport /></>} />
                        <Route path="import" element={<><MetaTags title="Admin: Importação" /><AdminImport /></>} />
                        <Route path="import/review" element={<><MetaTags title="Admin: Revisão - Lista" /><AdminImportReviewIndex /></>} />
                        <Route path="import/review/:id" element={<><MetaTags title="Admin: Inspeção de Questão" /><AdminImportReview /></>} />

                        <Route path="analytics" element={<><MetaTags title="Admin: Analytics" /><AdminAnalytics /></>} />
                        <Route path="monitor" element={<><MetaTags title="Admin: Monitoramento" /><AdminMonitor /></>} />
                        <Route path="integrations" element={<><MetaTags title="Admin: Integrações" /><AdminIntegrations /></>} />
                        <Route path="chat-logs/:id" element={<><MetaTags title="Admin: Auditoria IA" /><AdminChatLogs /></>} />
                    </Route>
                </Route>
            </Routes>
        </BrowserRouter>
    );
}

export default App;
