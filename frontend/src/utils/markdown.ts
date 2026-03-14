import { marked } from 'marked';
import katex from 'katex';
import DOMPurify from 'dompurify';

marked.setOptions({ breaks: true, gfm: true });

const apiUrl = import.meta.env.VITE_API_BASE_URL || (import.meta.env.PROD ? '' : 'http://localhost:8000');

export const renderMd = (text: string) => {
    if (!text) return { __html: '' };
    
    // 1. Initial cleanup of common escaping issues from the database
    // We convert double backslashes to single ones to fix commands like \\to becoming \to.
    // Also fix form-feed and literal \n strings.
    let processedText = String(text)
        .replace(/\\\\/g, '\\') // Fix DB escaping: \\to -> \to
        .replace(/\f/g, '\\f') // Fix form-feed
        .replace(/\\n/g, '\n'); // Fix literal \n strings

    // 2. --- LaTeX Delimiters Logic (Placeholder System) ---
    // Extract math BEFORE any other string manipulations to protect backslashes
    const mathPlaceholders: { id: string; code: string; displayMode: boolean; prefix: string }[] = [];

    const saveMath = (mathCode: string, displayMode: boolean, prefix = '') => {
        // Use a unique placeholder that marked is EXTREAMELY unlikely to corrupt
        const id = `@@@MATH_PROTECTED_ID_${mathPlaceholders.length}@@@`;
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
    // Slightly more robust regex to avoid matching escaped \$
    processedText = processedText.replace(/(^|[^\\])\$([\s\S]*?)\$/g, (_match, prefix, formula) => saveMath(formula, false, prefix));

    // Now proceed with normal transformations on the remaining text
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
        .replace(/&lt;img\s+(.*?)\s*&gt;/gi, '<img $1>')
        .replace(/&lt;br\s*\/?&gt;/gi, '<br>')
        .replace(/&#0?39;/g, "'")
        .replace(/&quot;/g, '"');

    // Remove newlines and <br> around details/summary to prevent excess spacing
    processedText = processedText
        .replace(/(<(?:details|summary|b|i|u|strong|em)>)\s*(?:[\r\n]|<br\s*\/?>)+/gi, '$1')
        .replace(/(?:[\r\n]|<br\s*\/?>)+\s*(<\/(?:details|summary|b|i|u|strong|em)>)/gi, '$1')
        .replace(/(<\/(?:details|summary|b|i|u|strong|em)>)\s*(?:[\r\n]|<br\s*\/?>)+/gi, '$1')
        .replace(/(?:[\r\n]|<br\s*\/?>)+\s*(<(?:details|summary|b|i|u|strong|em)>)/gi, '$1');

    // Fix Markdown Image URLs: ![alt](/storage/path), ![alt](storage/path), ![alt](questoes/path)
    processedText = processedText.replace(/!\[(.*?)\]\(\s*(\/?(?:storage\/|questoes\/).*?)\s*\)/g, (_, alt, url) => {
        const cleanUrl = url.trim().replace(/^\/?storage\//, '').replace(/^\//, ''); 
        return `![${alt}](${apiUrl}/storage/${cleanUrl})`;
    });

    // Prevention: Escape numeric starts that look like list items (e.g. "30.")
    if (/^\d+\.($|\s)/.test(processedText.trim()) && !processedText.includes('\n')) {
        processedText = processedText.replace(/^(\d+)\./, '$1\\.');
    }

    // Fix HTML Image URLs: src="/storage/path", src="storage/path", src="questoes/path"
    processedText = processedText.replace(/src=["']\s*(\/?(?:storage\/|questoes\/).*?)\s*["']/g, (_, url) => {
        const cleanUrl = url.trim().replace(/^\/?storage\//, '').replace(/^\//, '');
        return `src="${apiUrl}/storage/${cleanUrl}"`;
    });

    const normalizeFormula = (f: string) => {
        // Pre-process Formula to support KaTeX compatibility
        return f
            .replace(/\\begin{tabular}(\{.*?\})/g, '\\begin{array}$1')
            .replace(/\\end{tabular}/g, '\\end{array}')
            .replace(/&amp;/g, '&')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&quot;/g, '"')
            .replace(/&#39;/g, "'");
    };

    let html = '';
    try {
        html = marked.parse(processedText) as string;
    } catch (e) {
        html = processedText;
    }

    // 3. Restore Math with KaTeX
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
        
        // Use split/join for safer replacement of the placeholder
        html = html.split(item.id).join(renderedMath);
    });

    // 4. Final Sanitization
    try {
        return { __html: DOMPurify.sanitize(html, { 
            ADD_TAGS: ['u', 'details', 'summary', 'img', 'span', 'div', 'math', 'svg', 'path', 'use', 'annotation', 'semantics', 'msubsup', 'mrow', 'mi', 'mo', 'mn', 'mstyle', 'mtable', 'mtr', 'mtd', 'mspace', 'msqrt', 'mfrac', 'mover', 'munder', 'munderover'], 
            ADD_ATTR: ['src', 'alt', 'class', 'loading', 'style', 'aria-hidden', 'viewBox', 'd', 'role', 'width', 'height', 'encoding', 'mathbackground', 'mathcolor', 'mathsize', 'mathvariant', 'display'] 
        }) };
    } catch (e) {
        return { __html: DOMPurify.sanitize(processedText, { 
            ADD_TAGS: ['u', 'details', 'summary', 'img', 'span', 'div'], 
            ADD_ATTR: ['src', 'alt', 'class', 'loading'] 
        }) };
    }
};

