import { useEffect } from 'react';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { useConfig } from './hooks/useConfig';
import { useAuthStore } from './stores/authStore';
import { getUser } from './api/auth';

// Layouts & Auth
import LoginPage from './pages/auth/LoginPage';
import RegisterPage from './pages/auth/RegisterPage';
import PrivateRoute from './components/PrivateRoute';
import AppLayout from './layouts/AppLayout';

// Module Pages
import HomePage from './pages/HomePage';
import Dashboard from './pages/Dashboard';
import Profile from './pages/Profile';

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
import AdminUsers from './pages/admin/AdminUsers';
import AdminApiKeys from './pages/admin/AdminApiKeys';

function App() {
    const { isLoading: configLoading, error: configError } = useConfig();
    const setUser = useAuthStore((state) => state.setUser);
    const setLoading = useAuthStore((state) => state.setLoading);

    useEffect(() => {
        const checkAuthStatus = async () => {
            console.log('Checking auth status...');
            try {
                const response = await getUser();
                console.log('Auth check response:', response.data);
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
            <Routes>
                {/* Public / Auth Routes */}
                <Route path="/" element={<HomePage />} />
                <Route path="/login" element={<LoginPage />} />
                <Route path="/register" element={<RegisterPage />} />
                <Route path="/privacidade" element={<PrivacyPolicy />} />
                <Route path="/uso-justo" element={<FairUsePolicy />} />

                {/* Protected App Routes */}
                <Route element={<PrivateRoute />}>
                    <Route element={<AppLayout />}>
                        {/* Dashboard */}
                        <Route path="/dashboard" element={<Dashboard />} />

                        {/* Simulations */}
                        <Route path="/simulations" element={<SimulationList />} />
                        <Route path="/simulations/create" element={<SimulationCreate />} />
                        <Route path="/simulations/:id" element={<SimulationView />} />
                        <Route path="/simulations/:id/result" element={<SimulationResult />} />
                        <Route path="/study-plan" element={<StudyPlanDashboard />} />
                        <Route path="/concursos" element={<ConcursoList />} />

                        {/* Essays */}
                        <Route path="/essays" element={<EssayList />} />
                        <Route path="/essays/create" element={<EssayWrite />} />
                        <Route path="/essays/:id" element={<EssayReview />} />

                        {/* Question Bank */}
                        <Route path="/questions" element={<QuestionBank />} />

                        {/* Plans */}
                        <Route path="/plans" element={<PlanList />} />
                        <Route path="/plans/:planId/checkout" element={<PlanCheckout />} />

                        {/* Profile */}
                        <Route path="/profile" element={<Profile />} />
                    </Route>

                    {/* Admin Portal */}
                    <Route path="/admin" element={<AdminLayout />}>
                        <Route index element={<AdminDashboard />} />
                        <Route path="dashboard" element={<AdminDashboard />} />
                        <Route path="curadoria" element={<Curadoria />} />
                        <Route path="questions" element={<AdminQuestions />} />
                        <Route path="users" element={<AdminUsers />} />
                        <Route path="api-keys" element={<AdminApiKeys />} />
                        {/* Outras rotas administrativas serão adicionadas aqui */}
                    </Route>
                </Route>
            </Routes>
        </BrowserRouter>
    );
}

export default App;
