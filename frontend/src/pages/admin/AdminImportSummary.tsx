import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery, keepPreviousData } from '@tanstack/react-query';
import { getAdminExamsList, AdminExamSummary } from '../../api/adminExams';

export default function AdminImportSummary() {
    const { import_id } = useParams<{ import_id: string }>();
    const [page, setPage] = useState(1);
    
    // Filtros visuais omitidos de propósito para ser focado no lote atual
    // Apenas passamos a página e a ordenação.
    const filters = { 
        sort: 'last_update', 
        import_id: import_id 
    };

    const { data: response, isLoading, error } = useQuery({
        queryKey: ['adminImportSummary', import_id, page, filters],
        queryFn: () => getAdminExamsList(page, filters),
        placeholderData: keepPreviousData,
        enabled: !!import_id,
    });

    // Handle normalisation like AdminExamsList
    const exams: AdminExamSummary[] = Array.isArray(response)
        ? response
        : Array.isArray((response as any)?.data)
            ? (response as any).data
            : [];
    const lastPage: number = (response as any)?.last_page ?? 1;
    const currentPage: number = (response as any)?.current_page ?? page;

    const formatDate = (dateString: any) => {
        if (!dateString || dateString === 'N/A') return 'N/A';
        try {
            const cleanDate = String(dateString).split('.')[0];
            let date = new Date(cleanDate);
            if (isNaN(date.getTime())) {
                date = new Date(cleanDate.replace(' ', 'T'));
            }
            if (isNaN(date.getTime())) return 'N/A';
            return date.toLocaleDateString('pt-BR', {
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });
        } catch (e) {
            return 'N/A';
        }
    };

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center bg-white p-5 rounded-lg shadow-sm border border-gray-200">
                <div>
                     <div className="flex items-center gap-3 mb-1">
                        <Link to="/admin/import" className="text-gray-500 hover:text-indigo-600 transition-colors">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                        </Link>
                        <h1 className="text-2xl font-bold text-gray-900">Resumo da Importação</h1>
                        <span className="bg-indigo-100 text-indigo-800 text-xs font-bold px-2.5 py-1 rounded-md uppercase tracking-wide border border-indigo-200">
                            Lote #{import_id}
                        </span>
                    </div>
                    <p className="text-sm text-gray-500 mt-1 ml-8">Exibindo provas e questões processadas unicamente nesta sessão de envio.</p>
                </div>
            </div>

            <div className="bg-white shadow-sm rounded-lg overflow-hidden border border-gray-200">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prova / Metadados</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Questões Alteradas</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data do UPSERT</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Arquivo Original</th>
                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {isLoading ? (
                            <tr><td colSpan={5} className="px-6 py-8 text-center text-gray-500">Carregando itens importados...</td></tr>
                        ) : error ? (
                            <tr><td colSpan={5} className="px-6 py-8 text-center text-red-500 font-semibold">Erro ao carregar resumo.</td></tr>
                        ) : exams.length === 0 ? (
                            <tr>
                                <td colSpan={5} className="px-6 py-12 text-center text-gray-500">
                                    <svg className="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    Nenhuma prova registrada. Provavelmente esta importação não processou nada (tudo foi ignorado por duplicidade).
                                </td>
                            </tr>
                        ) : (
                            exams.map((exam, idx) => {
                                const idParam = exam.arquivo_origem ? btoa(exam.arquivo_origem) : 'null';
                                const searchParams = new URLSearchParams();
                                
                                // O segredo é passar o import_id para URL da view de Detalhes
                                searchParams.append('import_id', String(import_id));
                                
                                if (!exam.arquivo_origem) {
                                    if (exam.year) searchParams.append('year', exam.year.toString());
                                    if (exam.organization) searchParams.append('organization', exam.organization);
                                    if (exam.institution) searchParams.append('institution', exam.institution);
                                    if (exam.role) searchParams.append('role', exam.role);
                                }

                                const detailUrl = `/admin/provas/${idParam}?${searchParams.toString()}`;

                                return (
                                    <tr key={idx} className="hover:bg-gray-50 transition-colors">
                                        <td className="px-6 py-4">
                                            <div className="text-sm text-gray-900 font-bold flex items-center gap-2">
                                                <span className="bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded text-xs border border-indigo-100">{exam.year || 'S/A'}</span>
                                                {exam.organization || 'Sem Banca'}
                                            </div>
                                            <div className="text-sm text-gray-600 mt-0.5 font-medium truncate max-w-md">
                                                {exam.institution || 'Sem Órgão'} {exam.role ? ` - ${exam.role}` : ''}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <span className="px-2.5 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-blue-50 text-blue-700 border border-blue-200">
                                                {exam.total_questions} itens
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div className="flex flex-col">
                                                <span className="font-medium text-gray-700">{formatDate(exam.last_update)}</span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center text-xs text-gray-500 italic max-w-[200px] truncate" title={exam.arquivo_origem || 'N/A'}>
                                                <svg className="w-4 h-4 mr-1.5 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                </svg>
                                                {exam.arquivo_origem || 'Não identificado'}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <Link
                                                to={detailUrl}
                                                className="text-indigo-600 hover:text-indigo-900 inline-flex items-center gap-1 bg-indigo-50 hover:bg-indigo-100 px-4 py-1.5 rounded-lg border border-indigo-200 transition-all font-bold"
                                            >
                                                Detalhar Itens
                                            </Link>
                                        </td>
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>

            {lastPage > 1 && (
                <div className="flex justify-between items-center bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                    <span className="text-sm text-gray-700">Página <span className="font-semibold">{currentPage}</span> de <span className="font-semibold">{lastPage}</span></span>
                    <div className="flex space-x-2">
                        <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1} className="px-4 py-2 border rounded-md text-sm text-gray-700 disabled:opacity-50">Anterior</button>
                        <button onClick={() => setPage(p => Math.min(lastPage, p + 1))} disabled={page === lastPage} className="px-4 py-2 border rounded-md text-sm text-gray-700 disabled:opacity-50">Próxima</button>
                    </div>
                </div>
            )}
        </div>
    );
}
