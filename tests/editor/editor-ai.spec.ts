import { test, expect } from '@playwright/test';
import {
  createTestPost,
  getRestNonce,
  expectAssetLoaded,
} from '../helpers/plugin-helpers';

let testPostId: number;
let postEditUrl: string;

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext({
    storageState: 'playwright/.auth/user.json',
  });
  const page = await context.newPage();
  const post = await createTestPost(page, 'Editor AI Test Post');
  testPostId = post.id;
  postEditUrl = post.editUrl;
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

test.describe('Editor AI Sidebar Assets', () => {
  test('editor-ai.js script tag present on post editor', async ({ page }) => {
    await page.goto(postEditUrl);
    await page.waitForLoadState('domcontentloaded');

    // Wait for editor to load
    await page.waitForTimeout(2000);

    await expectAssetLoaded(page, 'editor-ai.js');
  });

  test('editor-ai.js dependencies load', async ({ page }) => {
    await page.goto(postEditUrl);
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(2000);

    // Check that key WordPress editor dependencies are loaded
    const hasDeps = await page.evaluate(() => {
      return !!(
        (window as any).wp?.plugins &&
        (window as any).wp?.element
      );
    });
    expect(hasDeps).toBe(true);
  });

  test('seoAi global available in post editor', async ({ page }) => {
    await page.goto(postEditUrl);
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(2000);

    const keys = await page.evaluate(() => {
      const g = (window as any).seoAi;
      return g ? Object.keys(g) : null;
    });
    expect(keys).not.toBeNull();
    expect(keys).toEqual(expect.arrayContaining(['restUrl', 'nonce', 'adminUrl', 'version']));
  });

  test('seoAiPost global contains post-specific data', async ({ page }) => {
    await page.goto(postEditUrl);
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(2000);

    const keys = await page.evaluate(() => {
      const g = (window as any).seoAiPost;
      return g ? Object.keys(g) : null;
    });
    expect(keys).not.toBeNull();
    expect(keys).toEqual(expect.arrayContaining(['postId', 'postType', 'postTitle']));
  });

  test('seoAiPost.postId matches created test post ID', async ({ page }) => {
    await page.goto(postEditUrl);
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(2000);

    const postIdFromGlobal = await page.evaluate(() => {
      const g = (window as any).seoAiPost;
      return g?.postId;
    });

    // postId might be a string or number depending on localization
    expect(Number(postIdFromGlobal)).toBe(testPostId);
  });
});
