import { test, expect } from '@playwright/test';
import {
  createTestPost,
  getRestNonce,
} from '../helpers/plugin-helpers';

let testPostId: number;

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext({
    storageState: 'playwright/.auth/user.json',
  });
  const page = await context.newPage();
  const post = await createTestPost(page, 'Post List Filter Test');
  testPostId = post.id;
  await context.close();
});

test.afterAll(async ({ browser }) => {
  const context = await browser.newContext({
    storageState: 'playwright/.auth/user.json',
  });
  const page = await context.newPage();
  const nonce = await getRestNonce(page);
  await page.request.delete(`/wp-json/wp/v2/posts/${testPostId}?force=true`, {
    headers: { 'X-WP-Nonce': nonce },
  });
  await context.close();
});

test.describe('Post List Filters', () => {
  test('SEO score filter is visible with 5 options', async ({ page }) => {
    await page.goto('/wp-admin/edit.php');
    await page.waitForLoadState('domcontentloaded');

    const scoreFilter = page.locator('select[name="seo_ai_score"]');
    await expect(scoreFilter).toBeVisible();

    const options = scoreFilter.locator('option');
    await expect(options).toHaveCount(5);
  });

  test('Robots filter is visible with 3 options', async ({ page }) => {
    await page.goto('/wp-admin/edit.php');
    await page.waitForLoadState('domcontentloaded');

    const robotsFilter = page.locator('select[name="seo_ai_robots"]');
    await expect(robotsFilter).toBeVisible();

    const options = robotsFilter.locator('option');
    await expect(options).toHaveCount(3);
  });

  test('Schema filter is visible with >= 7 options', async ({ page }) => {
    await page.goto('/wp-admin/edit.php');
    await page.waitForLoadState('domcontentloaded');

    const schemaFilter = page.locator('select[name="seo_ai_schema"]');
    await expect(schemaFilter).toBeVisible();

    const options = schemaFilter.locator('option');
    const count = await options.count();
    expect(count).toBeGreaterThanOrEqual(7);
  });

  test('score filter adds seo_ai_score query param', async ({ page }) => {
    await page.goto('/wp-admin/edit.php');
    await page.waitForLoadState('domcontentloaded');

    await page.selectOption('select[name="seo_ai_score"]', 'good');
    await page.click('#post-query-submit');
    await page.waitForLoadState('domcontentloaded');

    expect(page.url()).toContain('seo_ai_score=good');
  });

  test('robots filter adds seo_ai_robots query param', async ({ page }) => {
    await page.goto('/wp-admin/edit.php');
    await page.waitForLoadState('domcontentloaded');

    await page.selectOption('select[name="seo_ai_robots"]', 'noindex');
    await page.click('#post-query-submit');
    await page.waitForLoadState('domcontentloaded');

    expect(page.url()).toContain('seo_ai_robots=noindex');
  });
});

test.describe('Post List Bulk Actions', () => {
  const expectedActions = [
    'seo_ai_optimize',
    'seo_ai_noindex',
    'seo_ai_remove_noindex',
    'seo_ai_nofollow',
    'seo_ai_remove_nofollow',
    'seo_ai_remove_canonical',
    'seo_ai_set_schema_article',
    'seo_ai_clear_seo_data',
    'seo_ai_reanalyze',
  ];

  test('top bulk actions dropdown contains 9 SEO AI actions', async ({ page }) => {
    await page.goto('/wp-admin/edit.php');
    await page.waitForLoadState('domcontentloaded');

    const topSelect = page.locator('select[name="action"]');
    for (const action of expectedActions) {
      const option = topSelect.locator(`option[value="${action}"]`);
      await expect(option).toBeAttached();
    }
  });

  test('bottom bulk actions dropdown also has SEO AI actions', async ({ page }) => {
    await page.goto('/wp-admin/edit.php');
    await page.waitForLoadState('domcontentloaded');

    const bottomSelect = page.locator('select[name="action2"]');
    for (const action of expectedActions) {
      const option = bottomSelect.locator(`option[value="${action}"]`);
      await expect(option).toBeAttached();
    }
  });
});

test.describe('Post List Quick Edit', () => {
  /**
   * Open the quick edit row for the first post in the list.
   * Returns a scoped locator for the specific edit row (#edit-{postId})
   * to avoid strict-mode violations from the hidden template row.
   */
  async function openQuickEdit(page: import('@playwright/test').Page) {
    await page.goto('/wp-admin/edit.php');
    await page.waitForLoadState('domcontentloaded');

    // Wait for post list to fully load
    await page.waitForSelector('#the-list tr', { timeout: 10000 });

    // Open quick edit using JS — return the post ID for scoped selectors
    const postId = await page.evaluate(() => {
      const firstRow = document.querySelector('#the-list tr');
      if (!firstRow) return null;
      const id = firstRow.id.replace('post-', '');
      if (!id) return null;
      try {
        (window as any).inlineEditPost.edit(id);
        return id;
      } catch {
        return null;
      }
    });

    expect(postId).toBeTruthy();

    // Wait for the specific edit row to appear
    await page.waitForSelector(`#edit-${postId}`, { state: 'visible', timeout: 5000 });

    return page.locator(`#edit-${postId}`);
  }

  test('quick edit panel has SEO AI fieldset with heading', async ({ page }) => {
    const editRow = await openQuickEdit(page);

    const heading = editRow.locator('h4').filter({ hasText: /SEO AI/i });
    await expect(heading).toBeVisible();
  });

  test('quick edit has title input', async ({ page }) => {
    const editRow = await openQuickEdit(page);
    await expect(editRow.locator('.seo-ai-qe-title')).toBeAttached();
  });

  test('quick edit has keyword input', async ({ page }) => {
    const editRow = await openQuickEdit(page);
    await expect(editRow.locator('.seo-ai-qe-keyword')).toBeAttached();
  });

  test('quick edit has schema select', async ({ page }) => {
    const editRow = await openQuickEdit(page);
    await expect(editRow.locator('.seo-ai-qe-schema')).toBeAttached();
  });

  test('quick edit has noindex checkbox', async ({ page }) => {
    const editRow = await openQuickEdit(page);
    await expect(editRow.locator('.seo-ai-qe-noindex')).toBeAttached();
  });
});
