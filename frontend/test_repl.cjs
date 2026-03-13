const str = "foo&#039;bar";
console.log("Original: " + str);
console.log("Replaced: " + str.replace(/&#0?39;/g, "'"));

const { marked } = require('marked');
console.log("Marked: " + marked.parse(str.replace(/&#0?39;/g, "'")));
