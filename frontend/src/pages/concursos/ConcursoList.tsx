import { useQuery } from '@tanstack/react-query';
import { useSearchParams } from 'react-router-dom';
import api from '../../api/axios';
import { useConfigStore } from '../../stores/configStore';

const getConcursos = async (params: any) => {
    const { data } = await api.get('/api/v1/concursos', { params });
    return data;
};

const UF_LIST = ['AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO'];

export default function ConcursoList() {
    const { appName } = useConfigStore();
    const [searchParams, setSearchParams] = useSearchParams();

    // Filtros atuais do searchParams
    const uf = searchParams.get('uf') || '';
    const busca = searchParams.get('busca') || '';
    const situacao = searchParams.get('situacao') || 'Ativo';
    const page = searchParams.get('page') || '1';

    const { data, isLoading } = useQuery({
        queryKey: ['concursos', { uf, busca, situacao, page }],
        queryFn: () => getConcursos({ uf, busca, situacao, page })
    });

    const handleFilter = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const formData = new FormData(e.currentTarget);
        const newParams: any = {};
        if (formData.get('uf')) newParams.uf = formData.get('uf');
        if (formData.get('busca')) newParams.busca = formData.get('busca');
        if (formData.get('situacao')) newParams.situacao = formData.get('situacao');
        newParams.page = '1';
        setSearchParams(newParams);
    };

    const handlePageChange = (newPage: number) => {
        const currentParams = Object.fromEntries(searchParams.entries());
        setSearchParams({ ...currentParams, page: newPage.toString() });
        window.scrollTo(0, 0);
    };

    const getSituacaoBadge = (situacao: string) => {
        const s = situacao.toLowerCase();
        if (['inscrições abertas', 'aberto', 'andamento', 'ativo'].includes(s)) {
            return { class: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300', label: 'Ativo' };
        }
        if (['previsto'].includes(s)) {
            return { class: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300', label: 'Previsto' };
        }
        return { class: 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400', label: 'Encerrado' };
    };

    return (
        <div className="space-y-6 pb-12">
            {/* Header */}
            <div>
                <h1 className="text-2xl font-bold text-slate-800 dark:text-slate-100">🏛️ Radar de Concursos</h1>
                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Acompanhe os principais concursos e editais sem sair do {appName}.
                </p>
            </div>

            {/* Filtros */}
            <form onSubmit={handleFilter} className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm p-4 flex flex-col sm:flex-row gap-3">
                <div className="flex-1 min-w-0">
                    <label className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Estado (UF)</label>
                    <select name="uf" defaultValue={uf} className="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Todos os estados</option>
                        {UF_LIST.map(sigla => (
                            <option key={sigla} value={sigla}>{sigla}</option>
                        ))}
                    </select>
                </div>

                <div className="flex-[2] min-w-0">
                    <label className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Órgão ou cargo</label>
                    <input name="busca" type="text" defaultValue={busca} placeholder="Ex.: Polícia Federal, Analista..." className="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>

                <div className="flex-1 min-w-0">
                    <label className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Situação</label>
                    <select name="situacao" defaultValue={situacao} className="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="Ativo">Ativo</option>
                        <option value="Previsto">Previsto</option>
                        <option value="Encerrado">Encerrado</option>
                    </select>
                </div>

                <div className="flex items-end">
                    <button type="submit" className="w-full sm:w-auto px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500">
                        Filtrar
                    </button>
                </div>
            </form>

            {isLoading ? (
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    {[1, 2, 3, 4, 5, 6].map(i => (
                        <div key={i} className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-5 h-48 animate-pulse"></div>
                    ))}
                </div>
            ) : data?.data && data.data.length > 0 ? (
                <>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        {data.data.map((concurso: any) => {
                            const badge = getSituacaoBadge(concurso.situacao);
                            return (
                                <div key={concurso.id} className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm flex flex-col p-5 gap-3 hover:shadow-md transition-shadow">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="text-sm font-bold text-slate-800 dark:text-slate-100 leading-snug truncate" title={concurso.orgao}>
                                                {concurso.orgao}
                                            </p>
                                            {concurso.cargo && (
                                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate" title={concurso.cargo}>
                                                    {concurso.cargo}
                                                </p>
                                            )}
                                        </div>
                                        <span className={`flex-shrink-0 text-xs font-semibold px-2 py-0.5 rounded-full ${badge.class}`}>
                                            {badge.label}
                                        </span>
                                    </div>

                                    <div className="space-y-1 text-xs text-slate-500 dark:text-slate-400">
                                        <div className="flex items-center gap-1.5">
                                            <svg className="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            <span>{concurso.uf}</span>
                                        </div>

                                        {concurso.vagas > 0 && (
                                            <div className="flex items-center gap-1.5">
                                                <svg className="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                                <span>{concurso.vagas} vaga{concurso.vagas > 1 ? 's' : ''}</span>
                                            </div>
                                        )}

                                        {concurso.salario_maximo > 0 && (
                                            <div className="flex items-center gap-1.5">
                                                <svg className="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>Até R$ {concurso.salario_maximo.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</span>
                                            </div>
                                        )}

                                        {(concurso.inscricoes_inicio || concurso.inscricoes_fim) && (
                                            <div className="flex items-center gap-1.5">
                                                <svg className="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <span>
                                                    {concurso.inscricoes_inicio && concurso.inscricoes_fim
                                                        ? `${new Date(concurso.inscricoes_inicio + 'T00:00:00').toLocaleDateString('pt-BR')} – ${new Date(concurso.inscricoes_fim + 'T00:00:00').toLocaleDateString('pt-BR')}`
                                                        : concurso.inscricoes_fim
                                                            ? `até ${new Date(concurso.inscricoes_fim + 'T00:00:00').toLocaleDateString('pt-BR')}`
                                                            : `a partir de ${new Date(concurso.inscricoes_inicio + 'T00:00:00').toLocaleDateString('pt-BR')}`
                                                    }
                                                </span>
                                            </div>
                                        )}
                                    </div>

                                    {concurso.link_oficial && (
                                        <div className="mt-3 border-t border-slate-50 dark:border-slate-800 pt-3">
                                            <a href={concurso.link_oficial} target="_blank" rel="noopener noreferrer" className="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline inline-flex items-center gap-1">
                                                Saiba mais
                                                <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                </svg>
                                            </a>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>

                    {/* Paginação Simples */}
                    <div className="mt-8 flex items-center justify-between border-t border-slate-200 dark:border-slate-700 pt-4 px-2">
                        <p className="text-xs text-slate-500">
                            Mostrando <span className="font-medium">{data.meta.from}</span> a <span className="font-medium">{data.meta.to}</span> de <span className="font-medium">{data.meta.total}</span> resultados
                        </p>
                        <div className="flex gap-2">
                            <button onClick={() => handlePageChange(data.meta.current_page - 1)} disabled={data.meta.current_page === 1} className="px-3 py-1 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-md text-sm text-slate-700 dark:text-slate-300 disabled:opacity-50">Anterior</button>
                            <button onClick={() => handlePageChange(data.meta.current_page + 1)} disabled={data.meta.current_page === data.meta.last_page} className="px-3 py-1 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-md text-sm text-slate-700 dark:text-slate-300 disabled:opacity-50">Próximo</button>
                        </div>
                    </div>
                </>
            ) : (
                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm p-10 text-center">
                    <svg className="mx-auto w-12 h-12 text-slate-300 dark:text-slate-600 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p className="text-slate-500 dark:text-slate-400 text-sm font-medium">Nenhum concurso encontrado.</p>
                </div>
            )}

            {data?.meta?.ultima_atualizacao && (
                <p className="text-[10px] text-slate-400 dark:text-slate-500 text-right">
                    Última atualização: {new Date(data.meta.ultima_atualizacao).toLocaleString('pt-BR')}
                </p>
            )}
        </div>
    );
}
