import React, { useState } from 'react';
import { useQuery, keepPreviousData } from '@tanstack/react-query';
import { getAdminExamsList } from '../../../api/adminExams';
import { Link } from 'react-router-dom';

export default function AdminExamsList() {
    const [page, setPage] = useState(1);
    const [filters, setFilters] = useState({ year: '', organization: '', institution: '' });

    const { data: response, isLoading } = useQuery({
        queryKey: ['adminExams', page, filters],
        queryFn: () => getAdminExamsList(page, filters),
        placeholderData: keepPreviousData,
    });

    const handleFilterChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setFilters({ ...filters, [e.target.name]: e.target.value });
        setPage(1);
    };

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <h1 className="text-2xl font-bold text-gray-900">Provas (PDFs)</h1>
            </div>

            <div className="bg-white p-4 rounded-lg shadow-sm border border-gray-200 flex gap-4 items-center">
                <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
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
                    placeholder="Banca (ex: CEBRASPE)"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500"
                />
                <input
                    type="text"
                    name="institution"
                    value={filters.institution}
                    onChange={handleFilterChange}
                    placeholder="Órgão (ex: Polícia Federal)"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500 flex-1"
                />
            </div>

            <div className="bg-white shadow-sm rounded-lg overflow-hidden border border-gray-200">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Identificador / Arquivo
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Metadados
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Questões
                            </th>
                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Ações
                            </th>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {isLoading ? (
                            <tr>
                                <td colSpan={4} className="px-6 py-8 text-center text-gray-500">
                                    Carregando provas...
                                </td>
                            </tr>
                        ) : response?.data.length === 0 ? (
                            <tr>
                                <td colSpan={4} className="px-6 py-8 text-center text-gray-500">
                                    Nenhuma prova encontrada.
                                </td>
                            </tr>
                        ) : (
                            response?.data.map((exam, idx) => {
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
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center">
                                                <svg className="w-6 h-6 text-red-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                </svg>
                                                <div className="text-sm font-medium text-gray-900 truncate max-w-xs">
                                                    {exam.arquivo_origem || 'Não identificado'}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="text-sm text-gray-900 font-semibold">
                                                {exam.year || 'S/A'} - {exam.organization || 'Sem Banca'}
                                            </div>
                                            <div className="text-sm text-gray-500 truncate max-w-sm">
                                                {exam.institution || 'Sem Órgão'} {exam.role ? ` - ${exam.role}` : ''}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                {exam.total_questions} itens
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <Link
                                                to={detailUrl}
                                                className="text-primary-600 hover:text-primary-900 inline-flex items-center gap-1 bg-primary-50 px-3 py-1 rounded-md"
                                            >
                                                Visualizar
                                            </Link>
                                        </td>
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>

            {/* Pagination */}
            {response && response.last_page > 1 && (
                <div className="flex justify-between items-center bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                    <span className="text-sm text-gray-700">
                        Mostrando página <span className="font-semibold">{response.current_page}</span> de <span className="font-semibold">{response.last_page}</span>
                    </span>
                    <div className="flex space-x-2">
                        <button
                            onClick={() => setPage(p => Math.max(1, p - 1))}
                            disabled={page === 1}
                            className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Anterior
                        </button>
                        <button
                            onClick={() => setPage(p => Math.min(response.last_page, p + 1))}
                            disabled={page === response.last_page}
                            className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Próxima
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
