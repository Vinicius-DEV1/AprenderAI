const { marked } = require('marked');
const katex = require('katex');

marked.setOptions({ breaks: true, gfm: true });

function renderMd(text) {
    if (!text) return '';
    let processedText = String(text);

    // Unescape secure tags first
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
        .replace(/&lt;br\s*\/?&gt;/gi, '<br>');

    const normalizeFormula = (f) => {
        return f
            .replace(/\\begin{tabular}(\{.*?\})/g, '\\begin{array}$1')
            .replace(/\\end{tabular}/g, '\\end{array}')
            .replace(/&amp;/g, '&')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&quot;/g, '"')
            .replace(/&#39;/g, "'");
    };

    const mathPlaceholders = [];

    const saveMath = (mathCode, displayMode, prefix = '') => {
        const id = `@@MATH_${mathPlaceholders.length}@@`;
        mathPlaceholders.push({ id, code: mathCode, displayMode, prefix });
        return `${prefix}${id}`;
    };

    processedText = processedText.replace(/\$\$([\s\S]*?)\$\$/g, (match, formula) => saveMath(formula, true));
    processedText = processedText.replace(/\\\[([\s\S]*?)\\\]/g, (match, formula) => saveMath(formula, true));
    processedText = processedText.replace(/\\\(([\s\S]*?)\\\)/g, (match, formula) => saveMath(formula, false));
    processedText = processedText.replace(/(^|[^\\])\$([\s\S]*?)\$/g, (match, prefix, formula) => saveMath(formula, false, prefix));

    let html = marked.parse(processedText);

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
        
        // Use regex for global replacement
        const regex = new RegExp(item.id, 'g');
        html = html.replace(regex, item.prefix + renderedMath);
    });

    return html;
}

const test1 = "Texto normal &lt;u&gt;sublinhado&lt;/u&gt;. Fórmulas de estatística $\\mu_D$ e $\\sigma_L$ e $x_i + y_i$. Depois um bloco $$ a_1 + b_1 = c_1 $$.";
console.log(renderMd(test1));

