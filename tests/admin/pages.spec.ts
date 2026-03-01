import { test, expect } from '@playwright/test';
import {
  navigateToSeoAiPage,
  expectAssetLoaded,
  expectSeoAiGlobal,
} from '../helpers/plugin-helpers';

test.describe('Admin Pages', () => {
  const pages = [
    { slug: 'seo-ai', label: 'Dashboard', hasVersion: true },
    { slug: 'seo-ai-settings', label: 'Settings', hasVersion: true },
    { slug: 'seo-ai-redirects', label: 'Redirects', hasVersion: false },
    { slug: 'seo-ai-404-log', label: '404 Log', hasVersion: false },
  ] as const;

  for (const p of pages) {
    test(`${p.label} page loads`, async ({ page }) => {
      await navigateToSeoAiPage(page, p.slug);
      await expect(page.locator('.seo-ai-header')).toBeVisible();
      if (p.hasVersion) {
        await expect(page.locator('.seo-ai-version')).toBeVisible();
      }
    });
  }
});

test.describe('Settings Tabs', () => {
  const tabs = [
    'general',
    'providers',
    'content',
    'schema',
    'social',
    'sitemap',
    'redirects',
    'advanced',
  ];

  for (const tab of tabs) {
    test(`tab "${tab}" navigates correctly`, async ({ page }) => {
      await page.goto(`/wp-admin/admin.php?page=seo-ai-settings&tab=${tab}`);
      await expect(page.locator('.seo-ai-header')).toBeVisible();

      const activeTab = page.locator('.nav-tab-active');
      await expect(activeTab).toHaveAttribute('href', new RegExp(`tab=${tab}`));
    });
  }
});

test.describe('Admin Assets', () => {
  test('CSS and JS assets load on dashboard', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai');

    await expectAssetLoaded(page, 'admin.css');
    await expectAssetLoaded(page, 'admin.js');
  });

  test('settings assets load on settings page', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai-settings');

    await expectAssetLoaded(page, 'admin.css');
    await expectAssetLoaded(page, 'admin.js');
    await expectAssetLoaded(page, 'settings.css');
    await expectAssetLoaded(page, 'settings.js');
  });

  test('seoAi global is present with expected properties', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai');
    await expectSeoAiGlobal(page);
  });
});
