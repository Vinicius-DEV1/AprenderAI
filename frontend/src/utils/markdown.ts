import { marked } from 'marked';
import katex from 'katex';
import DOMPurify from 'dompurify';

marked.setOptions({ breaks: true, gfm: true });

const apiUrl = import.meta.env.VITE_API_BASE_URL || (import.meta.env.PROD ? '' : 'http://localhost:8000');

export const renderMd = (text: string) => {
    if (!text) return { __html: '' };
    let processedText = String(text);

    // Unescape secure tags first (like <u>, <b>, <i>, <strong>, <em>, <br>)
    // The backend uses e() so they arrive as &lt;u&gt;
    processedText = processedText
        .replace(/&lt;u&gt;/gi, '<u>')
        .replace(/&lt;\/u&gt;/gi, '</u>')
        .replace(/&lt;b&gt;/gi, '<b>')
        .replace(/&lt;\/b&gt;/gi, '</b>')
        .replace(/&lt;i&gt;/gi, '<i>')
        .replace(/&lt;\/i&gt;/gi, '</i>')
        .replace(/&lt;strong&gt;/gi, '<strong>')
        .replace(/&lt;\/strong&gt;/gi, '</strong>')
        .replace(/&lt;em&gt;/gi, '<em>')
        .replace(/&lt;\/em&gt;/gi, '</em>')
        .replace(/&lt;details&gt;/gi, '<details>')
        .replace(/&lt;\/details&gt;/gi, '</details>')
        .replace(/&lt;summary&gt;/gi, '<summary>')
        .replace(/&lt;\/summary&gt;/gi, '</summary>')
        .replace(/&lt;br\s*\/?&gt;/gi, '<br>')
        .replace(/&#0?39;/g, "'")
        .replace(/&quot;/g, '"');

    // Remove newlines and <br> around details/summary to prevent excess spacing
    // The backend nl2br() adds <br /> which often duplicates with markdown breaks
    processedText = processedText
        .replace(/(<(?:details|summary|b|i|u|strong|em)>)\s*(?:[\r\n]|<br\s*\/?>)+/gi, '$1')
        .replace(/(?:[\r\n]|<br\s*\/?>)+\s*(<\/(?:details|summary|b|i|u|strong|em)>)/gi, '$1')
        .replace(/(<\/(?:details|summary|b|i|u|strong|em)>)\s*(?:[\r\n]|<br\s*\/?>)+/gi, '$1')
        .replace(/(?:[\r\n]|<br\s*\/?>)+\s*(<(?:details|summary|b|i|u|strong|em)>)/gi, '$1');

    // Fix LaTeX format escaping corruption: backend sends \frac, but JS JSON parsers sometimes see \f as form-feed
    processedText = processedText.replace(/\f/g, '\\f');

    // Fix literal '\n' strings that come escaped from the backend JSON payload
    processedText = processedText.replace(/\\n/g, '\n');

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

    // --- LaTeX Delimiters Logic (Placeholder System) ---

    // Pre-process Formula to support 'tabular' by converting to 'array' (KaTeX support)
    const normalizeFormula = (f: string) => {
        return f
            .replace(/\\begin{tabular}(\{.*?\})/g, '\\begin{array}$1')
            .replace(/\\end{tabular}/g, '\\end{array}')
            .replace(/&amp;/g, '&')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&quot;/g, '"')
            .replace(/&#39;/g, "'");
    };

    const mathPlaceholders: { id: string; code: string; displayMode: boolean; prefix: string }[] = [];

    const saveMath = (mathCode: string, displayMode: boolean, prefix = '') => {
        const id = `@@MATH_${mathPlaceholders.length}@@`;
        mathPlaceholders.push({ id, code: mathCode, displayMode, prefix });
        return `${prefix}${id}`;
    };

    // 1. Extract block math $$ ... $$
    processedText = processedText.replace(/\$\$([\s\S]*?)\$\$/g, (_match, formula) => saveMath(formula, true));

    // 2. Extract block math \[ ... \]
    processedText = processedText.replace(/\\\[([\s\S]*?)\\\]/g, (_match, formula) => saveMath(formula, true));

    // 3. Extract inline math \( ... \)
    processedText = processedText.replace(/\\\(([\s\S]*?)\\\)/g, (_match, formula) => saveMath(formula, false));

    // 4. Extract inline math $ ... $
    processedText = processedText.replace(/(^|[^\\])\$([\s\S]*?)\$/g, (_match, prefix, formula) => saveMath(formula, false, prefix));

    let html = '';
    try {
        html = marked.parse(processedText) as string;
    } catch (e) {
        html = processedText;
    }

    // Restore Math with KaTeX
    mathPlaceholders.forEach(item => {
        let renderedMath = '';
        try {
            const normalized = normalizeFormula(item.code);
            renderedMath = katex.renderToString(normalized, { 
                displayMode: item.displayMode, 
                throwOnError: false, 
                trust: true 
            });
            if (item.displayMode) {
                renderedMath = `<div class="katex-block-wrapper my-2">${renderedMath}</div>`;
            }
        } catch (e) {
            renderedMath = item.displayMode ? `$$${item.code}$$` : `$${item.code}$`;
        }
        
        // Replace all instances of the placeholder
        const regex = new RegExp(item.id, 'g');
        html = html.replace(regex, renderedMath);
    });

    try {
        return { __html: DOMPurify.sanitize(html, { ADD_TAGS: ['u', 'details', 'summary'] }) };
    } catch (e) {
        return { __html: DOMPurify.sanitize(processedText, { ADD_TAGS: ['u', 'details', 'summary'] }) };
    }
};

