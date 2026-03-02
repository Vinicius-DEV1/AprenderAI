import { toast } from 'sonner';
import { useState, useEffect } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { useParams, useNavigate } from 'react-router-dom';
import api from '../../api/axios';
import ReactMarkdown from 'react-markdown';
import { AdminPageSkeleton } from './components/AdminSkeletons';

const apiUrl = import.meta.env.VITE_API_BASE_URL || (import.meta.env.PROD ? '' : 'http://localhost:8000');

export default function QuestionForm() {
    const { id } = useParams();
    const navigate = useNavigate();
    const isEditing = !!id;

    const [subject, setSubject] = useState('');
    const [type, setType] = useState('enem');
    const [format, setFormat] = useState('multiple_choice');
    const [source, setSource] = useState('manual');
    const [organization, setOrganization] = useState('');
    const [topic, setTopic] = useState('');
    const [difficulty, setDifficulty] = useState('medium');
    const [difficultyReasoning, setDifficultyReasoning] = useState('');
    const [statement, setStatement] = useState('');
    const [correctAnswer, setCorrectAnswer] = useState('');
    const [explanation, setExplanation] = useState('');
    const [tipoQuestao, setTipoQuestao] = useState('Objetiva');
    const [discursiveAnswer, setDiscursiveAnswer] = useState('');

    // Multiple Choice Alternatives
    const [altA, setAltA] = useState('');
    const [altB, setAltB] = useState('');
    const [altC, setAltC] = useState('');
    const [altD, setAltD] = useState('');
    const [altE, setAltE] = useState('');

    const [validationErrors, setValidationErrors] = useState<any>({});

    const { data: supportData, isLoading: isLoadingSupport } = useQuery({
        queryKey: ['admin-questions-support-data'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/questions/support-data');
            return res.data;
        }
    });

    const { data: question, isLoading: isLoadingQuestion } = useQuery({
        queryKey: ['admin-question', id],
        queryFn: async () => {
            const res = await api.get(`/api/v1/admin/questions/${id}`);
            return res.data;
        },
        enabled: isEditing
    });

    useEffect(() => {
        if (question) {
            setSubject(question.subjects?.[0]?.name?.toLowerCase() || '');
            setType(question.type || 'enem');
            setFormat(question.format || 'multiple_choice');
            setSource(question.source || 'manual');
            setOrganization(question.organization || '');
            setTopic(question.topics?.[0]?.name?.toLowerCase() || '');
            setDifficulty(question.difficulty || 'medium');
            setDifficultyReasoning(question.difficulty_reasoning || '');
            setStatement(question.statement || '');
            setCorrectAnswer(question.correct_answer || '');
            setExplanation(question.explanation || '');
            setTipoQuestao(question.tipo_questao || 'Objetiva');
            const discAns = question.discursive_answer;
            setDiscursiveAnswer(typeof discAns === 'object' && discAns !== null ? JSON.stringify(discAns, null, 2) : (discAns || ''));

            if (question.format === 'multiple_choice' && question.alternatives) {
                const alts = question.alternatives;
                const getAlt = (letter: string) => {
                    const found = alts.find((a: any) => a.label === letter);
                    if (!found) return '';
                    if (found.content) return found.content;
                    if (found.image_path) {
                        return `![Imagem da Alternativa](/storage/${found.image_path.replace('storage/', '')})`;
                    }
                    return '';
                };

                setAltA(getAlt('A'));
                setAltB(getAlt('B'));
                setAltC(getAlt('C'));
                setAltD(getAlt('D'));
                setAltE(getAlt('E'));
            } else if (question.format === 'true_false') {
                if (!['C', 'E'].includes(question.correct_answer)) {
                    setCorrectAnswer('C');
                }
            }
        }
    }, [question]);

    const saveMutation = useMutation({
        mutationFn: async (payload: any) => {
            if (isEditing) {
                return await api.put(`/api/v1/admin/questions/${id}`, payload);
            }
            return await api.post('/api/v1/admin/questions', payload);
        },
        onSuccess: () => {
            toast.success(`Questão ${isEditing ? 'atualizada' : 'criada'} com sucesso!`);
            navigate('/admin/questions');
        },
        onError: (error: any) => {
            if (error.response?.status === 422) {
                setValidationErrors(error.response.data.errors);
                toast.error('Verifique os campos obrigatórios.');
            } else {
                toast.error('Erro ao salvar questão.');
            }
        }
    });

    const urlTransform = (uri: string) => {
        if (uri.startsWith('/storage')) {
            return `${apiUrl}${uri}`;
        }
        return uri;
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setValidationErrors({});

        const payload: any = {
            subject,
            type,
            format,
            source,
            organization,
            difficulty,
            difficulty_reasoning: difficultyReasoning,
            statement,
            correct_answer: correctAnswer,
            explanation,
            tipo_questao: tipoQuestao
        };

        if (type === 'concurso') {
            payload.topic = topic;
        }

        if (format === 'multiple_choice') {
            payload.alternatives = {
                A: altA,
                B: altB,
                C: altC,
                D: altD,
                E: altE
            };
        } else if (format === 'true_false') {
            payload.alternatives = {
                'C': 'Certo',
                'E': 'Errado'
            };
        }

        if (tipoQuestao !== 'Objetiva' && discursiveAnswer) {
            try {
                payload.discursive_answer = JSON.parse(discursiveAnswer);
            } catch {
                payload.discursive_answer = discursiveAnswer;
            }
        }

        saveMutation.mutate(payload);
    };

    if (isLoadingSupport || (isEditing && isLoadingQuestion)) {
        return <AdminPageSkeleton />;
    }

    if (isEditing && !question) {
        return <div className="p-8 text-center text-red-500 font-bold">Questão não encontrada.</div>;
    }

    const getError = (field: string) => validationErrors[field] ? validationErrors[field][0] : null;

    return (
        <div className="py-6 px-4 md:px-6 w-full">
            <h2 className="font-semibold text-xl text-gray-800 leading-tight mb-6">
                {isEditing ? `Editar Questão #${id}` : 'Nova Questão'}
            </h2>

            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div className="p-6 text-gray-900">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {/* Metadados */}
                            <div>
                                <label htmlFor="subject" className="block text-sm font-medium text-gray-700">Matéria</label>
                                <select
                                    id="subject"
                                    value={subject}
                                    onChange={(e) => setSubject(e.target.value)}
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Selecione...</option>
                                    {supportData?.subjects?.map((subj: any) => (
                                        <option key={subj.id || subj.name} value={subj.name?.toLowerCase()}>{subj.name}</option>
                                    ))}
                                </select>
                                {getError('subject') && <span className="text-red-500 text-xs">{getError('subject')}</span>}
                            </div>

                            <div>
                                <label htmlFor="type" className="block text-sm font-medium text-gray-700">Tipo de Prova (Categoria)</label>
                                <select
                                    id="type"
                                    value={type}
                                    onChange={(e) => setType(e.target.value)}
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="enem">ENEM</option>
                                    <option value="concurso">Concurso</option>
                                </select>
                                {getError('type') && <span className="text-red-500 text-xs">{getError('type')}</span>}
                            </div>

                            <div>
                                <label htmlFor="tipo_questao" className="block text-sm font-medium text-gray-700">Tipo da Questão (Estrutura)</label>
                                <select
                                    id="tipo_questao"
                                    value={tipoQuestao}
                                    onChange={(e) => setTipoQuestao(e.target.value)}
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 border-indigo-300 bg-indigo-50 font-bold">
                                    <option value="Objetiva">Objetiva (A, B, C, D, E | Certo/Errado)</option>
                                    <option value="Discursiva">Discursiva (Com subitens fatiados)</option>
                                    <option value="Redação">Redação (Texto Único)</option>
                                </select>
                                {getError('tipo_questao') && <span className="text-red-500 text-xs">{getError('tipo_questao')}</span>}
                            </div>

                            <div>
                                <label htmlFor="format" className="block text-sm font-medium text-gray-700">Formato da Questão (Usado para Objetivas)</label>
                                <select
                                    id="format"
                                    value={format}
                                    onChange={(e) => {
                                        setFormat(e.target.value);
                                        if (e.target.value === 'true_false' && !['C', 'E'].includes(correctAnswer)) {
                                            setCorrectAnswer('C');
                                        }
                                    }}
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="multiple_choice">Múltipla Escolha (A, B, C, D, E)</option>
                                    <option value="true_false">Certo ou Errado (Somente C/E)</option>
                                </select>
                                <span className="text-[10px] text-gray-400">Na Discursiva, use 'Múltipla Escolha' para fatiar as linhas (a, b, c).</span>
                                {getError('format') && <span className="text-red-500 text-xs">{getError('format')}</span>}
                            </div>

                            <div>
                                <label htmlFor="source" className="block text-sm font-medium text-gray-700">Origem</label>
                                <select
                                    id="source"
                                    value={source}
                                    onChange={(e) => setSource(e.target.value)}
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="manual">Manual (ENEM)</option>
                                    <option value="ai_generated">IA Gerada</option>
                                </select>
                                {getError('source') && <span className="text-red-500 text-xs">{getError('source')}</span>}
                            </div>

                            <div>
                                <label htmlFor="organization" className="block text-sm font-medium text-gray-700">Banca / Organização (Ex: ENEM, CESPE)</label>
                                <input
                                    type="text"
                                    id="organization"
                                    value={organization}
                                    onChange={(e) => setOrganization(e.target.value)}
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                {getError('organization') && <span className="text-red-500 text-xs">{getError('organization')}</span>}
                            </div>

                            {type === 'concurso' && (
                                <div>
                                    <label htmlFor="topic" className="block text-sm font-medium text-gray-700">Assunto / Tópico (Opcional)</label>
                                    <select
                                        id="topic"
                                        value={topic}
                                        onChange={(e) => setTopic(e.target.value)}
                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Selecione um tópico...</option>
                                        {supportData?.topics?.map((t: any) => (
                                            <option key={t.id || t.name} value={t.name?.toLowerCase()}>{t.name}</option>
                                        ))}
                                    </select>
                                    {getError('topic') && <span className="text-red-500 text-xs">{getError('topic')}</span>}
                                </div>
                            )}

                            <div>
                                <label htmlFor="difficulty" className="block text-sm font-medium text-gray-700">Dificuldade</label>
                                <select
                                    id="difficulty"
                                    value={difficulty}
                                    onChange={(e) => setDifficulty(e.target.value)}
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="easy">Fácil</option>
                                    <option value="medium">Média</option>
                                    <option value="hard">Difícil</option>
                                </select>
                                {getError('difficulty') && <span className="text-red-500 text-xs">{getError('difficulty')}</span>}
                            </div>

                            <div className="md:col-span-2 bg-blue-50 p-4 rounded-lg">
                                <label htmlFor="difficulty_reasoning" className="block text-sm font-medium text-gray-700">Justificativa da IA (Dificuldade)</label>
                                <textarea
                                    id="difficulty_reasoning"
                                    value={difficultyReasoning}
                                    onChange={(e) => setDifficultyReasoning(e.target.value)}
                                    rows={3}
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm placeholder-gray-500 focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="Explicação da IA sobre a dificuldade..."></textarea>
                                {getError('difficulty_reasoning') && <span className="text-red-500 text-xs">{getError('difficulty_reasoning')}</span>}
                                <p className="text-sm text-gray-500 mt-1">Este texto é gerado automaticamente pela IA, mas pode ser editado para refinar a explicação.</p>
                            </div>
                        </div>

                        {/* Enunciado */}
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <label htmlFor="statement" className="block text-sm font-medium text-gray-700">Enunciado (Markdown/Texto)</label>
                                <span className="text-xs font-bold text-indigo-600 bg-indigo-50 px-2 py-1 rounded">Live Preview Ativo</span>
                            </div>

                            {/* Preview Area */}
                            {statement && (
                                <div className="p-6 bg-gray-50 border-2 border-dashed border-gray-200 rounded-xl mb-4">
                                    <h4 className="text-[10px] uppercase font-bold text-gray-400 mb-3 tracking-widest">Prévia do Aluno</h4>
                                    <div className="prose prose-indigo max-w-none text-gray-800">
                                        <ReactMarkdown
                                            urlTransform={urlTransform}
                                            components={{
                                                img: ({ ...props }) => <img {...props} className="max-w-full h-auto rounded-lg my-4 mx-auto block shadow-sm" />
                                            }}>
                                            {statement}
                                        </ReactMarkdown>
                                    </div>
                                </div>
                            )}

                            <textarea
                                id="statement"
                                value={statement}
                                onChange={(e) => setStatement(e.target.value)}
                                rows={5}
                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="Ex: ![Imagem](url) ..."></textarea>
                            {getError('statement') && <span className="text-red-500 text-xs">{getError('statement')}</span>}
                        </div>

                        <div className="border-t pt-4">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">
                                {tipoQuestao !== 'Objetiva' ? 'Subitens Discursivos (Tratado como Alternativas)' : (format === 'multiple_choice' ? 'Alternativas (Múltipla Escolha)' : 'Alternativas (Certo ou Errado)')}
                            </h3>
                            <div className="space-y-4">
                                {/* Múltipla Escolha */}
                                {format === 'multiple_choice' && (
                                    <div className="space-y-4">
                                        {[
                                            { letter: 'A', val: altA, set: setAltA },
                                            { letter: 'B', val: altB, set: setAltB },
                                            { letter: 'C', val: altC, set: setAltC },
                                            { letter: 'D', val: altD, set: setAltD },
                                            { letter: 'E', val: altE, set: setAltE }
                                        ].map((item) => (
                                            <div key={item.letter}>
                                                <label htmlFor={`alt_${item.letter}`} className="block text-sm font-medium text-gray-700">Alternativa {item.letter}</label>
                                                <div className="flex items-center gap-2 mt-1">
                                                    {tipoQuestao === 'Objetiva' && (
                                                        <input
                                                            type="radio"
                                                            name="correct_answer"
                                                            value={item.letter}
                                                            checked={correctAnswer === item.letter}
                                                            onChange={(e) => setCorrectAnswer(e.target.value)}
                                                            className="text-indigo-600 focus:ring-indigo-500 w-4 h-4"
                                                            required
                                                        />
                                                    )}
                                                    <div className="flex-1 space-y-1">
                                                        <textarea
                                                            id={`alt_${item.letter}`}
                                                            value={item.val}
                                                            onChange={(e) => item.set(e.target.value)}
                                                            className="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                            rows={2}
                                                            required={tipoQuestao === 'Objetiva' || item.val !== ''}
                                                        />
                                                        {/<img|!\[.*?\]\(.*?\)/i.test(item.val) && (
                                                            <div className="mt-2 p-3 bg-gray-50 border border-gray-200 rounded-lg shadow-sm">
                                                                <span className="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2 block">Prévia da Imagem Mapeada</span>
                                                                <div className="prose max-w-none text-gray-800 text-sm">
                                                                    <ReactMarkdown
                                                                        urlTransform={urlTransform}
                                                                        components={{
                                                                            img: ({ ...props }) => <img {...props} className="max-h-32 h-auto rounded-md shadow-sm" />
                                                                        }}>
                                                                        {item.val}
                                                                    </ReactMarkdown>
                                                                </div>
                                                            </div>
                                                        )}
                                                        {getError(`alternatives.${item.letter}`) && <span className="text-red-500 text-xs">{getError(`alternatives.${item.letter}`)}</span>}
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                {/* Certo ou Errado */}
                                {format === 'true_false' && tipoQuestao === 'Objetiva' && (
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        {[
                                            { letter: 'C', label: 'Certo' },
                                            { letter: 'E', label: 'Errado' }
                                        ].map(item => (
                                            <div key={item.letter} className="p-4 border border-gray-300 rounded-lg hover:bg-gray-50 flex items-center gap-4 cursor-pointer" onClick={() => setCorrectAnswer(item.letter)}>
                                                <input
                                                    type="radio"
                                                    name="correct_answer"
                                                    value={item.letter}
                                                    checked={correctAnswer === item.letter}
                                                    onChange={(e) => setCorrectAnswer(e.target.value)}
                                                    className="w-6 h-6 text-indigo-600 focus:ring-indigo-500"
                                                    required
                                                />
                                                <div className="flex-1">
                                                    <span className="block font-bold text-gray-700">{item.label}</span>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                                {getError('correct_answer') && <span className="text-red-500 text-xs">{getError('correct_answer')}</span>}
                            </div>
                        </div>

                        {/* Espelho Discursiva/Redação */}
                        {tipoQuestao !== 'Objetiva' && (
                            <div className="border-t pt-4 bg-indigo-50 p-4 rounded-lg mt-6 border border-indigo-200">
                                <label htmlFor="discursive_answer" className="block text-sm font-bold text-indigo-900 mb-2">🎓 Espelho de Correção (JSON ou Texto Livro)</label>
                                <textarea
                                    id="discursive_answer"
                                    value={discursiveAnswer}
                                    onChange={(e) => setDiscursiveAnswer(e.target.value)}
                                    rows={8}
                                    className="font-mono text-sm block w-full border-indigo-300 rounded-md shadow-sm placeholder-indigo-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder='Ex: 
{
  "a": "O tratamento para a gripe...",
  "b": "O diagnóstico inclui..."
}'></textarea>
                                {getError('discursive_answer') && <span className="text-red-500 text-xs">{getError('discursive_answer')}</span>}
                                <p className="text-xs text-indigo-600 mt-2 font-medium">Se você preencher em formato JSON, o frontend exibirá fatiado de forma elegante. Caso decida colar apenas o texto liso da banca, está ótimo também.</p>
                            </div>
                        )}

                        {/* Explicação */}
                        <div className="border-t pt-4 bg-yellow-50 p-4 rounded-lg mt-6">
                            <label htmlFor="explanation" className="block text-sm font-medium text-gray-700">Explicação Teórica (Crucial para Objetivas)</label>
                            <textarea
                                id="explanation"
                                value={explanation}
                                onChange={(e) => setExplanation(e.target.value)}
                                rows={4}
                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm placeholder-gray-500 focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Explique os conceitos teóricos subjacentes da questão..."></textarea>
                            {getError('explanation') && <span className="text-red-500 text-xs">{getError('explanation')}</span>}
                            <p className="text-sm text-gray-500 mt-1">Se deixado em branco, o aluno solicitará ajuda ao tutor (gerando custo de API).</p>
                        </div>

                        <div className="flex items-center justify-end mt-8 border-t pt-4">
                            <button
                                type="button"
                                onClick={() => navigate('/admin/questions')}
                                className="text-gray-600 underline mr-4 hover:text-gray-900">
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                disabled={saveMutation.isPending}
                                className="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 font-semibold shadow-sm disabled:opacity-50">
                                {saveMutation.isPending ? 'Salvando...' : 'Salvar Questão'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}
