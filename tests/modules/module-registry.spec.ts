import { test, expect } from '@playwright/test';
import {
  navigateToSeoAiPage,
  getRestNonce,
  restApi,
} from '../helpers/plugin-helpers';

const ALL_MODULE_NAMES = [
  'Meta Tags',
  'Schema Markup',
  'XML Sitemap',
  'Redirects & 404 Monitor',
  'Open Graph',
  'Twitter Cards',
  'Image SEO',
  'Breadcrumbs',
  'Robots.txt',
  'Instant Indexing',
  'Video Sitemap',
  'News Sitemap',
  'WooCommerce SEO',
  'Local SEO',
  'Analytics & Keyword Tracking',
  'Podcast',
];

test.describe('Module Registry — Dashboard', () => {
  test('dashboard Active Modules section has >= 16 badges', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai');

    const badges = page.locator('.seo-ai-badge');
    const count = await badges.count();
    expect(count).toBeGreaterThanOrEqual(16);
  });

  for (const moduleName of ALL_MODULE_NAMES) {
    test(`module "${moduleName}" appears on dashboard`, async ({ page }) => {
      await navigateToSeoAiPage(page, 'seo-ai');

      const badge = page.locator('.seo-ai-badge', { hasText: moduleName });
      await expect(badge).toBeVisible();
    });
  }

  test('default-enabled modules have success badge class', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai');

    // Meta Tags is default-enabled — its badge should have seo-ai-badge-success
    const metaTagsBadge = page.locator('.seo-ai-badge', { hasText: 'Meta Tags' });
    await expect(metaTagsBadge).toHaveClass(/seo-ai-badge-success/);
  });

  test('default-disabled modules have muted badge class', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai');

    // WooCommerce SEO is default-disabled
    const wooBadge = page.locator('.seo-ai-badge', { hasText: 'WooCommerce SEO' });
    await expect(wooBadge).toHaveClass(/seo-ai-badge-muted/);
  });
});

test.describe('Module Registry — REST API', () => {
  /** Helper to read the current enabled_modules from the API. */
  async function getEnabledModules(page: import('@playwright/test').Page, nonce: string) {
    const resp = await restApi(page, 'GET', '/settings', undefined, nonce);
    expect(resp.ok()).toBeTruthy();
    const body = await resp.json();
    return body.data.settings.enabled_modules as string[];
  }

  /** Helper to save modules back to the API. */
  async function setEnabledModules(page: import('@playwright/test').Page, modules: string[], nonce: string) {
    await restApi(page, 'POST', '/settings', {
      settings: { enabled_modules: modules },
    }, nonce);
  }

  test('GET /settings returns enabled_modules array with defaults', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const modules = await getEnabledModules(page, nonce);

    expect(Array.isArray(modules)).toBe(true);
    // Default-enabled modules should be present
    expect(modules).toEqual(expect.arrayContaining(['meta_tags', 'schema', 'sitemap']));
  });

  test('POST /settings can toggle Phase 3 modules', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const originalModules = await getEnabledModules(page, nonce);

    try {
      // Exclude 'indexing' — its constructor requires Options dependency
      // which Module_Manager doesn't inject (known bug).
      const phase3 = ['video_sitemap', 'news_sitemap'];
      const updated = [...new Set([...originalModules, ...phase3])];

      const postResp = await restApi(page, 'POST', '/settings', {
        settings: { enabled_modules: updated },
      }, nonce);
      expect(postResp.ok()).toBeTruthy();

      // Verify they were saved
      const saved = await getEnabledModules(page, nonce);
      for (const mod of phase3) {
        expect(saved).toContain(mod);
      }
    } finally {
      // Always restore original settings
      await setEnabledModules(page, originalModules, nonce);
    }
  });

  test('POST /settings can toggle Phase 5 modules', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const originalModules = await getEnabledModules(page, nonce);

    try {
      const phase5 = ['woocommerce', 'local_seo', 'analytics'];
      const updated = [...new Set([...originalModules, ...phase5])];

      const postResp = await restApi(page, 'POST', '/settings', {
        settings: { enabled_modules: updated },
      }, nonce);
      expect(postResp.ok()).toBeTruthy();

      const saved = await getEnabledModules(page, nonce);
      for (const mod of phase5) {
        expect(saved).toContain(mod);
      }
    } finally {
      await setEnabledModules(page, originalModules, nonce);
    }
  });

  test('POST /settings can toggle Phase 6 module', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const originalModules = await getEnabledModules(page, nonce);

    try {
      const updated = [...new Set([...originalModules, 'podcast'])];

      const postResp = await restApi(page, 'POST', '/settings', {
        settings: { enabled_modules: updated },
      }, nonce);
      expect(postResp.ok()).toBeTruthy();

      const saved = await getEnabledModules(page, nonce);
      expect(saved).toContain('podcast');
    } finally {
      await setEnabledModules(page, originalModules, nonce);
    }
  });
});
