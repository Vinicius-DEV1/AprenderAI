const puppeteer = require('puppeteer');

(async () => {
    const browser = await puppeteer.launch({ headless: 'new' });
    const page = await browser.newPage();

    // Track network requests
    page.on('response', async (response) => {
        const url = response.url();
        // Log all API responses to catch the error
        if (url.includes('/api/')) {
            console.log(`\n============================`);
            console.log(`[RESPONSE] ${url}`);
            console.log(`Status: ${response.status()}`);

            if (response.status() >= 400) {
                try {
                    console.log(`Body:`, await response.text());
                } catch (e) {
                    console.log(`Could not read body:`, e.message);
                }
            }

            const req = response.request();
            const reqHeaders = req.headers();
            if (response.status() >= 400) {
                console.log(`[REQUEST HEADERS]`, JSON.stringify(reqHeaders, null, 2));
            }
        }
    });

    console.log('Navigating to login page for session bootstrap...');
    await page.goto('http://127.0.0.1:5174/login', { waitUntil: 'networkidle2' });

    console.log('Waiting for form...');
    await page.waitForSelector('#email', { timeout: 10000 });

    console.log('Filling form...');
    await page.type('#email', 'admin@aprenderai.com');
    await page.type('#password', 'Aprova@123');

    console.log('Submitting...');
    await page.click('button[type="submit"]');

    console.log('Waiting for navigation/dashboard redirect...');
    // We expect a redirect to /dashboard or similar
    await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 15000 }).catch(() => console.log('Navigation timeout after login'));

    console.log('Navigating explicitly to dashboard to be sure...');
    await page.goto('http://127.0.0.1:5174/dashboard', { waitUntil: 'networkidle2' });

    console.log('Waiting 5s for any delayed requests...');
    await new Promise(r => setTimeout(r, 5000));

    console.log('Capturing dashboard state...');
    await page.screenshot({ path: 'dashboard_error_check.png', fullPage: true });

    await browser.close();
})();
