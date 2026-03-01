import { test, expect } from '@playwright/test';
import {
  getRestNonce,
  restApi,
  createTestPost,
} from '../helpers/plugin-helpers';

let testPostId: number;

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext({
    storageState: 'playwright/.auth/user.json',
  });
  const page = await context.newPage();
  const post = await createTestPost(page, 'Competitor Analysis Test Post');
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

test.describe('Competitor Analyze Endpoint', () => {
  test('POST /competitor/analyze validates required url', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'POST', '/competitor/analyze', {}, nonce);

    expect(response.status()).toBe(400);
  });

  test('POST /competitor/analyze returns error for empty URL', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'POST', '/competitor/analyze', {
      url: '',
    }, nonce);

    expect(response.ok()).toBeFalsy();
  });

  test('POST /competitor/analyze returns error for unreachable URL', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'POST', '/competitor/analyze', {
      url: 'https://this-domain-does-not-exist-xyz-12345.example',
    }, nonce);

    // Should fail — unreachable URL
    expect(response.ok()).toBeFalsy();
  });

  test('POST /competitor/analyze accepts optional focus_keyword', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'POST', '/competitor/analyze', {
      url: 'https://this-domain-does-not-exist-xyz-12345.example',
      focus_keyword: 'test keyword',
    }, nonce);

    // Should not be a 400 validation error for the extra parameter
    // It will still fail (unreachable URL) but not due to invalid params
    expect(response.status()).not.toBe(400);
  });
});

test.describe('Competitor Compare Endpoint', () => {
  test('POST /competitor/compare validates required post_id', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'POST', '/competitor/compare', {
      url: 'https://example.com',
    }, nonce);

    expect(response.status()).toBe(400);
  });

  test('POST /competitor/compare validates required url', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'POST', '/competitor/compare', {
      post_id: testPostId,
    }, nonce);

    expect(response.status()).toBe(400);
  });

  test('POST /competitor/compare returns error for non-existent post_id', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'POST', '/competitor/compare', {
      post_id: 999999,
      url: 'https://example.com',
    }, nonce);

    expect(response.ok()).toBeFalsy();
  });
});

test.describe('Competitor Endpoint Authorization', () => {
  test('rejects unauthenticated requests (no nonce)', async ({ page }) => {
    // Make request without nonce header
    const response = await page.request.post('/wp-json/seo-ai/v1/competitor/analyze', {
      data: { url: 'https://example.com' },
    });

    expect(response.status()).toBeGreaterThanOrEqual(400);
    expect(response.status()).toBeLessThan(500);
  });
});
