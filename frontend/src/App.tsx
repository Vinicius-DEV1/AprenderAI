import { useEffect, useState } from 'react';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { HelmetProvider } from 'react-helmet-async';
import { useConfig } from './hooks/useConfig';
import { useAuthStore } from './stores/authStore';
import { getUser, getCsrfCookie } from './api/auth';
import { Toaster } from 'sonner';
import { setBootstrapping } from './api/axios';

// Layouts & Auth
import LoginPage from './pages/auth/LoginPage';
import RegisterPage from './pages/auth/RegisterPage';
import ForgotPassword from './pages/auth/ForgotPassword';
import ResetPassword from './pages/auth/ResetPassword';
import VerifyEmail from './pages/auth/VerifyEmail';
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

import PlanList from './pages/plans/PlanList';
import WelcomePlans from './pages/plans/WelcomePlans';
import PlanCheckout from './pages/plans/PlanCheckout';
import PlanSuccess from './pages/plans/PlanSuccess';

// Notebooks
import NotebookList from './pages/notebooks/NotebookList';

import QuestionBank from './pages/questions/QuestionBank';

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
import AdminAnalyticsBehavior from './pages/admin/analytics/Behavior';
import AdminAnalyticsAcquisition from './pages/admin/analytics/Acquisition';
import AdminAnalyticsConversion from './pages/admin/analytics/Conversion';
import AdminAnalyticsMonetization from './pages/admin/analytics/Monetization';
import AdminMonitor from './pages/admin/Monitor';
import AdminIntegrations from './pages/admin/Integrations';
import AdminChatLogs from './pages/admin/AdminChatLogs';
import AdminApiPricing from './pages/admin/ApiPricing';
import AdminSimulationBuilder from './pages/admin/SimulationBuilder';
import AdminSubscriptions from './pages/admin/AdminSubscriptions';
import AdminXavierInsights from './pages/admin/XavierInsights';
import AdminExamsList from './pages/admin/exams/AdminExamsList';
import AdminExamDetails from './pages/admin/exams/AdminExamDetails';
import Analytics from './components/Analytics';

