from playwright.sync_api import sync_playwright
import json
import time

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()

    request_counts = {}

    # Mock the photos API response
    def handle_photos_route(route):
        url = route.request.url
        print(f"Intercepted request: {url}")

        try:
            query_params = url.split('?')[1]
            params = dict(p.split('=') for p in query_params.split('&'))
            page_num = int(params.get('page', 1))
        except:
            page_num = 1

        request_counts[page_num] = request_counts.get(page_num, 0) + 1

        photos = []
        if page_num <= 2:
            # Generate 20 dummy photos per page
            for i in range(20):
                photo_id = (page_num - 1) * 20 + i
                photos.append({
                    "id": photo_id,
                    "token": "dummy_token",
                    "thumb_path": f"/dummy/thumb/{photo_id}.jpg"
                })

        response_body = {
            "data": photos,
            "meta": {
                "current_page": page_num,
                "total_pages": 2
            }
        }

        # Simulate network delay
        time.sleep(0.1)

        route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps(response_body)
        )

    page.route("**/api/projects/123/photos?page=*", handle_photos_route)

    page.route("**/api/files/view/*", lambda route: route.fulfill(
        status=200,
        content_type="image/svg+xml",
        body='<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect width="100" height="100" fill="#ddd"/></svg>'
    ))

    print("Navigating to page...")
    page.goto("http://localhost:3000/client/projects/123")

    print("Waiting for grid...")
    try:
        page.wait_for_selector(".scrollbar-thin", timeout=10000)
        print("Grid loaded.")
    except Exception as e:
        print(f"Timed out waiting for content: {e}")
        browser.close()
        return

    print("Scrolling grid to bottom...")
    # Scroll multiple times
    for i in range(5):
        page.evaluate("const el = document.querySelector('.scrollbar-thin'); if(el) el.scrollTop = el.scrollHeight;")
        time.sleep(0.5)

    total_requests = sum(request_counts.values())
    max_page_requested = max(request_counts.keys()) if request_counts else 0

    print(f"Total requests: {total_requests}")
    print(f"Max page requested: {max_page_requested}")
    print(f"Request counts per page: {request_counts}")

    # Take screenshot
    page.screenshot(path="verification/gallery_fixed.png")
    print("Screenshot saved to verification/gallery_fixed.png")

    if max_page_requested > 2:
        print("FAIL: Infinite loop detected! Requested pages beyond available content.")
    else:
        print("PASS: Request count seems normal.")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
