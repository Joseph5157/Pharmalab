"""Manual browser acceptance check for SYNC-SPIKE-01.

Run against the local Docker stack with: python tests/Browser/sync_spike.py
"""

from datetime import datetime
from pathlib import Path

from playwright.sync_api import Page, sync_playwright


BASE_URL = "http://127.0.0.1:8000"
EMAIL = "student@pharmalab.test"
PASSWORD = "password"


def login(page: Page) -> None:
    page.goto(f"{BASE_URL}/login")
    page.wait_for_load_state("networkidle")
    if page.url.endswith("/student"):
        return
    page.locator("#email").wait_for(timeout=20_000)
    page.locator("#email").fill(EMAIL)
    page.locator("#password").fill(PASSWORD)
    page.locator('[data-test="login-button"]').click()
    page.wait_for_url("**/student")


def wait_for_state(page: Page, label: str) -> None:
    page.locator('[data-test="save-status"]').get_by_text(label, exact=True).wait_for(
        timeout=20_000
    )


def main() -> None:
    run = datetime.now().strftime("%H%M%S")
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        device = browser.new_context(viewport={"width": 390, "height": 844})
        remote = browser.new_context(viewport={"width": 900, "height": 800})
        page = device.new_page()
        other_page = remote.new_page()

        login(page)
        page.goto(f"{BASE_URL}/student/sync-spike")
        page.wait_for_load_state("networkidle")
        note_url = page.url

        online_text = f"Online autosave {run}"
        page.locator('[data-test="case-note"]').fill(online_text)
        wait_for_state(page, "Saved on server")
        assert page.locator('[data-test="case-note"]').input_value() == online_text

        device.set_offline(True)
        recovered_text = f"Recovered after offline refresh {run}"
        page.locator('[data-test="case-note"]').fill(recovered_text)
        wait_for_state(page, "Saved on this device")
        try:
            page.reload(timeout=5_000)
        except Exception:
            pass  # The page shell is intentionally not service-worker cached in this spike.
        device.set_offline(False)
        page.goto(note_url)
        page.wait_for_load_state("networkidle")
        wait_for_state(page, "Saved on server")
        assert page.locator('[data-test="case-note"]').input_value() == recovered_text

        login(other_page)
        other_page.goto(note_url)
        other_page.wait_for_load_state("networkidle")

        device.set_offline(True)
        local_conflict_text = f"Device side of conflict {run}"
        page.locator('[data-test="case-note"]').fill(local_conflict_text)
        wait_for_state(page, "Saved on this device")

        server_conflict_text = f"Newer server side {run}"
        other_page.locator('[data-test="case-note"]').fill(server_conflict_text)
        wait_for_state(other_page, "Saved on server")

        device.set_offline(False)
        wait_for_state(page, "Conflict — Review changes")
        panel = page.locator('[data-test="conflict-panel"]')
        assert panel.get_by_text("Use server version", exact=True).is_visible()
        assert panel.get_by_text("Keep local draft as a copy", exact=True).is_visible()
        assert panel.get_by_text("Replace server version", exact=True).is_visible()
        panel.screenshot(path=str(Path("storage") / "sync-spike-mobile-conflict.png"))

        panel.get_by_text("Keep local draft as a copy", exact=True).click()
        wait_for_state(page, "Saved on server")
        assert page.locator('[data-test="case-note"]').input_value() == server_conflict_text

        # An unsynced draft must be removed before the logout request is sent.
        page.route(
            "**/student/sync-spike/**",
            lambda route: route.abort()
            if route.request.method == "PUT"
            else route.continue_(),
        )
        page.locator('[data-test="case-note"]').fill(f"Clear on logout {run}")
        wait_for_state(page, "Sync failed — Retry")
        page.set_viewport_size({"width": 1100, "height": 844})
        page.locator('[data-test="sidebar-menu-button"]').click()
        page.locator('[data-test="logout-button"]').click()
        page.wait_for_url(f"{BASE_URL}/")
        stored_records = page.evaluate(
            """async () => {
                const request = indexedDB.open('pharmalab-case-drafts', 1);
                const db = await new Promise((resolve, reject) => {
                    request.onsuccess = () => resolve(request.result);
                    request.onerror = () => reject(request.error);
                });
                if (!db.objectStoreNames.contains('drafts')) return 0;
                const transaction = db.transaction(['drafts', 'copies']);
                const count = store => new Promise((resolve, reject) => {
                    const result = transaction.objectStore(store).count();
                    result.onsuccess = () => resolve(result.result);
                    result.onerror = () => reject(result.error);
                });
                return (await count('drafts')) + (await count('copies'));
            }"""
        )
        assert stored_records == 0

        remote.close()
        device.close()
        browser.close()
        print("PASS: online, offline refresh recovery, reconnect, conflict UI, and logout clearing")


if __name__ == "__main__":
    main()
