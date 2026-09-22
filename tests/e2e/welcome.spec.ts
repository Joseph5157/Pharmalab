import { expect, test } from '@playwright/test';

test('shows the NEXPHARMED hero section', async ({ page }) => {
    await page.goto('/#hero');
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveTitle(
        'NEXPHARMED — Clinical Case & Practical Platform',
    );
    await expect(
        page.getByRole('heading', {
            name: 'Empowering Pharmacy Education Through Digital Learning & Practice',
        }),
    ).toBeVisible();
});
