import { type Page, expect } from '@playwright/test';

/** Navigate to one of the SEO AI admin pages. */
export async function navigateToSeoAiPage(
  page: Page,
  slug: 'seo-ai' | 'seo-ai-settings' | 'seo-ai-redirects' | 'seo-ai-404-log' | 'seo-ai-logs' = 'seo-ai'
) {
  await page.goto(`/wp-admin/admin.php?page=${slug}`);
  await expect(page.locator('.seo-ai-header')).toBeVisible();
}

/** Get a REST API nonce from the current admin page context. */
export async function getRestNonce(page: Page): Promise<string> {
  await page.goto('/wp-admin/');
  await page.waitForLoadState('domcontentloaded');

  const nonce = await page.evaluate(() => {
    return (window as any).wpApiSettings?.nonce as string | undefined;
  });

  if (nonce) return nonce;

  return await page.evaluate(async () => {
    const res = await fetch('/wp-admin/admin-ajax.php?action=rest-nonce', {
      credentials: 'same-origin',
    });
    return res.text();
  });
}

/** Make a REST API call and return the parsed JSON. */
export async function restApi(
  page: Page,
  method: 'GET' | 'POST' | 'DELETE',
  endpoint: string,
  data?: Record<string, unknown>,
  nonce?: string
) {
  const restNonce = nonce || await getRestNonce(page);
  const url = `/wp-json/seo-ai/v1${endpoint}`;
  const options: Parameters<typeof page.request.fetch>[1] = {
    method,
    headers: { 'X-WP-Nonce': restNonce },
  };

  if (data && (method === 'POST' || method === 'DELETE')) {
    options.data = data;
  }

  return page.request.fetch(url, options);
}

/**
 * Create a test post via the WP REST API and return the post id and edit URL.
 * Reuses the auth cookies already stored in the browser context.
 */
export async function createTestPost(page: Page, title = 'SEO AI Test Post') {
  // Navigate to wp-admin so we can extract a REST nonce from the page context
  await page.goto('/wp-admin/');
  await page.waitForLoadState('domcontentloaded');

  const nonce = await page.evaluate(() => {
    return (window as any).wpApiSettings?.nonce as string | undefined;
  });

  // If wpApiSettings isn't available, fetch the nonce via admin-ajax
  const restNonce = nonce || await page.evaluate(async () => {
    const res = await fetch('/wp-admin/admin-ajax.php?action=rest-nonce', {
      credentials: 'same-origin',
    });
    return res.text();
  });

  const response = await page.request.post('/wp-json/wp/v2/posts', {
    headers: { 'X-WP-Nonce': restNonce },
    data: {
      title,
      content: 'Test content for SEO AI E2E testing. This paragraph has enough words to pass basic content length checks during analysis.',
      status: 'draft',
    },
  });

  expect(response.ok()).toBeTruthy();
  const post = await response.json();
  return {
    id: post.id as number,
    editUrl: `/wp-admin/post.php?post=${post.id}&action=edit`,
  };
}

/** Assert that a CSS or JS asset loaded (check the DOM for link/script tags). */
export async function expectAssetLoaded(page: Page, filename: string) {
  const selector =
    filename.endsWith('.css')
      ? `link[href*="${filename}"]`
      : `script[src*="${filename}"]`;
  await expect(page.locator(selector)).toHaveCount(1);
}

/** Assert the seoAi global is present and has expected keys. */
export async function expectSeoAiGlobal(page: Page) {
  const keys = await page.evaluate(() => {
    const g = (window as any).seoAi;
    return g ? Object.keys(g) : null;
  });
  expect(keys).not.toBeNull();
  expect(keys).toEqual(expect.arrayContaining(['restUrl', 'nonce', 'adminUrl', 'version']));
}

/** Assert the seoAiPost global is present and has expected keys. */
export async function expectSeoAiPostGlobal(page: Page) {
  const keys = await page.evaluate(() => {
    const g = (window as any).seoAiPost;
    return g ? Object.keys(g) : null;
  });
  expect(keys).not.toBeNull();
  expect(keys).toEqual(expect.arrayContaining(['postId', 'postType', 'postTitle']));
}
