import { test, expect } from '@playwright/test';
import { navigateToSeoAiPage } from '../helpers/plugin-helpers';

test.describe('Redirects Page', () => {
  test('redirects page loads with form', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai-redirects');

    const form = page.locator('#seo-ai-add-redirect');
    await expect(form).toBeVisible();
  });

  test('add redirect form has required fields', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai-redirects');

    await expect(page.locator('input[name="source_url"]')).toBeVisible();
    await expect(page.locator('input[name="target_url"]')).toBeVisible();
    await expect(page.locator('select[name="type"]')).toBeVisible();
  });

  test('redirect appears in table after creation via REST API', async ({ page }) => {
    // Navigate to wp-admin to get a REST nonce
    await page.goto('/wp-admin/');
    await page.waitForLoadState('domcontentloaded');

    const nonce = await page.evaluate(() => {
      return (window as any).wpApiSettings?.nonce as string | undefined;
    }) || await page.evaluate(async () => {
      const res = await fetch('/wp-admin/admin-ajax.php?action=rest-nonce', {
        credentials: 'same-origin',
      });
      return res.text();
    });

    const timestamp = Date.now();
    const sourceUrl = `/test-source-${timestamp}`;
    const targetUrl = `/test-target-${timestamp}`;

    // Create redirect via REST API (the actual functional path)
    const response = await page.request.post('/wp-json/seo-ai/v1/redirects', {
      headers: { 'X-WP-Nonce': nonce },
      data: {
        source_url: sourceUrl,
        target_url: targetUrl,
        type: 301,
      },
    });

    expect(response.ok()).toBeTruthy();

    // Navigate to redirects page and verify it appears
    await navigateToSeoAiPage(page, 'seo-ai-redirects');
    await expect(page.locator(`text=${sourceUrl}`)).toBeVisible({ timeout: 5000 });

    // Clean up: delete the redirect
    const body = await response.json();
    const redirectId = body?.data?.redirect?.id;
    if (redirectId) {
      await page.request.delete(`/wp-json/seo-ai/v1/redirects/${redirectId}`, {
        headers: { 'X-WP-Nonce': nonce },
      });
    }
  });
});
