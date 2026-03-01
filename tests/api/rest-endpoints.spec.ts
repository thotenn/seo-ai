import { test, expect } from '@playwright/test';
import {
  getRestNonce,
  restApi,
  createTestPost,
} from '../helpers/plugin-helpers';

let testPostId: number;
let restNonce: string;

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext({
    storageState: 'playwright/.auth/user.json',
  });
  const page = await context.newPage();

  restNonce = await getRestNonce(page);
  const post = await createTestPost(page, 'REST API Test Post');
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

test.describe('Queue REST Endpoints', () => {
  test('GET /queue/posts returns paginated post list', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'GET', '/queue/posts?page=1&per_page=5', undefined, nonce);

    expect(response.ok()).toBeTruthy();

    const body = await response.json();
    expect(body.data).toBeDefined();
    expect(body.data.posts).toBeInstanceOf(Array);
    expect(typeof body.data.total).toBe('number');
    expect(typeof body.data.pages).toBe('number');
  });

  test('GET /queue/posts items have expected structure', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'GET', '/queue/posts?per_page=1', undefined, nonce);

    expect(response.ok()).toBeTruthy();

    const body = await response.json();
    if (body.data.posts.length > 0) {
      const item = body.data.posts[0];
      expect(item).toHaveProperty('id');
      expect(item).toHaveProperty('title');
      expect(item).toHaveProperty('post_type');
      expect(item).toHaveProperty('seo_score');
    }
  });

  test('GET /queue/posts supports search parameter', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(
      page, 'GET',
      '/queue/posts?search=REST+API+Test',
      undefined, nonce
    );

    expect(response.ok()).toBeTruthy();

    const body = await response.json();
    expect(body.data.posts).toBeInstanceOf(Array);

    // Our test post should appear in results
    if (body.data.posts.length > 0) {
      const titles = body.data.posts.map((i: any) => i.title);
      expect(titles).toEqual(expect.arrayContaining([expect.stringContaining('REST API Test')]));
    }
  });

  test('GET /queue/posts supports post_type filter', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(
      page, 'GET',
      '/queue/posts?post_type=post',
      undefined, nonce
    );

    expect(response.ok()).toBeTruthy();

    const body = await response.json();
    for (const item of body.data.posts) {
      expect(item.post_type).toBe('post');
    }
  });

  test('POST /queue/start validates empty post_ids', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'POST', '/queue/start', {
      post_ids: [],
      fields: ['title'],
    }, nonce);

    // Should fail — WP_Error returns 400 with code and message
    expect(response.ok()).toBeFalsy();

    const body = await response.json();
    expect(body.code || body.message).toBeTruthy();
  });

  test('POST /queue/start accepts valid post_ids', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'POST', '/queue/start', {
      post_ids: [testPostId],
      fields: ['title', 'description'],
    }, nonce);

    const body = await response.json();

    if (response.status() === 503) {
      // No AI provider configured — acceptable in test environment
      expect(body.message).toBeTruthy();
    } else {
      expect(response.ok()).toBeTruthy();
      expect(body.success).toBe(true);

      // Cancel to clean up
      await restApi(page, 'POST', '/queue/cancel', {}, nonce);
    }
  });

  test('POST /queue/cancel clears the queue', async ({ page }) => {
    const nonce = await getRestNonce(page);

    // Start a queue first (may fail if no provider, that's ok)
    await restApi(page, 'POST', '/queue/start', {
      post_ids: [testPostId],
      fields: ['title'],
    }, nonce);

    // Cancel it
    const response = await restApi(page, 'POST', '/queue/cancel', {}, nonce);
    expect(response.ok()).toBeTruthy();

    const body = await response.json();
    expect(body.success).toBe(true);
  });

  test('POST /queue/process-next without active queue returns done', async ({ page }) => {
    const nonce = await getRestNonce(page);

    // Make sure no queue is active
    await restApi(page, 'POST', '/queue/cancel', {}, nonce);

    const response = await restApi(page, 'POST', '/queue/process-next', {}, nonce);

    const body = await response.json();
    // Should indicate no active queue or be done
    if (body.success) {
      expect(body.data.done).toBe(true);
    } else {
      expect(body.message).toBeTruthy();
    }
  });
});

test.describe('Log REST Endpoints', () => {
  test('GET /logs returns paginated log list', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'GET', '/logs', undefined, nonce);

    expect(response.ok()).toBeTruthy();

    const body = await response.json();
    expect(body.data).toBeDefined();
    expect(body.data.items).toBeInstanceOf(Array);
    expect(typeof body.data.total).toBe('number');
    expect(typeof body.data.pages).toBe('number');
  });

  test('GET /logs supports pagination', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'GET', '/logs?page=1&per_page=5', undefined, nonce);

    expect(response.ok()).toBeTruthy();

    const body = await response.json();
    expect(body.data.items.length).toBeLessThanOrEqual(5);
  });

  test('GET /logs items have expected structure', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'GET', '/logs?per_page=1', undefined, nonce);

    expect(response.ok()).toBeTruthy();

    const body = await response.json();
    if (body.data.items.length > 0) {
      const item = body.data.items[0];
      expect(item).toHaveProperty('level');
      expect(item).toHaveProperty('operation');
      expect(item).toHaveProperty('message');
      expect(item).toHaveProperty('created_at');
      expect(['debug', 'info', 'warn', 'error']).toContain(item.level);
    }
  });

  test('GET /logs supports operation filter', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(
      page, 'GET',
      '/logs?operation=settings_change',
      undefined, nonce
    );

    expect(response.ok()).toBeTruthy();

    const body = await response.json();
    for (const item of body.data.items) {
      expect(item.operation).toBe('settings_change');
    }
  });

  test('GET /logs supports search parameter', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(
      page, 'GET',
      '/logs?search=activated',
      undefined, nonce
    );

    expect(response.ok()).toBeTruthy();

    const body = await response.json();
    // If there are results, messages should contain the search term
    for (const item of body.data.items) {
      expect(item.message.toLowerCase()).toContain('activated');
    }
  });

  test('DELETE /logs deletes old entries', async ({ page }) => {
    const nonce = await getRestNonce(page);
    const response = await restApi(page, 'DELETE', '/logs', { days: 9999 }, nonce);

    expect(response.ok()).toBeTruthy();

    const body = await response.json();
    expect(body.success).toBe(true);
    expect(typeof body.data.deleted).toBe('number');
  });
});

test.describe('Endpoint Authorization', () => {
  test('queue endpoints reject unauthenticated requests', async ({ page }) => {
    // Make request without nonce
    const response = await page.request.get('/wp-json/seo-ai/v1/queue/posts');

    // Should return 401 or 403
    expect(response.status()).toBeGreaterThanOrEqual(400);
    expect(response.status()).toBeLessThan(500);
  });

  test('log endpoints reject unauthenticated requests', async ({ page }) => {
    const response = await page.request.get('/wp-json/seo-ai/v1/logs');

    expect(response.status()).toBeGreaterThanOrEqual(400);
    expect(response.status()).toBeLessThan(500);
  });
});
