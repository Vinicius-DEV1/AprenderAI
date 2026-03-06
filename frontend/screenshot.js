const puppeteer = require('puppeteer');
const path = require('path');

(async () => {
    const browser = await puppeteer.launch({ headless: 'new' });
    const page = await browser.newPage();

    // Configura viewport para desktop
    await page.setViewport({ width: 1280, height: 1024 });

    console.log("Navigating to login...");
    await page.goto('http://localhost:5174/login', { waitUntil: 'networkidle2' });

    console.log("Filling credentials...");
    await page.type('input[type="email"]', 'admin@aprenderai.com');
    await page.type('input[type="password"]', 'password');

    console.log("Submitting login...");
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle2' }),
        page.click('button[type="submit"]')
    ]);

    console.log("Navigating to essay correction page...");
    await page.goto('http://localhost:5174/redacao/correcao/12', { waitUntil: 'networkidle2' });

    console.log("Waiting for the page and tabs to load...");
    // Wait for the "Resumo da Avaliação" block to appear
    await page.waitForSelector('h3:contains("Resumo da Avaliação")', { timeout: 10000 }).catch(() => console.log('Selector timeout but continuing...'));
    await page.waitForTimeout(2000); // give it an extra 2s to render

    const screenshotPath = 'C:\\Users\\anton\\.gemini\\antigravity\\brain\\ac7d7344-c318-410e-ab9e-b7312a3f0e65\\resumo_tab_premium.png';
    console.log(`Taking screenshot: ${screenshotPath}`);
    await page.screenshot({ path: screenshotPath, fullPage: true });

    await browser.close();
    console.log("Done.");
})();
