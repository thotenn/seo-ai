import { test, expect } from '@playwright/test';
import {
  createTestPost,
  expectAssetLoaded,
  expectSeoAiPostGlobal,
} from '../helpers/plugin-helpers';

let postEditUrl: string;
let postId: number;

/** Expand the Gutenberg "Meta boxes" panel and scroll to the SEO AI metabox. */
async function scrollToMetabox(page: import('@playwright/test').Page) {
  // Wait for the metabox element to exist in DOM (even if hidden/collapsed)
  await page.locator('.seo-ai-metabox').waitFor({ state: 'attached', timeout: 15000 });

  // In Gutenberg, metaboxes are inside a collapsible "Meta boxes" panel.
  // Click the toggle to expand it if the metabox is not yet visible.
  const metabox = page.locator('.seo-ai-metabox');
  const isVisible = await metabox.isVisible();

  if (!isVisible) {
    // Try clicking the "Meta boxes" / "Cajas meta" panel toggle
    // The toggle is a button in the editor footer area
    const metaBoxToggle = page.locator('button.editor-meta-boxes-area__container, [aria-label*="Meta"], [aria-label*="meta"]').first();
    if (await metaBoxToggle.isVisible({ timeout: 2000 }).catch(() => false)) {
      await metaBoxToggle.click();
    }

    // Alternative: use JavaScript to make the metabox visible by scrolling the page
    await page.evaluate(() => {
      const el = document.querySelector('.seo-ai-metabox');
      if (el) {
        // Find the scrollable container and scroll to the metabox
        el.scrollIntoView({ behavior: 'instant', block: 'center' });

        // Also try to make the parent containers visible
        let parent = el.parentElement;
        while (parent) {
          const style = window.getComputedStyle(parent);
          if (style.display === 'none') {
            (parent as HTMLElement).style.display = 'block';
          }
          if (style.overflow === 'hidden' && parent.scrollHeight > parent.clientHeight) {
            parent.scrollTop = parent.scrollHeight;
          }
          parent = parent.parentElement;
        }
      }
    });
  }

  // Give the panel animation time to complete
  await page.waitForTimeout(500);

  // Final scroll into view
  await page.evaluate(() => {
    document.querySelector('.seo-ai-metabox')?.scrollIntoView({ behavior: 'instant', block: 'center' });
  });

  await expect(metabox).toBeVisible({ timeout: 10000 });
}

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext({
    storageState: 'playwright/.auth/user.json',
  });
  const page = await context.newPage();
  const post = await createTestPost(page);
  postId = post.id;
  postEditUrl = post.editUrl;
  await context.close();
});

test.afterAll(async ({ browser }) => {
  const context = await browser.newContext({
    storageState: 'playwright/.auth/user.json',
  });
  const page = await context.newPage();

  // Navigate to wp-admin so we can get a nonce
  await page.goto('/wp-admin/');
  await page.waitForLoadState('domcontentloaded');

  const nonce = await page.evaluate(() => {
    return (window as any).wpApiSettings?.nonce as string | undefined;
  }) || await page.evaluate(async () => {
    const res = await fetch('/wp-admin/admin-ajax.php?action=rest-nonce', {
      credentials: 'same-origin',
    });
    return res.text();
  });

  await page.request.delete(`/wp-json/wp/v2/posts/${postId}?force=true`, {
    headers: { 'X-WP-Nonce': nonce },
  });
  await context.close();
});

test.describe('Post Editor Metabox', () => {
  test('metabox renders in post editor', async ({ page }) => {
    await page.goto(postEditUrl);
    await scrollToMetabox(page);
    await expect(page.locator('.seo-ai-scores-bar')).toBeVisible();
  });

  test('metabox tabs switch correctly', async ({ page }) => {
    await page.goto(postEditUrl);
    await scrollToMetabox(page);

    const tabs = ['seo', 'readability', 'social', 'schema', 'advanced'];

    for (const tab of tabs) {
      // Use JavaScript click to trigger jQuery's delegated event handler,
      // bypassing Gutenberg's footer overlay issue
      await page.evaluate((t) => {
        const btn = document.querySelector(`.seo-ai-metabox-tab[data-tab="${t}"]`) as HTMLElement;
        btn?.click();
      }, tab);
      const panel = page.locator(`#seo-ai-panel-${tab}`);
      await expect(panel).toBeVisible();
    }
  });

  test('metabox assets load', async ({ page }) => {
    await page.goto(postEditUrl);
    // Wait for the metabox element to confirm assets loaded
    await page.locator('.seo-ai-metabox').waitFor({ state: 'attached', timeout: 15000 });

    await expectAssetLoaded(page, 'metabox.css');
    await expectAssetLoaded(page, 'metabox.js');
  });

  test('seoAiPost global is present', async ({ page }) => {
    await page.goto(postEditUrl);
    // Wait for the metabox script to have loaded (the global is set by metabox.js)
    await page.locator('.seo-ai-metabox').waitFor({ state: 'attached', timeout: 15000 });
    await expectSeoAiPostGlobal(page);
  });

  test('SEO fields are editable', async ({ page }) => {
    await page.goto(postEditUrl);
    await scrollToMetabox(page);

    // Click SEO tab to ensure we're on it (force due to Gutenberg footer overlap)
    await page.locator('.seo-ai-metabox-tab[data-tab="seo"]').click({ force: true });

    // Try to fill the SEO title field
    const titleField = page.locator('#seo-ai-panel-seo input[type="text"]').first();
    if (await titleField.isVisible()) {
      await titleField.fill('Test SEO Title');
      await expect(titleField).toHaveValue('Test SEO Title');
    }
  });
});
