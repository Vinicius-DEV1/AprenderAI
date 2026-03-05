import { Helmet } from 'react-helmet-async';
import { useConfigStore } from '../stores/configStore';

interface MetaTagsProps {
    title?: string;
    description?: string;
    image?: string;
    url?: string;
}

export default function MetaTags({ title, description, image, url }: MetaTagsProps) {
    const { appName } = useConfigStore();
    const fullTitle = title ? `${title} | ${appName}` : appName;
    const defaultDescription = "A plataforma inteligente para estudar para o ENEM e Concursos Públicos.";
    const finalDescription = description || defaultDescription;
    const defaultImage = "/og-image.png"; // Usando imagem padrão de fallback
    const finalImage = image || defaultImage;
    const currentUrl = url || typeof window !== 'undefined' ? window.location.href : '';

    return (
        <Helmet>
            <title>{fullTitle}</title>
            <meta name="description" content={finalDescription} />
            {currentUrl && <link rel="canonical" href={currentUrl} />}

            {/* Open Graph / Facebook */}
            <meta property="og:type" content="website" />
            <meta property="og:url" content={currentUrl} />
            <meta property="og:title" content={fullTitle} />
            <meta property="og:description" content={finalDescription} />
            <meta property="og:image" content={finalImage} />

            {/* Twitter */}
            <meta name="twitter:card" content="summary_large_image" />
            <meta name="twitter:url" content={currentUrl} />
            <meta name="twitter:title" content={fullTitle} />
            <meta name="twitter:description" content={finalDescription} />
            <meta name="twitter:image" content={finalImage} />
        </Helmet>
    );
}
