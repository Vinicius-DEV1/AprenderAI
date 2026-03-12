import { useParams, useSearchParams, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getAdminExamDetails } from '../../../api/adminExams';
import ExamQuestionCard from '../../../components/admin/ExamQuestionCard';

export default function AdminExamDetails() {
    const { id } = useParams<{ id: string }>();
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();

    // Reconstruct filters from URL if fallback is needed
    const filters: any = {};
    if (searchParams.get('year')) filters.year = searchParams.get('year');
    if (searchParams.get('organization')) filters.organization = searchParams.get('organization');
    if (searchParams.get('institution')) filters.institution = searchParams.get('institution');
    if (searchParams.get('role')) filters.role = searchParams.get('role');

    const { data: response, isLoading, isError } = useQuery({
        queryKey: ['adminExamDetails', id, filters],
        queryFn: () => getAdminExamDetails(id || 'null', Object.keys(filters).length ? filters : undefined),
        enabled: !!id,
    });

    const questions = response?.data || [];

    const examTitle = id && id !== 'null' ? atob(id) : 'Prova Encontrada via Filtros';
    const examYear = filters.year || questions[0]?.year || 'Ano Indefinido';
    const examOrg = filters.organization || questions[0]?.organization || 'Banca Indefinida';

    return (
        <div className="space-y-6 max-w-5xl mx-auto pb-10">
            {/* Header */}
            <div className="bg-white p-5 rounded-lg shadow-sm border border-gray-200 flex flex-col gap-2">
                <div className="flex justify-between items-center">
                    <button
                        onClick={() => navigate('/admin/provas')}
                        className="flex items-center text-sm text-gray-700 hover:text-primary-600 font-medium"
                    >
                        <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Voltar para Lista
                    </button>
                    <span className="bg-primary-100 text-primary-800 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">
                        Cronologia Preservada
                    </span>
                </div>
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 truncate">
                        {examTitle}
                    </h1>
                    <p className="text-sm text-gray-600 mt-1">
                        {examYear} &bull; {examOrg} &bull; {questions.length} Questões Carregadas
                    </p>
                </div>
            </div>

            {isLoading ? (
                <div className="text-center py-10 text-gray-500 font-medium">Carregando espelho da prova...</div>
            ) : isError ? (
                <div className="text-center py-10 text-red-500 font-medium">Erro ao carregar a prova. Verifique se o ID existe.</div>
            ) : questions.length === 0 ? (
                <div className="text-center py-10 text-gray-500 font-medium">Nenhuma questão encontrada para essa prova.</div>
            ) : (
                <div className="flex flex-col gap-2">
                    {questions.map((q, idx) => (
                        <ExamQuestionCard key={q.id} question={q} index={idx} />
                    ))}
                </div>
            )}
        </div>
    );
}
