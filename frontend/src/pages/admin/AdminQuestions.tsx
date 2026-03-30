import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../api/axios';
import { AdminPageSkeleton } from './components/AdminSkeletons';
import QuestionBankExplorerModal from './components/QuestionBankExplorerModal';
import { AnimatePresence } from 'framer-motion';
import { useUIStore } from '../../stores/uiStore';
import AdminDeleteQuestionModal from './components/AdminDeleteQuestionModal';
import SmartPagination from '../../components/admin/SmartPagination';

// Modular Components
import QuestionStatsCards from './questions/QuestionStatsCards';
import AITriagePanel from './questions/AITriagePanel';
import QuestionBank from './questions/QuestionBank';

export default function AdminQuestions() {
    const queryClient = useQueryClient();

    // Filters State
    const [triageFilters, setTriageFilters] = useState({
        triage_search: '',
        triage_status: '',
        triage_subject: '',
        triage_organization: ''
    });

    const [filters, setFilters] = useState({
        search: '',
        subject: '',
        source: '',
        organization: ''
    });

    const [page, setPage] = useState(1);
    const [triagePage, setTriagePage] = useState(1);
    const [removingIds, setRemovingIds] = useState<number[]>([]);
    const [activeMenu, setActiveMenu] = useState<number | null>(null);
    const [mainActiveMenu, setMainActiveMenu] = useState<number | null>(null);
    const [deleteModal, setDeleteModal] = useState<{ isOpen: boolean, id: number | null }>({ isOpen: false, id: null });
    const [explorerOrg, setExplorerOrg] = useState<string | null>(null);
    const ui = useUIStore();

    // Reset pagination when filters change
    useEffect(() => {
        setPage(1);
    }, [filters]);

    useEffect(() => {
        setTriagePage(1);
    }, [triageFilters]);

    // NEW STATES FOR REPORTS TABS
    const [activeTab, setActiveTab] = useState<'all' | 'reported' | 'trashed' | 'batch_history'>('all');
    const [reportsPage, setReportsPage] = useState(1);
    const [trashedPage, setTrashedPage] = useState(1);
    const [batchHistoryPage, setBatchHistoryPage] = useState(1);
    const [trashedSearch, setTrashedSearch] = useState('');

    const { data, isLoading } = useQuery({
        queryKey: ['admin-questions', filters, page, triageFilters, triagePage],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/questions', {
                params: { ...filters, ...triageFilters, page, triage_page: triagePage }
            });
            return res.data;
        }
    });

    const adminActions = useMutation({
        mutationFn: async ({ id, action }: { id: number, action: string }) => {
            const res = await api.post(`/api/v1/admin/questions/${id}/${action}`);
            return res.data;
        },
        onSuccess: (_, variables) => {
            // Animacao de saida
            setRemovingIds(prev => [...prev, variables.id]);
            setTimeout(() => {
                queryClient.invalidateQueries({ queryKey: ['admin-questions'] });
                setRemovingIds(prev => prev.filter(rid => rid !== variables.id));
            }, 600);
        }
    });

    const { data: reportsData, isLoading: reportsLoading } = useQuery({
        queryKey: ['admin-reports', reportsPage],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/question-reports', {
                params: { page: reportsPage, status: 'pending' }
            });
            return res.data;
        },
        enabled: activeTab === 'reported'
    });

    const reportActions = useMutation({
        mutationFn: async ({ id, action }: { id: number, action: 'resolve' | 'deactivate' }) => {
            const url = action === 'resolve'
                ? `/api/v1/admin/question-reports/${id}/resolve`
                : `/api/v1/admin/questions/${id}/deactivate`;
            const res = await api.post(url);
            return res.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-reports'] });
            queryClient.invalidateQueries({ queryKey: ['admin-questions'] });
        }
    });

    const { data: trashedData, isLoading: trashedLoading } = useQuery({
        queryKey: ['admin-trashed', trashedPage, trashedSearch],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/questions/trashed', {
                params: { page: trashedPage, search: trashedSearch }
            });
            return res.data;
        },
        enabled: activeTab === 'trashed'
    });

    const trashedActions = useMutation({
        mutationFn: async ({ id, action }: { id: number, action: 'restore' | 'force' }) => {
            if (action === 'restore') {
                return (await api.post(`/api/v1/admin/questions/${id}/restore`)).data;
            } else {
                return (await api.delete(`/api/v1/admin/questions/${id}/force`)).data;
            }
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-trashed'] });
            queryClient.invalidateQueries({ queryKey: ['admin-questions'] });
        }
    });

    if (isLoading) return <AdminPageSkeleton />;
    if (!data) return <div className="p-8 text-center text-red-500">Erro ao carregar banco de questões.</div>;

    const questions = data.questions || { data: [], total: 0 };
    const pendingQuestions = data.pendingQuestions || { data: [] };
    const meta = { total_questions: 0, ai_questions: 0, published_questions: 0, ...(data.meta || {}) };
    const counts = data.counts || { pending_total: 0, missing_difficulty: 0, missing_explanation: 0, missing_classification: 0, both_missing: 0 };
    const availableSubjects = data.availableSubjects || [];
    const availableOrganizations = data.availableOrganizations || [];

    const stats = [
        { label: 'Total Geral', value: meta.total_questions, color: 'indigo', org: null },
        { label: 'Total Aprovadas', value: meta.published_questions, color: 'emerald', org: null },
        { label: 'Inéditas IA', value: meta.ai_questions, color: 'purple', org: null },
        ...(meta.questions_by_organization || []).map((org: any) => ({
            label: org.organization,
            value: org.total,
            color: 'blue',
            org: org.organization
        }))
    ];

    return (
        <div className="p-4 md:p-6 w-full space-y-6 animate-in fade-in duration-500 bg-gray-50/30 min-h-screen">
            {/* Header */}
            <div className="flex justify-between items-end">
                <div>
                    <h1 className="text-3xl font-black text-gray-900 tracking-tight">Banco de Questões</h1>
                    <p className="text-gray-500 font-medium">Gestão centralizada de conteúdo e triagem de inteligência artificial.</p>
                </div>
                <div className="flex items-center gap-3">
                    <Link to="/admin/questions/ranking" className="px-6 py-3 bg-white border border-indigo-100 text-indigo-600 rounded-xl font-bold flex items-center gap-2 hover:bg-slate-50 transition shadow-sm">
                        <span>📈</span> Disciplinas/Assuntos
                    </Link>
                    <Link to="/admin/questions/create" className="px-6 py-3 bg-indigo-600 text-white rounded-xl font-bold flex items-center gap-2 hover:bg-indigo-700 transition shadow-lg shadow-indigo-100">
                        <span>➕</span> Nova Questão
                    </Link>
                </div>

            </div>

            {/* Mini Dashboard */}
            <QuestionStatsCards stats={stats} onOrgClick={setExplorerOrg} />

            {/* AI TRIAGE SECTION */}
            <AITriagePanel
                counts={counts}
                triageFilters={triageFilters}
                setTriageFilters={setTriageFilters}
                availableSubjects={availableSubjects}
                pendingQuestions={pendingQuestions}
                removingIds={removingIds}
                activeMenu={activeMenu}
                setActiveMenu={setActiveMenu}
                adminActions={adminActions}
                setDeleteModal={setDeleteModal}
                ui={ui}
                triagePage={triagePage}
                setTriagePage={setTriagePage}
                SmartPagination={SmartPagination}
            />

            {/* MAIN QUESTION BANK */}
            <QuestionBank
                activeTab={activeTab}
                setActiveTab={setActiveTab}
                filters={filters}
                setFilters={setFilters}
                availableSubjects={availableSubjects}
                availableOrganizations={availableOrganizations}
                questions={questions}
                reportsData={reportsData}
                reportsLoading={reportsLoading}
                trashedData={trashedData}
                trashedLoading={trashedLoading}
                trashedSearch={trashedSearch}
                setTrashedSearch={setTrashedSearch}
                adminActions={adminActions}
                reportActions={reportActions}
                trashedActions={trashedActions}
                mainActiveMenu={mainActiveMenu}
                setMainActiveMenu={setMainActiveMenu}
                setDeleteModal={setDeleteModal}
                page={page}
                setPage={setPage}
                reportsPage={reportsPage}
                setReportsPage={setReportsPage}
                trashedPage={trashedPage}
                setTrashedPage={setTrashedPage}
                batchHistoryPage={batchHistoryPage}
                setBatchHistoryPage={setBatchHistoryPage}
                SmartPagination={SmartPagination}
                data={data}
            />

            <AdminDeleteQuestionModal
                isOpen={deleteModal.isOpen}
                onClose={() => setDeleteModal({ isOpen: false, id: null })}
                questionId={deleteModal.id}
                onDeleted={() => {
                    queryClient.invalidateQueries({ queryKey: ['admin-questions'] });
                }}
            />

            {/* Question Bank Explorer Modal */}
            <AnimatePresence>
                {explorerOrg && (
                    <QuestionBankExplorerModal
                        organization={explorerOrg}
                        onClose={() => setExplorerOrg(null)}
                    />
                )}
            </AnimatePresence>
        </div >
    );
}

