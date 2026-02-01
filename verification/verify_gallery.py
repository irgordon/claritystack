from playwright.sync_api import sync_playwright
import json

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()

    # Mock the photos API response
    def handle_photos_route(route):
        print(f"Intercepted request: {route.request.url}")
        photos = []
        # Generate 50 dummy photos
        for i in range(50):
            photos.append({
                "id": i,
                "token": "dummy_token",
                "filename": f"photo_{i}.jpg"
            })

        response_body = {
            "data": photos,
            "current_page": 1,
            "last_page": 5
        }

        route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps(response_body)
        )

    # Mock the Project details if needed (though the component didn't seem to fetch it explicitly in the snippet)
    # But often Layouts fetch user/project info. Let's mock a generic user/auth check if needed.
    # secureFetch might check /api/auth/check or similar.
    # For now, let's just mock the photos endpoint.

    page.route("**/api/projects/123/photos?page=*", handle_photos_route)

    # Mock image requests to avoid 404s in console (optional, but cleaner)
    page.route("**/api/files/view/*", lambda route: route.fulfill(
        status=200,
        content_type="image/svg+xml",
        body='<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect width="100" height="100" fill="#ddd"/></svg>'
    ))

    print("Navigating to page...")
    # React Router uses client-side routing.
    # If we go directly to a deep link on a dev server, it should work if configured as SPA fallback.
    page.goto("http://localhost:3000/client/projects/123")

    print("Waiting for grid...")
    # Wait for the grid to render items. The grid creates divs with style.
    # We can look for the "Gallery" text or the grid itself.
    try:
        page.wait_for_selector("text=Gallery", timeout=10000)
        # Wait for at least one grid cell
        page.wait_for_selector("img[alt^='Project photo']", timeout=10000)
        print("Grid loaded.")
    except Exception as e:
        print(f"Timed out waiting for content: {e}")
        page.screenshot(path="verification/error_state.png")
        # Dump console logs
        # page.on("console", lambda msg: print(f"Console: {msg.text}"))

    # Take screenshot
    page.screenshot(path="verification/gallery_optimized.png")
    print("Screenshot saved to verification/gallery_optimized.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
