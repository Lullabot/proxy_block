/**
 * @file
 * Basic proxy block functionality tests.
 * Tests core proxy block plugin functionality and configuration access.
 */

const { test, expect } = require('@playwright/test');
const {
  createAdminUser,
  enableModule,
  clearCache,
} = require('../utils/drush-helper');

test.describe('Proxy Block Basic', () => {
  test.beforeAll(async () => {
    // Set up test environment - these operations are optional in CI
    try {
      await enableModule('proxy_block');
      console.log('Module enabled successfully');
    } catch (error) {
      console.warn(
        'Module enable skipped (may already be enabled):',
        error.message,
      );
    }

    try {
      await createAdminUser();
      console.log('Admin user created successfully');
    } catch (error) {
      console.warn(
        'Admin user creation skipped (may already exist):',
        error.message,
      );
    }

    try {
      await clearCache();
      console.log('Cache cleared successfully');
    } catch (error) {
      console.warn('Cache clear skipped:', error.message);
    }
  });

  test('should access proxy block configuration form', async ({ page }) => {
    // Login as admin user - this MUST work
    await page.goto('/user/login');
    await page.waitForLoadState('networkidle');

    const loginForm = page.locator('#user-login-form');
    await expect(loginForm).toBeVisible(); // FAIL if no login form

    await page.fill('#edit-name', 'admin');
    await page.fill('#edit-pass', 'admin');
    await page.click('#edit-submit');
    await page.waitForLoadState('networkidle');

    // Access proxy block configuration - must work with correct theme
    await page.goto('/admin/structure/block/add/proxy_block/olivero');
    await page.waitForLoadState('networkidle');

    // MUST be on the configuration page
    const heading = page.locator('h1');
    await expect(heading).toContainText('Configure block');

    // Proxy block specific form elements MUST exist
    const titleField = page.locator('#edit-settings-label');
    await expect(titleField).toBeVisible();

    // Target block selection field MUST exist (key proxy block feature)
    const targetBlockField = page.locator('#edit-settings-target-block');
    await expect(targetBlockField).toBeVisible();

    // Save button MUST exist
    const saveButton = page.locator('#edit-submit');
    await expect(saveButton).toBeVisible();
  });
});
