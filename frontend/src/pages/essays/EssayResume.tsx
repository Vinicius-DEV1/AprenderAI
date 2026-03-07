import { useParams, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getEssay } from '../../api/essays';
import EssayWrite from './EssayWrite';

/**
 * EssayResume — loads an existing draft (pending | in_progress) and
 * renders EssayWrite jump-started to step 3 with the saved data.
 * If the essay is no longer a draft, redirects to the review page.
 *
 * Draft status lifecycle:
 *   'pending'     → draft created, topic not yet generated
 *   'in_progress' → topic generated (by GenerateEssayTopicJob), ready to write
 */
const DRAFT_STATUSES = ['pending', 'in_progress'];

export default function EssayResume() {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();

    const { data: response, isLoading, isError } = useQuery({
        queryKey: ['essay', id],
        queryFn: () => getEssay(id!),
        enabled: !!id,
    });

    if (isLoading) {
        return (
            <div className="flex justify-center py-20">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600" />
            </div>
        );
    }

    if (isError || !response?.data) {
        return (
            <div className="flex justify-center py-20 text-red-600">
                Erro ao carregar o rascunho da redação.
            </div>
        );
    }

    const essay = response.data;

    // If no longer a draft, redirect to the review/correction page
    if (!DRAFT_STATUSES.includes(essay.status)) {
        navigate(`/redacao/correcao/${essay.id}`, { replace: true });
        return null;
    }

    // The API (EssayResource) exposes:
    //   essay.theme   → the theme title (mapped from 'title' column)
    //   essay.content → the essay text (may be '' when not started)
    //   essay.type    → 'enem' | 'concurso'
    return (
        <EssayWrite
            resumeEssayId={essay.id}
            initialTheme={essay.theme ?? ''}
            initialThemeDescription={essay.theme ?? ''}
            initialType={essay.type ?? 'enem'}
            initialContent={essay.content ?? ''}
        />
    );
}
