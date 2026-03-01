import { test, expect } from '@playwright/test';

test.describe('Sitemap Settings Tab', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=seo-ai-settings&tab=sitemap');
    await expect(page.locator('.seo-ai-header')).toBeVisible();
  });

  test('sitemap tab loads with correct indicator', async ({ page }) => {
    const activeTab = page.locator('.nav-tab-active');
    await expect(activeTab).toHaveAttribute('href', /tab=sitemap/);
  });

  test('enable XML sitemap toggle exists', async ({ page }) => {
    const toggle = page.locator('input[name="seo_ai_settings[sitemap_enabled]"]');
    await expect(toggle).toBeAttached();
  });

  test('max entries per sitemap field exists', async ({ page }) => {
    const field = page.locator('#seo_ai_sitemap_max, input[name="seo_ai_settings[sitemap_max_entries]"]');
    await expect(field.first()).toBeAttached();

    // Should be a number input
    const type = await field.first().getAttribute('type');
    expect(type).toBe('number');
  });

  test('include images toggle exists', async ({ page }) => {
    const toggle = page.locator('input[name="seo_ai_settings[sitemap_include_images]"]');
    await expect(toggle).toBeAttached();
  });

  test('Video Sitemap section heading exists', async ({ page }) => {
    const heading = page.locator('h2, h3, .seo-ai-section-title').filter({ hasText: /Video Sitemap/i });
    await expect(heading).toBeVisible();
  });

  test('enable video sitemap toggle exists', async ({ page }) => {
    const toggle = page.locator('input[name="seo_ai_settings[video_sitemap_enabled]"]');
    await expect(toggle).toBeAttached();
  });

  test('News Sitemap section heading exists', async ({ page }) => {
    const heading = page.locator('h2, h3, .seo-ai-section-title').filter({ hasText: /News Sitemap/i });
    await expect(heading).toBeVisible();
  });

  test('enable news sitemap toggle exists', async ({ page }) => {
    const toggle = page.locator('input[name="seo_ai_settings[news_sitemap_enabled]"]');
    await expect(toggle).toBeAttached();
  });

  test('publication name field exists', async ({ page }) => {
    const field = page.locator('#seo_ai_news_pub_name, input[name="seo_ai_settings[news_sitemap_publication_name]"]');
    await expect(field.first()).toBeAttached();
  });

  test('news post types checkboxes exist', async ({ page }) => {
    const checkboxes = page.locator('input[name="seo_ai_settings[news_sitemap_post_types][]"]');
    const count = await checkboxes.count();
    expect(count).toBeGreaterThanOrEqual(1);
  });
});
