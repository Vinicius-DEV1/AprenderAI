import { marked } from 'marked';
import katex from 'katex';
import DOMPurify from 'dompurify';

marked.setOptions({ breaks: true, gfm: true });

const apiUrl = import.meta.env.VITE_API_BASE_URL || (import.meta.env.PROD ? '' : 'http://localhost:8000');

export const renderMd = (text: string) => {
    if (!text) return { __html: '' };
    let processedText = String(text);

    // Fix LaTeX format escaping corruption: backend sends \frac, but JS JSON parsers sometimes see \f as form-feed
    processedText = processedText.replace(/\f/g, '\\f');

    // Fix Markdown Image URLs: ![alt](/storage/path) or ![alt](storage/path)
    processedText = processedText.replace(/!\[(.*?)\]\(\s*(\/?storage\/.*?)\s*\)/g, (_, alt, url) => {
        const cleanUrl = url.trim().replace(/^\//, ''); // remove leading slash
        const absoluteUrl = `${apiUrl}/${cleanUrl}`.replace(/([^:])\/\//g, '$1/'); // prevent double slashes but keep http://
        return `![${alt}](${absoluteUrl})`;
    });

    // Prevention: Escape numeric starts that look like list items (e.g. "30.")
    // This avoids 'marked' from creating empty <ol><li></li></ol> when the alternative is just a number.
    if (/^\d+\.($|\s)/.test(processedText.trim()) && !processedText.includes('\n')) {
        processedText = processedText.replace(/^(\d+)\./, '$1\\.');
    }

    // Fix HTML Image URLs: src="/storage/path" or src="storage/path"
    processedText = processedText.replace(/src=["']\s*(\/?storage\/.*?)\s*["']/g, (_, url) => {
        const cleanUrl = url.trim().replace(/^\//, '');
        const absoluteUrl = `${apiUrl}/${cleanUrl}`.replace(/([^:])\/\//g, '$1/');
        return `src="${absoluteUrl}"`;
    });

    // --- LaTeX Delimiters Logic ---

    // 1. Render block math $$ ... $$
    processedText = processedText.replace(/\$\$([\s\S]*?)\$\$/g, (match, formula) => {
        try {
            return `<div class="katex-block-wrapper my-2">${katex.renderToString(formula, { displayMode: true, throwOnError: false })}</div>`;
        } catch (e) {
            return match;
        }
    });

    // 2. Render block math \[ ... \]
    processedText = processedText.replace(/\\\[([\s\S]*?)\\\]/g, (match, formula) => {
        try {
            return `<div class="katex-block-wrapper my-2">${katex.renderToString(formula, { displayMode: true, throwOnError: false })}</div>`;
        } catch (e) {
            return match;
        }
    });

    // 3. Render inline math \( ... \)
    processedText = processedText.replace(/\\\(([\s\S]*?)\\\)/g, (match, formula) => {
        try {
            return katex.renderToString(formula, { displayMode: false, throwOnError: false });
        } catch (e) {
            return match;
        }
    });

    // 4. Render inline math $ ... $
    // We use a more cautious regex for $ to avoid matching $ in normal text.
    // Usually $ should be followed by a non-whitespace and preceded by a space or start of line.
    processedText = processedText.replace(/(^|[^\\])\$([\s\S]*?)\$/g, (match, prefix, formula) => {
        try {
            return `${prefix}${katex.renderToString(formula, { displayMode: false, throwOnError: false })}`;
        } catch (e) {
            return match;
        }
    });

    try {
        const html = marked.parse(processedText) as string;
        return { __html: DOMPurify.sanitize(html) };
    } catch (e) {
        return { __html: DOMPurify.sanitize(processedText) };
    }
};
