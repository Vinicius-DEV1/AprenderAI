const { marked } = require('marked');
const katex = require('katex');

marked.setOptions({ breaks: true, gfm: true });

let text = "Média $\\mu_D$ e desvio $\\sigma_L$ do vetor.";

text = text.replace(/(^|[^\\\\])\\$([\\s\\S]*?)\\$/g, (match, prefix, formula) => {
    try {
        console.log("Found formula:", formula);
        return `${prefix}${katex.renderToString(formula, { displayMode: false, throwOnError: false, trust: true })}`;
    } catch (e) {
        return match;
    }
});

console.log("\n--- After KaTeX ---");
console.log(text);

const html = marked.parse(text);
console.log("\n--- After Marked ---");
console.log(html);
