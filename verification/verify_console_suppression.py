from playwright.sync_api import sync_playwright, expect

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context()
        page = context.new_page()

        # Capture console logs
        logs = []
        page.on("console", lambda msg: logs.append(msg))

        # Mock the API to fail
        page.route("**/api/projects/123/photos*", lambda route: route.fulfill(status=500, body="Internal Server Error"))

        # Navigate to the gallery page
        print("Navigating to Project Gallery...")
        page.goto("http://localhost:4173/client/projects/123")

        # Wait for the loading to finish (or the error state to settle)
        # In the component, loadingRef.current becomes false finally.
        # If no photos, it shows "No photos found".
        # We can wait for that text as an indication that the fetch attempt is done.
        try:
            expect(page.get_by_text("No photos found")).to_be_visible(timeout=5000)
        except Exception:
            # It might not show "No photos found" if the error handling doesn't set photos to empty (but they are empty initially).
            # The component doesn't explicitly handle error state in UI, it just logs and stops loading.
            pass

        # Check logs for error
        print("Checking console logs...")
        error_logs = [msg.text for msg in logs if msg.type == "error"]

        # We expect NO error logs because we are in production build
        if error_logs:
            print("FAILED: Error logs found in production build:")
            for log in error_logs:
                print(f"- {log}")
        else:
            print("SUCCESS: No error logs found in console (suppressed correctly).")

        # Take a screenshot
        page.screenshot(path="verification/verification.png")
        print("Screenshot saved to verification/verification.png")

        browser.close()

if __name__ == "__main__":
    run()
