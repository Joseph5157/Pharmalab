"""Browser acceptance flow for ADM-FOUNDATION-01.

Run against the local Docker stack with: python tests/Browser/adm_foundation.py
"""

from datetime import datetime
from pathlib import Path

from playwright.sync_api import Page, sync_playwright


BASE_URL = "http://127.0.0.1:8000"


def login(page: Page) -> None:
    page.goto(f"{BASE_URL}/login")
    page.wait_for_load_state("networkidle")
    page.locator("#email").fill("admin@pharmalab.test")
    page.locator("#password").fill("password")
    page.locator('[data-test="login-button"]').click()
    page.wait_for_url("**/admin")


def submit_and_wait(page: Page, button: str) -> None:
    with page.expect_response(
        lambda response: response.request.method == "POST"
        and response.url.startswith(BASE_URL),
        timeout=30_000,
    ):
        page.get_by_role("button", name=button, exact=True).click()
    page.wait_for_load_state("networkidle")


def main() -> None:
    run = datetime.now().strftime("%H%M%S")
    programme_name = f"Clinical Pharmacy {run}"
    programme_code = f"CP{run}"
    cohort_name = f"Pilot intake {run}"
    site_name = f"North Teaching Hospital {run}"
    site_code = f"NTH{run}"
    department_name = f"Medicine {run}"
    ward_name = f"Ward {run}"
    ward_code = f"W{run}"
    student_name = f"Student {run}"
    faculty_name = f"Faculty {run}"
    rotation_name = f"Medicine block {run}"
    errors: list[str] = []

    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        context = browser.new_context(viewport={"width": 390, "height": 844})
        page = context.new_page()
        page.on("console", lambda message: errors.append(message.text) if message.type == "error" else None)
        login(page)

        page.goto(f"{BASE_URL}/admin/academic")
        page.wait_for_load_state("networkidle")
        page.locator("#programme-name").fill(programme_name)
        page.locator("#programme-code").fill(programme_code)
        page.locator("#duration").fill("4")
        submit_and_wait(page, "Create programme")
        page.locator("#cohort-programme").select_option(label=programme_name)
        page.locator("#cohort-name").fill(cohort_name)
        page.locator("#admission-year").fill("2026")
        page.locator("#academic-label").fill("2026-27")
        submit_and_wait(page, "Create cohort")
        page.get_by_text(cohort_name, exact=True).wait_for()

        page.goto(f"{BASE_URL}/admin/clinical-sites")
        page.wait_for_load_state("networkidle")
        page.locator("#site-name").fill(site_name)
        page.locator("#site-code").fill(site_code)
        submit_and_wait(page, "Add site")
        page.locator("#department-site").select_option(label=site_name)
        page.locator("#department-name").fill(department_name)
        submit_and_wait(page, "Add department")
        page.locator("#ward-site").select_option(label=site_name)
        page.locator("#ward-department").select_option(label=department_name)
        page.locator("#ward-name").fill(ward_name)
        page.locator("#ward-code").fill(ward_code)
        submit_and_wait(page, "Add ward")
        page.get_by_text(f"{ward_name} ({ward_code})", exact=False).wait_for()

        page.goto(f"{BASE_URL}/admin/people")
        page.wait_for_load_state("networkidle")
        for name, email, role in [
            (student_name, f"student.{run}@example.test", "student"),
            (faculty_name, f"faculty.{run}@example.test", "faculty"),
        ]:
            page.locator("#person-name").fill(name)
            page.locator("#person-email").fill(email)
            page.locator("#person-role").select_option(role)
            page.locator("#person-password").fill("BrowserPass1!")
            page.locator("#person-confirmation").fill("BrowserPass1!")
            submit_and_wait(page, "Create account")
            page.get_by_text(name, exact=True).wait_for()

        page.goto(f"{BASE_URL}/admin/rotations")
        page.wait_for_load_state("networkidle")
        page.locator("#rotation-name").fill(rotation_name)
        page.locator("#rotation-programme").select_option(label=programme_name)
        page.locator("#rotation-cohort").select_option(label=cohort_name)
        page.locator("#rotation-site").select_option(label=site_name)
        page.locator("#rotation-department").select_option(label=department_name)
        page.locator("#rotation-ward").select_option(label=ward_name)
        page.locator("#starts-on").fill("2026-10-01")
        page.locator("#ends-on").fill("2026-10-31")
        page.locator("#rotation-status").select_option("active")
        submit_and_wait(page, "Create rotation")

        page.locator("#assignment-rotation").select_option(label=rotation_name)
        page.locator("#assignment-student").select_option(label=student_name)
        page.locator("#assignment-faculty").select_option(label=faculty_name)
        submit_and_wait(page, "Save assignment")
        rotation_card = page.get_by_role("heading", name=rotation_name).locator("xpath=ancestor::article")
        rotation_card.get_by_text(student_name, exact=True).wait_for()
        rotation_card.get_by_text(f"Preceptor: {faculty_name}", exact=True).wait_for()
        rotation_card.screenshot(path=str(Path("storage") / "adm-foundation-mobile-rotation.png"))

        page.set_viewport_size({"width": 1280, "height": 900})
        page.reload()
        page.wait_for_load_state("networkidle")
        page.screenshot(path=str(Path("storage") / "adm-foundation-desktop.png"), full_page=True)

        assert errors == [], f"Browser console errors: {errors}"
        context.close()
        browser.close()

    print("PASS: admin created academic structure, accounts, rotation, and assignment through responsive UI")


if __name__ == "__main__":
    main()
