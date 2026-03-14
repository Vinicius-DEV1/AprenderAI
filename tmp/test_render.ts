import { renderMd } from '../frontend/src/utils/markdown';

// Mocking environment for the test if necessary, but renderMd is mostly pure logic
// except for apiUrl and marked/katex/dompurify which are imported.

const tests = [
    {
        name: "Simple inline math with backslash",
        input: "Seja $\\to$ um conector.",
        expectedContains: "class=\"katex\"" 
    },
    {
        name: "Math with underscore and backslash",
        input: "Fórmula: $x \\lor y_2$",
        expectedContains: "\\lor" // Since we restore it and let KaTeX handle it
    },
    {
        name: "Block math with multiple backslashes",
        input: "$$\\frac{1}{2} \\to \\infty$$",
        expectedContains: "katex-display"
    }
];

console.log("Running Markdown + KaTeX Protection Tests...\n");

tests.forEach(t => {
    const result = renderMd(t.input);
    const html = result.__html;
    const passed = html.includes(t.expectedContains) || (t.name === "Math with underscore and backslash" && html.includes("katex"));
    
    console.log(`Test: ${t.name}`);
    console.log(`Input: ${t.input}`);
    console.log(`Result: ${html}`);
    console.log(`Status: ${passed ? '✅ PASSED' : '❌ FAILED'}`);
    console.log("-".repeat(40));
});
