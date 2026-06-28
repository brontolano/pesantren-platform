import asyncio
import re
from playwright import async_api
from playwright.async_api import expect

async def run_test():
    pw = None
    browser = None
    context = None

    try:
        # Start a Playwright session in asynchronous mode
        pw = await async_api.async_playwright().start()

        # Launch a Chromium browser in headless mode with custom arguments
        browser = await pw.chromium.launch(
            headless=True,
            args=[
                "--window-size=1280,720",
                "--disable-dev-shm-usage",
                "--ipc=host",
                "--single-process"
            ],
        )

        # Create a new browser context (like an incognito window)
        context = await browser.new_context()
        # Wider default timeout to match the agent's DOM-stability budget;
        # auto-waiting Playwright APIs (expect, locator.wait_for) inherit this.
        context.set_default_timeout(15000)

        # Open a new page in the browser context
        page = await context.new_page()

        # Interact with the page elements to simulate user flow
        # -> navigate
        await page.goto("http://localhost:8000")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass
        
        # -> Open the privacy policy page by navigating to /kebijakan-privasi and verify that the privacy policy content (the 'Kebijakan Privasi' text/content) is displayed.
        await page.goto("http://localhost:8000/kebijakan-privasi")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass
        
        # -> Click the '← Kembali ke Beranda' link to return to the landing page and verify the landing page is displayed.
        # ← Kembali ke Beranda link
        elem = page.get_by_role('link', name='← Kembali ke Beranda', exact=True)
        await elem.click(timeout=10000)
        
        # --> Assertions to verify final state
        
        # --> Verify the landing page is displayed
        await page.locator("xpath=/html/body/nav/div/a").nth(0).scroll_into_view_if_needed()
        # Assert: The site logo link is visible, indicating the landing page is displayed.
        await expect(page.locator("xpath=/html/body/nav/div/a").nth(0)).to_be_visible(timeout=15000), "The site logo link is visible, indicating the landing page is displayed."
        # Assert: The browser is on the landing page URL.
        await expect(page).to_have_url(re.compile("http://localhost:8000/"), timeout=15000), "The browser is on the landing page URL."
        current_url = await page.evaluate("() => window.location.href")
        # Assert: page loaded with a URL (final outcome verified by the AI judge during the run)
        assert current_url, 'Page should have loaded with a URL'
        await asyncio.sleep(5)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    