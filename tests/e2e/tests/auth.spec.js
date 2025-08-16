/**
 * @file
 * Authentication setup helpers for Proxy Block E2E testing.
 * This file provides minimal authentication setup needed for proxy block tests.
 */

const {
  test,
  expect,
  execDrushInTestSite,
} = require('@lullabot/playwright-drupal');
const { TIMEOUTS, ENVIRONMENT } = require('../utils/constants');
const { waitForAjax } = require('../utils/ajax-helper');

test.describe('Proxy Block Test Setup', () => {
  test.beforeEach(async ({ page }) => {
    // Set longer timeout for admin operations
    test.setTimeout(TIMEOUTS.LONG);
  });

  test('should setup environment and verify proxy block availability', async ({
    page,
  }) => {
    // Enable proxy_block module using Drush
    await execDrushInTestSite('pm:enable proxy_block -y');

    // Create admin user for testing
    await execDrushInTestSite(
      'user:create admin --mail="admin@example.com" --password="admin"',
    );
    await execDrushInTestSite('user:role:add administrator admin');

    // Login
    await page.goto('/user/login');
    await page.fill('#edit-name', 'admin');
    await page.fill('#edit-pass', 'admin');
    await page.click('#edit-submit');

    // Navigate directly to block placement to verify proxy block is available
    await page.goto(`/admin/structure/block/list/${ENVIRONMENT.theme}`);
    await expect(page.locator('h1')).toContainText('Block layout');

    // Click place block for content region
    const contentRegion = page.locator('tr[data-region="content"]');
    await contentRegion.locator('.button').first().click();

    // Search for proxy block
    const searchInput = page.locator('#edit-search');
    if (await searchInput.isVisible()) {
      await searchInput.fill('Proxy Block');
      await waitForAjax(page);
    }

    // Verify Proxy Block plugin is available - this is the key test
    const proxyBlockLink = page.locator('a').filter({ hasText: 'Proxy Block' });
    await expect(proxyBlockLink).toBeVisible();
  });
});
