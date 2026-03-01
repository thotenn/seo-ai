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
  const post = await createTestPost(page, 'Inline AI Test Post');
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

test.describe('Inline AI Endpoint', () => {
  test('POST /ai/inline returns error when no AI provider configured', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'POST', '/ai/inline', {
      action: 'improve',
      text: 'Test content to improve',
    }, nonce);

    // Without an AI provider → 503; with a provider → 200
    if (response.status() === 503) {
      const body = await response.json();
      expect(body.message || body.code).toBeTruthy();
    } else {
      expect(response.ok()).toBeTruthy();
    }
  });

  test('POST /ai/inline validates required action parameter', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'POST', '/ai/inline', {
      text: 'Some text',
    }, nonce);

    expect(response.status()).toBe(400);
  });

  test('POST /ai/inline validates required text parameter', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'POST', '/ai/inline', {
      action: 'improve',
    }, nonce);

    expect(response.status()).toBe(400);
  });

  test('POST /ai/inline handles invalid action enum values gracefully', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'POST', '/ai/inline', {
      action: 'invalid_action_xyz',
      text: 'Some text',
    }, nonce);

    // WP REST API does not enforce enum validation when sanitize_callback is set.
    // The handler falls back to the 'improve' prompt for unknown actions.
    // Accept either 400 (strict enum) or 200 (graceful fallback) — never 500.
    expect(response.status()).toBeLessThan(500);
  });
});

test.describe('Content Brief Endpoint', () => {
  test('POST /ai/content-brief returns error without provider', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'POST', '/ai/content-brief', {
      keyword: 'test keyword',
    }, nonce);

    // Without an AI provider → 503; with a provider → 200
    if (response.status() === 503) {
      const body = await response.json();
      expect(body.message || body.code).toBeTruthy();
    } else {
      expect(response.ok()).toBeTruthy();
    }
  });

  test('POST /ai/content-brief validates required keyword', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'POST', '/ai/content-brief', {}, nonce);

    expect(response.status()).toBe(400);
  });

  test('POST /ai/content-brief accepts optional post_id', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'POST', '/ai/content-brief', {
      keyword: 'test keyword',
      post_id: testPostId,
    }, nonce);

    // Should not be 400 (validation error) — post_id is optional
    // Expect 503 (no provider) or 200 (provider configured)
    expect(response.status()).not.toBe(400);
  });
});

test.describe('Link Suggestions Endpoint', () => {
  test('POST /ai/link-suggestions returns 200 with data or 503', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'POST', '/ai/link-suggestions', {
      post_id: testPostId,
      content: 'Test content for link suggestions analysis.',
    }, nonce);

    // Returns 200 with empty array if class not found, or 503 if provider needed
    expect([200, 503]).toContain(response.status());
  });

  test('POST /ai/link-suggestions validates required post_id', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'POST', '/ai/link-suggestions', {
      content: 'Some content',
    }, nonce);

    expect(response.status()).toBe(400);
  });

  test('POST /ai/link-suggestions validates required content', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'POST', '/ai/link-suggestions', {
      post_id: testPostId,
    }, nonce);

    expect(response.status()).toBe(400);
  });
});
