import { test, expect } from '@playwright/test';
import { navigateToSeoAiPage } from '../helpers/plugin-helpers';

test.describe('Dashboard Hero Card', () => {
  test('hero card is visible with optimization button', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai');

    const heroCard = page.locator('.seo-ai-hero-card');
    await expect(heroCard).toBeVisible();

    const startButton = heroCard.locator('#seo-ai-start-optimize');
    await expect(startButton).toBeVisible();
    await expect(startButton).toHaveText(/Start Optimization/i);
  });

  test('hero card shows stat values', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai');

    const heroCard = page.locator('.seo-ai-hero-card');
    const statValues = heroCard.locator('.seo-ai-hero-stat-value');
    const count = await statValues.count();
    expect(count).toBeGreaterThan(0);

    const firstStatText = await statValues.first().textContent();
    expect(firstStatText).toBeTruthy();
    expect(Number(firstStatText?.trim())).toBeGreaterThanOrEqual(0);
  });
});

test.describe('Optimization Wizard Modal', () => {
  test('modal opens when Start Optimization is clicked', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai');

    const modal = page.locator('#seo-ai-optimize-modal');
    await expect(modal).toBeHidden();

    await page.click('#seo-ai-start-optimize');
    await expect(modal).toBeVisible();
  });

  test('modal has step indicator with 4 steps', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai');
    await page.click('#seo-ai-start-optimize');

    const steps = page.locator('.seo-ai-steps .seo-ai-step');
    await expect(steps).toHaveCount(4);

    // First step should be active
    await expect(steps.first()).toHaveClass(/active/);
  });

  test('modal closes with close button', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai');
    await page.click('#seo-ai-start-optimize');

    const modal = page.locator('#seo-ai-optimize-modal');
    await expect(modal).toBeVisible();

    await page.click('.seo-ai-modal-close');
    await expect(modal).toBeHidden();
  });

  test('modal closes when clicking overlay', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai');
    await page.click('#seo-ai-start-optimize');

    const modal = page.locator('#seo-ai-optimize-modal');
    await expect(modal).toBeVisible();

    // Click the overlay (outside the modal content)
    await page.locator('.seo-ai-modal-overlay').click({ position: { x: 10, y: 10 } });
    await expect(modal).toBeHidden();
  });
});

test.describe('Wizard Step 1 — Select Posts', () => {
  test('step 1 shows post list container', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai');
    await page.click('#seo-ai-start-optimize');

    const postList = page.locator('#seo-ai-post-list');
    await expect(postList).toBeVisible();
  });

  test('step 1 has search input', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai');
    await page.click('#seo-ai-start-optimize');

    const search = page.locator('#seo-ai-post-search');
    await expect(search).toBeVisible();
  });

  test('step 1 has post type filter dropdown', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai');
    await page.click('#seo-ai-start-optimize');

    const filter = page.locator('#seo-ai-post-type-filter');
    await expect(filter).toBeVisible();
  });

  test('step 1 has status filter dropdown', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai');
    await page.click('#seo-ai-start-optimize');

    const filter = page.locator('#seo-ai-status-filter');
    await expect(filter).toBeVisible();
  });

  test('step 1 loads posts from REST API', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai');
    await page.click('#seo-ai-start-optimize');

    // Wait for posts to load (checkboxes should appear)
    await page.waitForSelector('#seo-ai-post-list input[type="checkbox"]', {
      timeout: 10000,
    });

    const checkboxes = page.locator('#seo-ai-post-list input[type="checkbox"]');
    const count = await checkboxes.count();
    expect(count).toBeGreaterThan(0);
  });

  test('selecting a post enables navigation to step 2', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai');
    await page.click('#seo-ai-start-optimize');

    // Wait for posts to load
    await page.waitForSelector('#seo-ai-post-list input[type="checkbox"]', {
      timeout: 10000,
    });

    const nextButton = page.locator('.seo-ai-btn-next');
    await expect(nextButton).toBeVisible();

    // Select a post
    await page.locator('#seo-ai-post-list input[type="checkbox"]').first().check();

    // Click Next should navigate to step 2
    await nextButton.click();
    await expect(page.locator('.seo-ai-wizard-step[data-step="2"]')).toBeVisible();
  });
});

