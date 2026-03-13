const { marked } = require('marked');
const katex = require('katex');

marked.setOptions({ breaks: true, gfm: true });

function renderMd(text) {
    if (!text) return '';
    let processedText = String(text);

    // Unescape secure tags and basic chars
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
        .replace(/&lt;br\s*\/?&gt;/gi, '<br>')
        .replace(/&#39;/g, "'")
        .replace(/&quot;/g, '"');

    const normalizeFormula = (f) => {
        return f
            .replace(/\\begin{tabular}(\{.*?\})/g, '\\begin{array}$1')
            .replace(/\\end{tabular}/g, '\\end{array}')
            .replace(/&amp;/g, '&')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>');
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
        
        const regex = new RegExp(item.id, 'g');
        html = html.replace(regex, renderedMath);
    });

    return html;
}

const test1 = "Nesse caso, o piso exerce sobre o caixote uma força $\\vec{f}'$. Essas forças $\\vec{f}$ e $\\vec{f}'$ são tais que";
const test2 = "Nesse caso, o piso exerce sobre o caixote uma força \\vec{f}&#039;. Essas forças \\vec{f} e \\vec{f}&#039; são tais que";

console.log("TEST 1:", renderMd(test1));
console.log("TEST 2:", renderMd(test2));
