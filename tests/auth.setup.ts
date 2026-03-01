import { test as setup, expect } from '@playwright/test';
import path from 'path';
import fs from 'fs';

const authFile = path.join(__dirname, '..', 'playwright', '.auth', 'user.json');

setup('authenticate as admin', async ({ page }) => {
  const authDir = path.dirname(authFile);
  if (!fs.existsSync(authDir)) {
    fs.mkdirSync(authDir, { recursive: true });
  }

  await page.goto('/wp-login.php');

  await page.fill('#user_login', process.env.WP_ADMIN_USER || 'admin');
  await page.fill('#user_pass', process.env.WP_ADMIN_PASS || 'admin');
  await page.click('#wp-submit');

  await expect(page).toHaveURL(/wp-admin/, { timeout: 10000 });

  await page.context().storageState({ path: authFile });
});
