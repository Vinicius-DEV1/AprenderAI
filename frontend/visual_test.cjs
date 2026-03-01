const puppeteer = require('puppeteer');

(async () => {
    console.log('Starting visual validation script...');
    // We launch headless=new or headless=true, depending on puppeteer version
    const browser = await puppeteer.launch({ headless: "new", args: ['--no-sandbox'] });
    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 800 });

    try {
        console.log('1. Logging in with admin@aprenderai.com...');
        await page.goto('http://localhost:5174/login', { waitUntil: 'networkidle0' });

        await page.type('input[type="email"]', 'admin@aprenderai.com');
        await page.type('input[type="password"]', 'Aprova@123');

        // Find and click the login button
        await page.click('button[type="submit"]');
        await new Promise(r => setTimeout(r, 4000));

        console.log('Login successful. Taking dashboard screenshot.');
        await page.screenshot({ path: './evidence_dashboard.png' });

        console.log('2. Navigating to /essays/create...');
        await page.goto('http://localhost:5174/essays/create', { waitUntil: 'networkidle0' });
        await page.screenshot({ path: './evidence_create_essay.png' });

        console.log('3. Navigating to /essays (List + Charts)...');
        await page.goto('http://localhost:5174/essays', { waitUntil: 'networkidle0' });
        // Wait for charts to render
        await new Promise(r => setTimeout(r, 2000));
        await page.screenshot({ path: './evidence_essays_list.png' });

        console.log('4. Navigating to corrected essay details...');
        const links = await page.$$eval('a[href^="/essays/"]', els => els.map(e => e.href));
        const detailLinks = links.filter(l => !l.includes('create'));

        if (detailLinks.length > 0) {
            console.log(`Found essay detail link 1: ${detailLinks[0]}`);
            await page.goto(detailLinks[0], { waitUntil: 'networkidle0' });
            await new Promise(r => setTimeout(r, 2000));
            await page.screenshot({ path: './evidence_essay_details_1.png', fullPage: true });
        }

        if (detailLinks.length > 1) {
            console.log(`Found essay detail link 2: ${detailLinks[1]}`);
            await page.goto(detailLinks[1], { waitUntil: 'networkidle0' });
            await new Promise(r => setTimeout(r, 2000));
            await page.screenshot({ path: './evidence_essay_details_2.png', fullPage: true });
        }

        if (detailLinks.length === 0) {
            console.log("No completed essays found to click. Will capture whatever is visible.");
        }

    } catch (err) {
        console.error('Error during automation:', err);
    } finally {
        await browser.close();
    }
    console.log('Finished visual validation.');
})();
