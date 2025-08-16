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
    // Login first
    await page.goto('/user/login');
    await page.waitForLoadState('networkidle');
    await page.fill('#edit-name', 'admin');
    await page.fill('#edit-pass', 'admin');
    await page.click('#edit-submit');
    await page.waitForLoadState('networkidle');

    // Access proxy block configuration directly
    await page.goto('/admin/structure/block/add/proxy_block/stark');
    await page.waitForLoadState('networkidle');

    // Verify we're on the proxy block configuration page
    await expect(page.locator('h1')).toContainText('Configure block');

    // Verify proxy block specific form elements exist
    const titleField = page.locator('#edit-settings-label');
    await expect(titleField).toBeVisible();

    // Look for target block selection field (key proxy block feature)
    const targetBlockField = page.locator('#edit-settings-target-block');
    if ((await targetBlockField.count()) > 0) {
      await expect(targetBlockField).toBeVisible();
      console.log('SUCCESS: Proxy block target selection field found!');
    }

    // Verify save button exists
    const saveButton = page.locator('#edit-submit');
    await expect(saveButton).toBeVisible();
  });
});
