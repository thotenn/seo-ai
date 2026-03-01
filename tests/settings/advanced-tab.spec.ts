import { test, expect } from '@playwright/test';

test.describe('Advanced Settings Tab', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=seo-ai-settings&tab=advanced');
    await expect(page.locator('.seo-ai-header')).toBeVisible();
  });

  test('tab loads with correct active tab indicator', async ({ page }) => {
    const activeTab = page.locator('.nav-tab-active');
    await expect(activeTab).toHaveAttribute('href', /tab=advanced/);
  });

  test('CSV export form present with hidden input and Export button', async ({ page }) => {
    const hiddenInput = page.locator('input[name="seo_ai_csv_export"]');
    await expect(hiddenInput).toBeAttached();

    const exportButton = page.locator('input[type="submit"]').filter({ hasText: /Export/i });
    // If submit is value-based, try value attribute
    const altButton = page.locator('input[type="submit"][value*="Export"], button:has-text("Export")');
    const btn = exportButton.or(altButton);
    await expect(btn.first()).toBeAttached();
  });

  test('CSV export has post type selector', async ({ page }) => {
    const postTypeSelect = page.locator('select[name="seo_ai_csv_post_type"]');
    await expect(postTypeSelect).toBeAttached();
  });

  test('CSV import form present with file input and Import button', async ({ page }) => {
    const fileInput = page.locator('input[type="file"][name="seo_ai_csv_file"]');
    await expect(fileInput).toBeAttached();

    const importButton = page.locator('input[type="submit"][value*="Import"], button:has-text("Import")');
    await expect(importButton.first()).toBeAttached();
  });

  test('auto-submit on publish toggle exists', async ({ page }) => {
    const toggle = page.locator('input[name="seo_ai_settings[indexing_auto_submit]"]');
    await expect(toggle).toBeAttached();
  });

  test('Bing API Key field exists', async ({ page }) => {
    const field = page.locator('#seo_ai_bing_key, input[name="seo_ai_settings[bing_api_key]"]');
    await expect(field.first()).toBeAttached();
  });

  test('robots.txt custom rules textarea exists', async ({ page }) => {
    const textarea = page.locator('#seo_ai_robots_rules, textarea[name="seo_ai_settings[robots_custom_rules]"]');
    await expect(textarea.first()).toBeAttached();
  });

  test('View robots.txt link is present', async ({ page }) => {
    const link = page.locator('a[href*="robots.txt"]');
    await expect(link).toBeAttached();
  });

  test('auto alt text toggle exists', async ({ page }) => {
    const toggle = page.locator('input[name="seo_ai_settings[image_auto_alt]"]');
    await expect(toggle).toBeAttached();
  });

  test('alt text template input exists', async ({ page }) => {
    const input = page.locator('#seo_ai_alt_template, input[name="seo_ai_settings[image_alt_template]"]');
    await expect(input.first()).toBeAttached();
  });

  test('alt text case select has 4 options', async ({ page }) => {
    const select = page.locator('#seo_ai_alt_case, select[name="seo_ai_settings[image_alt_case]"]');
    await expect(select.first()).toBeAttached();

    const options = select.first().locator('option');
    await expect(options).toHaveCount(4);
  });

  test('breadcrumb enabled toggle exists', async ({ page }) => {
    const toggle = page.locator('input[name="seo_ai_settings[breadcrumb_enabled]"]');
    await expect(toggle).toBeAttached();
  });

  test('breadcrumb separator input exists', async ({ page }) => {
    const input = page.locator('#seo_ai_breadcrumb_sep, input[name="seo_ai_settings[breadcrumb_separator]"]');
    await expect(input.first()).toBeAttached();
  });
});
