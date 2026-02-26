import { useEffect } from 'react';
import { useConfigStore } from '../stores/configStore';

interface MetaTagsProps {
    title?: string;
    description?: string;
}

export default function MetaTags({ title, description }: MetaTagsProps) {
    const { appName } = useConfigStore();

    useEffect(() => {
        const fullTitle = title ? `${title} | ${appName}` : appName;
        document.title = fullTitle;

        if (description) {
            let metaDesc = document.querySelector('meta[name="description"]');
            if (!metaDesc) {
                metaDesc = document.createElement('meta');
                metaDesc.setAttribute('name', 'description');
                document.head.appendChild(metaDesc);
            }
            metaDesc.setAttribute('content', description);
        }
    }, [title, description, appName]);

    return null;
}
