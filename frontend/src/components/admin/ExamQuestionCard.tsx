import React from 'react';
import { Question } from '../../types';
import DOMPurify from 'dompurify';
import Viewer from 'viewerjs';
import 'viewerjs/dist/viewer.css';

interface ExamQuestionCardProps {
    question: Question;
    index: number;
}

export default function ExamQuestionCard({ question, index }: ExamQuestionCardProps) {
    const isDiscursive = question.type === 'concurso' && question.tipo_questao === 'Discursiva';

    // Viewer.js instance ref
    const galleryRef = React.useRef<HTMLDivElement>(null);
    const viewerInstance = React.useRef<Viewer | null>(null);

    React.useEffect(() => {
        if (galleryRef.current && question.images && question.images.length > 0) {
            viewerInstance.current = new Viewer(galleryRef.current, {
                inline: false,
                button: true,
                navbar: true,
                title: false,
                toolbar: true,
                tooltip: true,
                zoomRatio: 0.1,
            });
        }
        return () => {
            if (viewerInstance.current) {
                viewerInstance.current.destroy();
            }
        };
    }, [question.images]);

    const renderDiscursiveAnswer = () => {
        if (!question.discursive_answer) {
            return (
                <div className="text-gray-500 italic p-4 bg-gray-50 rounded-md border border-gray-200">
                    Gabarito / Padrão de Resposta não cadastrado.
                </div>
            );
        }

        // Try to parse if it's a string, might be JSON array/object or just a generic string
        let content = question.discursive_answer;

        if (typeof content === 'string') {
            try {
                const parsed = JSON.parse(content);
                if (typeof parsed === 'object' && parsed !== null) {
                    content = parsed;
                }
            } catch (e) {
                // Not JSON, just a string, which is fine
            }
        }

        return (
            <div className="bg-green-50/50 p-4 rounded-md border border-green-200">
                <h4 className="text-green-800 font-semibold text-sm mb-2 uppercase tracking-wide">Padrão de Resposta Esperado</h4>

                {typeof content === 'string' ? (
                    <div
                        className="text-gray-700 whitespace-pre-wrap text-sm leading-relaxed"
                        dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(content.replace(/\n/g, '<br/>')) }}
                    />
                ) : Array.isArray(content) ? (
                    <ul className="list-disc pl-5 space-y-2 text-sm text-gray-700">
                        {content.map((item, idx) => (
                            <li key={idx}>{item}</li>
                        ))}
                    </ul>
                ) : (
                    <div className="space-y-3">
                        {Object.entries(content).map(([key, val], idx) => (
                            <div key={idx} className="flex flex-col">
                                <span className="font-semibold text-gray-800 capitalize mb-1">{key}:</span>
                                <span className="text-gray-700 text-sm whitespace-pre-wrap">{String(val)}</span>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        );
    };

    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden break-inside-avoid mb-6">
            {/* Header */}
            <div className="bg-gray-50 px-5 py-3 border-b border-gray-200 flex justify-between items-center">
                <div className="flex gap-3 items-center">
                    <span className="bg-gray-800 text-white rounded-md px-2 py-0.5 text-xs font-bold">
                        #{index + 1}
                    </span>
                    <span className="text-sm font-semibold text-gray-700">
                        {question.number ? `Questão ${question.number}` : 'Questão Sem Número'}
                    </span>
                </div>
                <div className="flex gap-2">
                    <span className="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded">ID: {question.id}</span>
                    <span className={`text-xs px-2 py-1 rounded font-medium ${isDiscursive ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'}`}>
                        {isDiscursive ? 'Discursiva/Redação' : 'Objetiva'}
                    </span>
                </div>
            </div>

            <div className="p-5 flex flex-col gap-6">
                {/* Statement */}
                <div
                    className="prose prose-sm max-w-none text-gray-800"
                    dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(question.statement_html || '') }}
                />

                {/* Images */}
                {question.images && question.images.length > 0 && (
                    <div ref={galleryRef} className="flex flex-wrap gap-4 mt-2">
                        {question.images.map((img) => (
                            <img
                                key={img.id}
                                src={img.image_url}
                                alt={`Imagem da Questão ${question.id}`}
                                className="max-h-64 object-contain rounded border border-gray-200 cursor-pointer hover:opacity-90 transition-opacity"
                            />
                        ))}
                    </div>
                )}

                {/* Answers / Alternatives */}
                <div className="mt-4">
                    {isDiscursive ? (
                        renderDiscursiveAnswer()
                    ) : (
                        <div className="space-y-2">
                            {question.alternatives && question.alternatives.length > 0 ? (
                                question.alternatives.map((alt) => (
                                    <div
                                        key={alt.id}
                                        className={`flex p-3 rounded-lg border ${alt.is_correct ? 'bg-green-50 border-green-500' : 'bg-gray-50 border-gray-200'}`}
                                    >
                                        <div className={`flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full mr-3 font-bold text-sm ${alt.is_correct ? 'bg-green-500 text-white' : 'bg-white text-gray-500 border border-gray-300'}`}>
                                            {alt.label}
                                        </div>
                                        <div
                                            className="text-gray-700 text-sm mt-1"
                                            dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(alt.content || '') }}
                                        />
                                    </div>
                                ))
                            ) : (
                                <div className="text-gray-500 italic text-sm">Nenhuma alternativa cadastrada ou Questão Anulada.</div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
