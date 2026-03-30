import { AllQuestionsTable, ReportedQuestionsTable, TrashedQuestionsTable } from './QuestionBankTables';
import BatchHistoryTab from './BatchHistoryTab';

interface QuestionBankProps {
    activeTab: 'all' | 'reported' | 'trashed' | 'batch_history';
    setActiveTab: (tab: 'all' | 'reported' | 'trashed' | 'batch_history') => void;
    filters: any;
    setFilters: (filters: any) => void;
    availableSubjects: string[];
    availableOrganizations: string[];
    questions: any;
    reportsData: any;
    reportsLoading: boolean;
    trashedData: any;
    trashedLoading: boolean;
    trashedSearch: string;
    setTrashedSearch: (search: string) => void;
    adminActions: any;
    reportActions: any;
    trashedActions: any;
    mainActiveMenu: number | null;
    setMainActiveMenu: (id: number | null) => void;
    setDeleteModal: (modal: any) => void;
    page: number;
    setPage: (page: number) => void;
    reportsPage: number;
    setReportsPage: (page: number) => void;
    trashedPage: number;
    setTrashedPage: (page: number) => void;
    batchHistoryPage: number;
    setBatchHistoryPage: (page: number) => void;
    SmartPagination: any;
    data: any;
}

export default function QuestionBank({
    activeTab,
    setActiveTab,
    filters,
    setFilters,
    availableSubjects,
    availableOrganizations,
    questions,
    reportsData,
    reportsLoading,
    trashedData,
    trashedLoading,
    trashedSearch,
    setTrashedSearch,
    adminActions,
    reportActions,
    trashedActions,
    mainActiveMenu,
    setMainActiveMenu,
    setDeleteModal,
    page,
    setPage,
    reportsPage,
    setReportsPage,
    trashedPage,
    setTrashedPage,
    batchHistoryPage,
    setBatchHistoryPage,
    SmartPagination,
    data
}: QuestionBankProps) {
    return (
        <div className="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
            <div className="p-6 border-b border-gray-50 bg-gray-50/30 flex flex-wrap gap-4 items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="w-10 h-10 bg-white rounded-xl shadow-sm border border-gray-100 flex items-center justify-center text-lg">🏦</div>
                    <h2 className="text-xl font-black text-gray-900 flex items-center gap-2">
                        Qualidade do Acervo
                        {data.DEBUG_CODE_VERSION && (
                            <span className="text-[8px] bg-red-500 text-white px-1 rounded animate-pulse">
                                V_STRITO
                            </span>
                        )}
                    </h2>
                </div>

                <div className="flex bg-gray-100/80 p-1 rounded-xl">
                    <button
                        onClick={() => setActiveTab('all')}
                        className={`px-4 py-2 rounded-lg text-sm font-bold transition-all ${activeTab === 'all' ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}
                    >
                        Banco Completo
                    </button>
                    <button
                        onClick={() => setActiveTab('reported')}
                        className={`px-4 py-2 rounded-lg text-sm font-bold transition-all flex items-center gap-2 ${activeTab === 'reported' ? 'bg-white text-red-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}
                    >
                        Denunciadas
                        {reportsData?.total > 0 && (
                            <span className="bg-red-500 text-white text-[10px] px-1.5 py-0.5 rounded-full">{reportsData.total}</span>
                        )}
                    </button>
                    <button
                        onClick={() => setActiveTab('batch_history')}
                        className={`px-4 py-2 rounded-lg text-sm font-bold transition-all flex items-center gap-2 ${activeTab === 'batch_history' ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}
                    >
                        📊 Histórico de IA
                    </button>
                    <button
                        onClick={() => setActiveTab('trashed')}
                        className={`px-4 py-2 rounded-lg text-sm font-bold transition-all flex items-center gap-2 ${activeTab === 'trashed' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}
                    >
                        🗑️ Lixeira
                    </button>
                </div>
            </div>

            {activeTab === 'all' && (
                <div className="p-4 border-b border-gray-50 bg-white grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-2 items-center">
                    <input
                        name="search"
                        placeholder="Pesquisar..."
                        className="w-full px-4 py-2 bg-white rounded-xl text-sm font-bold border border-gray-200 focus:ring-2 focus:ring-indigo-500"
                        value={filters.search}
                        onChange={e => setFilters({ ...filters, search: e.target.value })}
                    />
                    <select
                        name="subject"
                        className="w-full px-4 py-2 bg-white rounded-xl text-sm font-bold border border-gray-200 focus:ring-2 focus:ring-indigo-500"
                        value={filters.subject}
                        onChange={e => setFilters({ ...filters, subject: e.target.value })}
                    >
                        <option value="">Todas as Matérias</option>
                        {availableSubjects.map((s: string) => <option key={s} value={s}>{s}</option>)}
                    </select>
                    <select
                        name="source"
                        className="w-full px-4 py-2 bg-white rounded-xl text-sm font-bold border border-gray-200 focus:ring-2 focus:ring-indigo-500"
                        value={filters.source}
                        onChange={e => setFilters({ ...filters, source: e.target.value })}
                    >
                        <option value="">Todas as Origens</option>
                        <option value="manual">Manual (ENEM)</option>
                        <option value="ai_generated">IA Gerada</option>
                    </select>
                    <select
                        name="organization"
                        className="w-full px-4 py-2 bg-white rounded-xl text-sm font-bold border border-gray-200 focus:ring-2 focus:ring-indigo-500"
                        value={filters.organization}
                        onChange={e => setFilters({ ...filters, organization: e.target.value })}
                    >
                        <option value="">Todas Organizações</option>
                        {availableOrganizations.map((o: string) => <option key={o} value={o}>{o}</option>)}
                    </select>
                </div>
            )}

            {activeTab === 'all' && (
                <>
                    <AllQuestionsTable 
                        questions={questions}
                        adminActions={adminActions}
                        mainActiveMenu={mainActiveMenu}
                        setMainActiveMenu={setMainActiveMenu}
                        setDeleteModal={setDeleteModal}
                    />
                    <div className="p-6 bg-gray-50/30 border-t border-gray-100 flex items-center justify-between">
                        <span className="text-xs font-black text-gray-400 uppercase">Total: {questions.total} questões</span>
                        <SmartPagination
                            currentPage={page}
                            lastPage={questions.last_page || 1}
                            onPageChange={setPage}
                        />
                    </div>
                </>
            )}

            {activeTab === 'reported' && (
                <>
                    {reportsLoading ? (
                        <div className="p-12 text-center text-gray-400 font-bold animate-pulse">Carregando denúncias...</div>
                    ) : (
                        <ReportedQuestionsTable 
                            reportsData={reportsData}
                            reportActions={reportActions}
                        />
                    )}
                    {reportsData && (
                        <div className="p-6 bg-red-50/30 border-t border-red-50 flex items-center justify-between">
                            <span className="text-xs font-black text-gray-500 uppercase">Total: {reportsData.total} relatadas</span>
                            <SmartPagination
                                currentPage={reportsPage}
                                lastPage={reportsData.last_page || 1}
                                onPageChange={setReportsPage}
                            />
                        </div>
                    )}
                </>
            )}

            {activeTab === 'trashed' && (
                <div className="p-4 border-b border-gray-50 bg-white flex flex-wrap gap-2 items-center justify-end">
                    <input
                        name="search"
                        placeholder="Buscar na Lixeira..."
                        className="px-4 py-2 bg-white rounded-xl text-sm font-bold border border-gray-200 focus:ring-2 focus:ring-indigo-500 min-w-[300px]"
                        value={trashedSearch}
                        onChange={e => setTrashedSearch(e.target.value)}
                    />
                </div>
            )}

            {activeTab === 'trashed' && (
                <>
                    {trashedLoading ? (
                        <div className="p-8 text-center text-gray-400 font-bold">Carregando lixeira...</div>
                    ) : (
                        <TrashedQuestionsTable 
                            trashedData={trashedData}
                            trashedActions={trashedActions}
                        />
                    )}
                    {trashedData?.questions && trashedData.questions.last_page > 1 && (
                        <div className="p-6 bg-gray-50/50 border-t border-gray-100 flex items-center justify-between">
                            <span className="text-xs font-black text-gray-500 uppercase">Página {trashedPage} de {trashedData.questions.last_page}</span>
                            <SmartPagination
                                currentPage={trashedPage}
                                lastPage={trashedData.questions.last_page || 1}
                                onPageChange={setTrashedPage}
                            />
                        </div>
                    )}
                </>
            )}

            {activeTab === 'batch_history' && (
                <BatchHistoryTab
                    batchHistoryPage={batchHistoryPage}
                    setBatchHistoryPage={setBatchHistoryPage}
                    SmartPagination={SmartPagination}
                />
            )}
        </div>
    );
}
