import React, { useState } from 'react';
import { useQuery, keepPreviousData } from '@tanstack/react-query';
import { getAdminExamsList, AdminExamSummary } from '../../../api/adminExams';
import { Link } from 'react-router-dom';

export default function AdminExamsList() {
    const [page, setPage] = useState(1);
    const [filters, setFilters] = useState({ year: '', organization: '', institution: '', role: '', sort: 'last_update' });

    const { data: response, isLoading, error } = useQuery({
        queryKey: ['adminExams', page, filters],
        queryFn: () => getAdminExamsList(page, filters),
        placeholderData: keepPreviousData,
    });

    // Suporta tanto { data: [...] } quanto [...] diretamente
    const exams: AdminExamSummary[] = Array.isArray(response)
        ? response
        : Array.isArray((response as any)?.data)
            ? (response as any).data
            : [];
    const lastPage: number = (response as any)?.last_page ?? 1;
    const currentPage: number = (response as any)?.current_page ?? page;

    const handleFilterChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
        setFilters({ ...filters, [e.target.name]: e.target.value });
        setPage(1);
    };

    const formatDate = (dateString: any) => {
        if (!dateString || dateString === 'N/A') return 'N/A';
        
        try {
            // Handle MySQL format "YYYY-MM-DD HH:mm:ss" and remove potential microsecond precision (.000)
            const cleanDate = String(dateString).split('.')[0];
            
            // Try standard parse
            let date = new Date(cleanDate);
            
            // Fallback for browsers that don't like spaces in date strings
            if (isNaN(date.getTime())) {
                date = new Date(cleanDate.replace(' ', 'T'));
            }
            
            if (isNaN(date.getTime())) return 'N/A';

            return date.toLocaleDateString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (e) {
            return 'N/A';
        }
    };

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <h1 className="text-2xl font-bold text-gray-900">Provas (PDFs)</h1>
            </div>

            <div className="bg-white p-4 rounded-lg shadow-sm border border-gray-200 flex flex-wrap gap-4 items-center">
                <div className="flex items-center gap-2">
                    <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    <span className="text-sm font-medium text-gray-700">Filtros:</span>
                </div>
                
                <input
                    type="number"
                    name="year"
                    value={filters.year}
                    onChange={handleFilterChange}
                    placeholder="Ano"
                    className="border border-gray-300 rounded-md px-3 py-2 w-24 text-sm focus:ring-primary-500 focus:border-primary-500"
                />
                <input
                    type="text"
                    name="organization"
                    value={filters.organization}
                    onChange={handleFilterChange}
                    placeholder="Banca"
                    className="border border-gray-300 rounded-md px-3 py-2 w-40 text-sm focus:ring-primary-500 focus:border-primary-500"
                />
                <input
                    type="text"
                    name="institution"
                    value={filters.institution}
                    onChange={handleFilterChange}
                    placeholder="Órgão"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500 flex-1 min-w-[200px]"
                />
                <input
                    type="text"
                    name="role"
                    value={filters.role}
                    onChange={handleFilterChange}
                    placeholder="Cargo"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500 flex-1 min-w-[200px]"
                />
                
                <div className="border-l border-gray-200 h-8 mx-1"></div>
                
                <div className="flex items-center gap-2">
                    <label className="text-xs font-bold text-gray-500 uppercase">Sort:</label>
                    <select
                        name="sort"
                        value={filters.sort}
                        onChange={handleFilterChange}
                        className="border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500 bg-gray-50 font-medium"
                    >
                        <option value="last_update">Última Atualização</option>
                        <option value="year">Ano da Prova</option>
                    </select>
                </div>
            </div>

            <div className="bg-white shadow-sm rounded-lg overflow-hidden border border-gray-200">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Prova / Metadados
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Questões
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Última Modificação
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Arquivo Original
                            </th>
                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Ações
                            </th>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {isLoading ? (
                            <tr>
                                <td colSpan={5} className="px-6 py-8 text-center text-gray-500">
                                    Carregando provas...
                                </td>
                            </tr>
                        ) : error ? (
                            <tr>
                                <td colSpan={5} className="px-6 py-8 text-center text-red-500 font-semibold">
                                    Erro ao carregar provas. Tente novamente.
                                </td>
                            </tr>
                        ) : exams.length === 0 ? (
                            <tr>
                                <td colSpan={5} className="px-6 py-8 text-center text-gray-500">
                                    Nenhuma prova encontrada com os filtros selecionados.
                                </td>
                            </tr>
                        ) : (
                            exams.map((exam, idx) => {
                                // Encode for URL mapping
                                const idParam = exam.arquivo_origem
                                    ? btoa(exam.arquivo_origem)
                                    : 'null';

                                const searchParams = new URLSearchParams();
                                if (!exam.arquivo_origem) {
                                    if (exam.year) searchParams.append('year', exam.year.toString());
                                    if (exam.organization) searchParams.append('organization', exam.organization);
                                    if (exam.institution) searchParams.append('institution', exam.institution);
                                    if (exam.role) searchParams.append('role', exam.role);
                                }

                                const detailUrl = `/admin/provas/${idParam}${searchParams.toString() ? '?' + searchParams.toString() : ''}`;

                                return (
                                    <tr key={idx} className="hover:bg-gray-50 transition-colors">
                                        <td className="px-6 py-4">
                                            <div className="text-sm text-gray-900 font-bold flex items-center gap-2">
                                                <span className="bg-primary-50 text-primary-700 px-2 py-0.5 rounded text-xs">{exam.year || 'S/A'}</span>
                                                {exam.organization || 'Sem Banca'}
                                            </div>
                                            <div className="text-sm text-gray-600 mt-0.5 font-medium truncate max-w-md">
                                                {exam.institution || 'Sem Órgão'} {exam.role ? ` - ${exam.role}` : ''}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <span className="px-2.5 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-blue-100 text-blue-800 border border-blue-200">
                                                {exam.total_questions} itens
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div className="flex flex-col">
                                                <span className="font-medium text-gray-700">{formatDate(exam.last_update)}</span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center text-xs text-gray-400 italic max-w-[200px] truncate" title={exam.arquivo_origem || 'N/A'}>
                                                <svg className="w-3.5 h-3.5 mr-1.5 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                </svg>
                                                {exam.arquivo_origem || 'Não identificado'}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <Link
                                                to={detailUrl}
                                                className="text-primary-600 hover:text-primary-900 inline-flex items-center gap-1 bg-primary-50 hover:bg-primary-100 px-4 py-1.5 rounded-lg border border-primary-200 transition-all font-bold"
                                            >
                                                Abrir Prova
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
                    <span className="text-sm text-gray-700">
                        Mostrando página <span className="font-semibold">{currentPage}</span> de <span className="font-semibold">{lastPage}</span>
                    </span>
                    <div className="flex space-x-2">
                        <button
                            onClick={() => setPage(p => Math.max(1, p - 1))}
                            disabled={page === 1}
                            className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            Anterior
                        </button>
                        <button
                            onClick={() => setPage(p => Math.min(lastPage, p + 1))}
                            disabled={page === lastPage}
                            className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            Próxima
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