test.describe('Wizard Step 2 — Configure Fields', () => {
  test('step 2 shows field checkboxes', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai');
    await page.click('#seo-ai-start-optimize');

    // Select a post and go to step 2
    await page.waitForSelector('#seo-ai-post-list input[type="checkbox"]', {
      timeout: 10000,
    });
    await page.locator('#seo-ai-post-list input[type="checkbox"]').first().check();
    await page.click('.seo-ai-btn-next');

    // Verify field checkboxes
    const step2 = page.locator('.seo-ai-wizard-step[data-step="2"]');
    await expect(step2).toBeVisible();

    const fieldCheckboxes = step2.locator('input[type="checkbox"]');
    const count = await fieldCheckboxes.count();
    expect(count).toBeGreaterThanOrEqual(2); // At least title + description
  });

  test('title and description are checked by default', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai');
    await page.click('#seo-ai-start-optimize');

    await page.waitForSelector('#seo-ai-post-list input[type="checkbox"]', {
      timeout: 10000,
    });
    await page.locator('#seo-ai-post-list input[type="checkbox"]').first().check();
    await page.click('.seo-ai-btn-next');

    await expect(page.locator('input[value="title"]')).toBeChecked();
    await expect(page.locator('input[value="description"]')).toBeChecked();
  });
});

test.describe('Wizard Step 3 — Review', () => {
  test('step 3 shows review summary', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai');
    await page.click('#seo-ai-start-optimize');

    // Step 1: select a post
    await page.waitForSelector('#seo-ai-post-list input[type="checkbox"]', {
      timeout: 10000,
    });
    await page.locator('#seo-ai-post-list input[type="checkbox"]').first().check();
    await page.click('.seo-ai-btn-next');

    // Step 2: use defaults and continue
    await page.click('.seo-ai-btn-next');

    // Step 3: verify review content
    const step3 = page.locator('.seo-ai-wizard-step[data-step="3"]');
    await expect(step3).toBeVisible();

    // Should show post count and field count
    const reviewText = await step3.textContent();
    expect(reviewText).toContain('1'); // At least the post count
  });

  test('Back button returns to previous step', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai');
    await page.click('#seo-ai-start-optimize');

    // Go to step 2
    await page.waitForSelector('#seo-ai-post-list input[type="checkbox"]', {
      timeout: 10000,
    });
    await page.locator('#seo-ai-post-list input[type="checkbox"]').first().check();
    await page.click('.seo-ai-btn-next');

    // Verify on step 2
    await expect(page.locator('.seo-ai-wizard-step[data-step="2"]')).toBeVisible();

    // Go back to step 1
    await page.click('.seo-ai-btn-back');
    await expect(page.locator('.seo-ai-wizard-step[data-step="1"]')).toBeVisible();
  });
});

test.describe('Wizard Step 4 — Progress', () => {
  test('step 4 has progress bar and terminal log', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai');
    await page.click('#seo-ai-start-optimize');

    // Navigate to step 4
    await page.waitForSelector('#seo-ai-post-list input[type="checkbox"]', {
      timeout: 10000,
    });
    await page.locator('#seo-ai-post-list input[type="checkbox"]').first().check();
    await page.click('.seo-ai-btn-next'); // to step 2
    await page.click('.seo-ai-btn-next'); // to step 3
    await page.click('.seo-ai-btn-start'); // start optimization → step 4

    const step4 = page.locator('.seo-ai-wizard-step[data-step="4"]');
    await expect(step4).toBeVisible();

    // Progress bar should exist
    await expect(page.locator('.seo-ai-progress-bar')).toBeVisible();

    // Terminal log should exist
    await expect(page.locator('.seo-ai-terminal')).toBeVisible();
  });
});

test.describe('Dashboard Sections', () => {
  test('dashboard shows SEO Score Overview stats', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai');

    const statsGrid = page.locator('.seo-ai-dashboard-stats');
    await expect(statsGrid).toBeVisible();
  });

  test('dashboard shows Recent Activity section', async ({ page }) => {
    await navigateToSeoAiPage(page, 'seo-ai');

    const activitySection = page.locator('.seo-ai-activity-list');
    // Section exists even if empty
    await expect(activitySection).toBeAttached();
  });
});
