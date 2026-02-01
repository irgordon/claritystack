from playwright.sync_api import sync_playwright, expect

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context()
        page = context.new_page()

        # Mock the initial settings load
        page.route("**/api/admin/settings", lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body='{"storage_driver": "local", "cloudinary_name": "", "cloudinary_key": "", "s3_key": "", "s3_bucket": "", "s3_region": "us-east-1", "s3_endpoint": "", "imagekit_public": "", "imagekit_url": ""}'
        ))

        print("Navigating to Settings Storage...")
        page.goto("http://localhost:4173/admin/settings/storage")

        # Wait for page to load
        try:
            expect(page.get_by_text("Loading configuration...")).to_be_hidden(timeout=5000)
            expect(page.get_by_text("Storage Settings")).to_be_visible()
        except:
            print("Timed out waiting for loading to finish.")

        # Take a screenshot
        print("Taking screenshot...")
        page.screenshot(path="verification/verification.png")
        print("Screenshot saved to verification/verification.png")

        browser.close()

if __name__ == "__main__":
    run()
