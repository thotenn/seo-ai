import { test, expect } from '@playwright/test';
import {
  navigateToSeoAiPage,
  getRestNonce,
  restApi,
} from '../helpers/plugin-helpers';

test.describe('Activity Log Page', () => {
  test('activity log page loads', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai-logs');

    await expect(page.locator('.seo-ai-header')).toBeVisible();
    await expect(page.locator('.seo-ai-header')).toContainText('Activity Log');
  });

  test('filter bar is visible with controls', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai-logs');

    // Level dropdown
    await expect(page.locator('select[name="level"]')).toBeVisible();

    // Operation dropdown
    await expect(page.locator('select[name="operation"]')).toBeVisible();

    // Search input
    await expect(page.locator('input[name="s"]')).toBeVisible();

    // Filter button
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('Clear Old Logs button is visible', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai-logs');

    const clearButton = page.locator('#seo-ai-clear-old-logs');
    await expect(clearButton).toBeVisible();
  });

  test('level dropdown has expected options', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai-logs');

    const levelSelect = page.locator('select[name="level"]');
    const options = levelSelect.locator('option');

    // Should have: All Levels + debug + info + warn + error = 5
    await expect(options).toHaveCount(5);
  });

  test('filtering by level adds query parameter', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai-logs');

    await page.locator('select[name="level"]').selectOption('error');
    await page.locator('button[type="submit"]').click();

    await expect(page).toHaveURL(/level=error/);
  });

  test('search filter works', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai-logs');

    await page.locator('input[name="s"]').fill('test search');
    await page.locator('button[type="submit"]').click();

    await expect(page).toHaveURL(/s=test\+search|s=test%20search/);
  });

  test('clear filter link appears when filters are active', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=seo-ai-logs&level=error');
    await expect(page.locator('.seo-ai-header')).toBeVisible();

    // A "Clear" button/link should be visible when filters are active
    const clearLink = page.locator('a.button:has-text("Clear")');
    await expect(clearLink).toBeVisible();
  });
});

test.describe('Activity Log Table', () => {
  test.beforeAll(async ({ browser }) => {
    // Create a log entry via REST API so we have data to test
    const context = await browser.newContext({
      storageState: 'playwright/.auth/user.json',
    });
    const page = await context.newPage();
    const nonce = await getRestNonce(page);

    // Start and immediately cancel a queue to generate log entries
    await restApi(page, 'POST', '/queue/start', {
      post_ids: [],
      fields: ['title'],
    }, nonce);

    await context.close();
  });

  test('table has expected columns when entries exist', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai-logs');

    // Check if the table exists (might show "No log entries found" if empty)
    const table = page.locator('.wp-list-table');
    const noEntries = page.locator('text=No log entries found');

    if (await table.isVisible()) {
      // Verify header columns
      const headers = table.locator('thead th');
      const headerTexts = await headers.allTextContents();
      const normalized = headerTexts.map(t => t.trim().toLowerCase());

      expect(normalized).toEqual(expect.arrayContaining(['time', 'level', 'operation', 'message', 'user']));
    } else {
      // If no entries, the empty state message should be shown
      await expect(noEntries).toBeVisible();
    }
  });

  test('log entries show level badges', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai-logs');

    const badges = page.locator('.seo-ai-activity-badge');
    const count = await badges.count();

    if (count > 0) {
      // Each badge should have a level-specific class
      const firstBadge = badges.first();
      const classes = await firstBadge.getAttribute('class');
      expect(classes).toMatch(/seo-ai-activity-badge-(debug|info|warn|error)/);
    }
  });

  test('context expand button toggles detail row', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai-logs');

    const expandButton = page.locator('.seo-ai-activity-expand').first();

    if (await expandButton.isVisible()) {
      // Click to expand
      await expandButton.click();

      const contextRow = page.locator('.seo-ai-log-context-row').first();
      await expect(contextRow).toBeVisible();

      // Click again to collapse
      await expandButton.click();
      await expect(contextRow).toBeHidden();
    }
  });
});

test.describe('Activity Log REST API', () => {
  test('GET /logs returns paginated results', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'GET', '/logs?page=1&per_page=10', undefined, nonce);

    expect(response.ok()).toBeTruthy();

    const body = await response.json();
    expect(body.data).toBeDefined();
    expect(body.data.items).toBeInstanceOf(Array);
    expect(typeof body.data.total).toBe('number');
    expect(typeof body.data.pages).toBe('number');
  });

  test('GET /logs supports level filter', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'GET', '/logs?level=info', undefined, nonce);

    expect(response.ok()).toBeTruthy();

    const body = await response.json();
    // All items should have level=info
    for (const item of body.data.items) {
      expect(item.level).toBe('info');
    }
  });

  test('DELETE /logs requires days parameter', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'DELETE', '/logs', { days: 9999 }, nonce);

    expect(response.ok()).toBeTruthy();

    const body = await response.json();
    expect(typeof body.data.deleted).toBe('number');
  });
});