function App() {
    // Unificar o estado de carregamento inicial para evitar transições bruscas e race conditions.
    const { isLoading: configLoading, isError: configError } = useConfig();
    const { setUser, setLoading: setAuthLoading, isLoading: authLoading } = useAuthStore();
    const [bootstrapTimedOut, setBootstrapTimedOut] = useState(false);

    useEffect(() => {
        const checkAuthStatus = async () => {
            try {
                // Garante que o cookie de sessão existe antes de verificar auth
                await getCsrfCookie();
                const response = await getUser();
                if (response.data && response.data.user) {
                    setUser(response.data.user);
                } else {
                    setUser(null);
                }
            } catch {
                setUser(null);
            } finally {
                // Desativa as flags de bootstrap APENAS após o término real
                setBootstrapping(false);
                setAuthLoading(false);
            }
        };

        checkAuthStatus();
    }, [setUser, setAuthLoading]);

    // Timeout de segurança — nunca deixa o spinner bloquear permanentemente
    useEffect(() => {
        const timer = setTimeout(() => {
            setBootstrapTimedOut(true);
            // Garante que o loading do auth também é desativado
            setAuthLoading(false);
            setBootstrapping(false);
        }, 8000);
        return () => clearTimeout(timer);
    }, [setAuthLoading]);

    // Mostra spinner unificado enquanto carrega config OU auth
    // O spinner é desbloqueado se: ambos terminaram, ou houve erro no config, ou timeout
    const isBootstrapping = (configLoading || authLoading) && !bootstrapTimedOut && !configError;
    if (isBootstrapping) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-slate-50 dark:bg-slate-900">
                <div className="flex flex-col items-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mb-4"></div>
                    <p className="text-slate-600 dark:text-slate-400 font-medium italic">Iniciando sistema...</p>
                </div>
            </div>
        );
    }

    return (
        <HelmetProvider>
            <BrowserRouter>
                <Analytics />
                <Toaster position="top-right" richColors />
                <Routes>
                    {/* Public / Auth Routes */}
                    <Route path="/" element={<><MetaTags title="Início" description="Prepare-se para o ENEM e concursos com IA." /><HomePage /></>} />
                    <Route path="/login" element={<><MetaTags title="Login" /><LoginPage /></>} />
                    <Route path="/register" element={<><MetaTags title="Criar Conta" /><RegisterPage /></>} />
                    <Route path="/forgot-password" element={<><MetaTags title="Recuperar Senha" /><ForgotPassword /></>} />
                    <Route path="/reset-password" element={<><MetaTags title="Redefinir Senha" /><ResetPassword /></>} />
                    <Route path="/verify-email" element={<><MetaTags title="Verificação de E-mail" /><VerifyEmail /></>} />
                    <Route path="/privacidade" element={<><MetaTags title="Política de Privacidade" /><PrivacyPolicy /></>} />
                    <Route path="/uso-justo" element={<><MetaTags title="Termos de Uso" /><FairUsePolicy /></>} />

                    <Route path="/500" element={<><MetaTags title="Erro no Servidor" /><ServerError /></>} />
                    <Route path="*" element={<><MetaTags title="Página Não Encontrada" /><NotFound /></>} />

                    {/* Protected App Routes */}
                    <Route element={<PrivateRoute />}>
                        <Route path="/welcome" element={<><MetaTags title="Bem-vindo(a)!" /><WelcomePlans /></>} />

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

                            {/* Question Bank & Notebooks */}
                            <Route path="/questions" element={<><MetaTags title="Banco de Questões" /><QuestionBank /></>} />
                            <Route path="/notebooks" element={<><MetaTags title="Meus Cadernos" /><NotebookList /></>} />

                            {/* Plans */}
                            <Route path="/plans" element={<><MetaTags title="Planos e Preços" /><PlanList /></>} />
                            <Route path="/plans/:planId/checkout" element={<><MetaTags title="Checkout" /><PlanCheckout /></>} />
                            <Route path="/checkout/success" element={<><MetaTags title="Pagamento Confirmado" /><PlanSuccess /></>} />

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
                            <Route path="analytics/behavior" element={<><MetaTags title="Admin: Comportamento" /><AdminAnalyticsBehavior /></>} />
                            <Route path="analytics/acquisition" element={<><MetaTags title="Admin: Aquisição" /><AdminAnalyticsAcquisition /></>} />
                            <Route path="analytics/conversion" element={<><MetaTags title="Admin: Conversão" /><AdminAnalyticsConversion /></>} />
                            <Route path="analytics/monetization" element={<><MetaTags title="Admin: Monetização" /><AdminAnalyticsMonetization /></>} />
                            <Route path="monitor" element={<><MetaTags title="Admin: Monitoramento" /><AdminMonitor /></>} />
                            <Route path="integrations" element={<><MetaTags title="Admin: Integrações" /><AdminIntegrations /></>} />
                            <Route path="chat-logs/:id" element={<><MetaTags title="Admin: Auditoria IA" /><AdminChatLogs /></>} />
                            <Route path="api-pricing" element={<><MetaTags title="Admin: Custos de API" /><AdminApiPricing /></>} />
                            <Route path="subscriptions" element={<><MetaTags title="Admin: Assinaturas" /><AdminSubscriptions /></>} />
                            <Route path="simulations/builder" element={<><MetaTags title="Admin: Motor de Simulados" /><AdminSimulationBuilder /></>} />
                            <Route path="xavier/insights" element={<><MetaTags title="Admin: Xavier Insights" /><AdminXavierInsights /></>} />

                            {/* Exams (Provas) */}
                            <Route path="provas" element={<><MetaTags title="Admin: Provas (PDFs)" /><AdminExamsList /></>} />
                            <Route path="provas/:id" element={<><MetaTags title="Admin: Detalhes da Prova" /><AdminExamDetails /></>} />

                        </Route>
                    </Route>
                </Routes>
            </BrowserRouter>
        </HelmetProvider>
    );
}

export default App;
