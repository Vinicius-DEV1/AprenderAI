const fs = require('fs');
const path = require('path');

const srcDir = path.join(__dirname, 'frontend', 'src');

function walk(dir, done) {
    let results = [];
    fs.readdir(dir, function (err, list) {
        if (err) return done(err);
        let i = 0;
        (function next() {
            let file = list[i++];
            if (!file) return done(null, results);
            file = path.resolve(dir, file);
            fs.stat(file, function (err, stat) {
                if (stat && stat.isDirectory()) {
                    walk(file, function (err, res) {
                        results = results.concat(res);
                        next();
                    });
                } else {
                    results.push(file);
                    next();
                }
            });
        })();
    });
}

walk(srcDir, function (err, results) {
    if (err) throw err;

    const filesToProcess = results.filter(f => f.endsWith('.tsx') || f.endsWith('.ts'));

    let count = 0;

    for (const file of filesToProcess) {
        if (file.includes('App.tsx') || file.includes('api/axios.ts')) continue;

        let content = fs.readFileSync(file, 'utf8');
        const original = content;

        if (content.includes('alert(')) {
            // Add import if missing
            if (!content.includes("import { toast } from 'sonner'")) {
                content = "import { toast } from 'sonner';\n" + content;
            }

            // Very simple heuristic for positive/negative/info alerts
            content = content.replace(/alert\((['"`].*(?:Erro|Falha|Verifique|Problema).*?['"`](?:\s*\+\s*.*?)?)\)/gi, 'toast.error($1)');
            content = content.replace(/alert\((['"`].*(?:sucesso|salv(?:o|as)|removed|redes|Conclu|100%).*?['"`])\)/gi, 'toast.success($1)');

            // Specific hardcoded replacements for "dead ends" based on the user's instructions
            content = content.replace(/alert\(\s*['"`]Transcript logic to be implemented['"`]\s*\)/g, "toast.info('Funcionalidade em manutenção')");
            content = content.replace(/alert\(\s*['"`]Upgrade necessário['"`]\s*\)/g, "toast.info('Funcionalidade em manutenção')");

            // Any remaining generic alert
            content = content.replace(/alert\(/g, 'toast.info(');

            if (content !== original) {
                fs.writeFileSync(file, content);
                count++;
            }
        }
    }

    console.log(`Processed ${count} files.`);
});
