from playwright.sync_api import Page, expect, sync_playwright

def test_dashboard_load(page: Page):
    print("Navigating to root...")
    page.goto("http://localhost:3000/")

    print("Waiting for loading spinner...")
    try:
        expect(page.get_by_text("Loading Dashboard...")).to_be_hidden(timeout=10000)
    except Exception as e:
        print(f"Loading spinner did not disappear: {e}")
        page.screenshot(path="verification/dashboard_failure.png")
        raise e

    print("Checking for Dashboard title...")
    expect(page.get_by_role("heading", name="Dashboard")).to_be_visible()

    print("Checking for N/A value...")
    # Allow multiple N/A elements, just ensure at least one is visible
    expect(page.get_by_text("N/A").first).to_be_visible()

    page.screenshot(path="verification/dashboard_production.png")
    print("Screenshot saved to verification/dashboard_production.png")

if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        console_errors = []
        page.on("console", lambda msg: console_errors.append(msg.text) if msg.type == "error" else None)

        try:
            test_dashboard_load(page)

            # Verify no console errors related to fetch
            fetch_errors = [msg for msg in console_errors if "Failed to fetch dashboard data" in msg]
            if fetch_errors:
                print("FAIL: Console error detected in production build!")
                print(fetch_errors)
                exit(1)
            else:
                print("SUCCESS: No console errors detected in production build.")

        except Exception as e:
            print(f"Test failed: {e}")
            exit(1)
        finally:
            browser.close()
