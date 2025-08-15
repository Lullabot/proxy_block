/**
 * @file
 * Authentication and basic setup tests for Proxy Block E2E testing.
 */

const {
  test,
  expect,
  execDrushInTestSite,
} = require('@lullabot/playwright-drupal');
const { TIMEOUTS, TEST_DATA, ENVIRONMENT } = require('../utils/constants');

test.describe('Authentication and Setup', () => {
  test.beforeEach(async ({ page }) => {
    // Set longer timeout for admin operations
    test.setTimeout(TIMEOUTS.LONG);
  });

  test('should verify site is accessible and functional', async ({ page }) => {
    // Navigate to homepage - site should be accessible via test isolation
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Verify homepage loads
    await expect(page.locator('html')).toBeVisible();
    await expect(page.locator('body')).toBeVisible();

    // Verify page title is not empty
    const title = await page.title();
    expect(title).toBeTruthy();
    expect(title.length).toBeGreaterThan(0);
  });

  test('should login as admin user successfully', async ({ page }) => {
    // Create admin user using Drush in test site
    await execDrushInTestSite(
      'user:create admin --mail="admin@example.com" --password="admin"',
    );
    await execDrushInTestSite('user:role:add administrator admin');

    // Navigate to login page
    await page.goto('/user/login');

    // Fill in credentials
    await page.fill('#edit-name', 'admin');
    await page.fill('#edit-pass', 'admin');
    await page.click('#edit-submit');

    // Verify admin toolbar is visible
    await expect(page.locator('#toolbar-administration')).toBeVisible();

    // Verify we're logged in successfully
    const userMenu = page.locator('.toolbar-menu-administration');
    await expect(userMenu).toBeVisible();
  });

  test('should verify admin user has required permissions', async ({
    page,
  }) => {
    // Create and login as admin
    await execDrushInTestSite(
      'user:create admin --mail="admin@example.com" --password="admin"',
    );
    await execDrushInTestSite('user:role:add administrator admin');

    // Login
    await page.goto('/user/login');
    await page.fill('#edit-name', 'admin');
    await page.fill('#edit-pass', 'admin');
    await page.click('#edit-submit');

    // Verify access to admin pages
    await page.goto('/admin');
    await expect(page.locator('h1')).toContainText('Administration');

    // Verify access to structure pages
    await page.goto('/admin/structure');
    await expect(page.locator('h1')).toContainText('Structure');

    await page.goto('/admin/structure/block');
    await expect(page.locator('h1')).toContainText('Block layout');
  });

  test('should verify proxy_block module is enabled', async ({ page }) => {
    // Enable proxy_block module using Drush
    await execDrushInTestSite('pm:enable proxy_block -y');

    // Create and login as admin
    await execDrushInTestSite(
      'user:create admin --mail="admin@example.com" --password="admin"',
    );
    await execDrushInTestSite('user:role:add administrator admin');

    await page.goto('/user/login');
    await page.fill('#edit-name', 'admin');
    await page.fill('#edit-pass', 'admin');
    await page.click('#edit-submit');

    // Navigate to modules page to verify
    await page.goto('/admin/modules');
    await expect(page.locator('h1')).toContainText('Modules');

    // Look for proxy_block module checkbox and verify it's checked
    const moduleCheckbox = page.locator(
      'input[name="modules[proxy_block][enable]"]',
    );
    await expect(moduleCheckbox).toBeChecked();
  });

  test('should verify proxy block is available in block placement', async ({
    page,
  }) => {
    // Enable proxy_block module and create admin user
    await execDrushInTestSite('pm:enable proxy_block -y');
    await execDrushInTestSite(
      'user:create admin --mail="admin@example.com" --password="admin"',
    );
    await execDrushInTestSite('user:role:add administrator admin');

    // Login
    await page.goto('/user/login');
    await page.fill('#edit-name', 'admin');
    await page.fill('#edit-pass', 'admin');
    await page.click('#edit-submit');

    // Navigate to block layout
    await page.goto(`/admin/structure/block/list/${ENVIRONMENT.theme}`);
    await expect(page.locator('h1')).toContainText('Block layout');

    // Click place block for content region
    const contentRegion = page.locator('tr[data-region="content"]');
    await contentRegion.locator('.button').first().click();

    // Should be on place block page
    await expect(page.locator('h1')).toContainText('Place block');

    // Search for proxy block
    const searchInput = page.locator('#edit-search');
    if (await searchInput.isVisible()) {
      await searchInput.fill('Proxy Block');
      await page.waitForTimeout(1000); // Wait for AJAX search
    }

    // Verify Proxy Block is available
    const proxyBlockLink = page.locator('a').filter({ hasText: 'Proxy Block' });
    await expect(proxyBlockLink).toBeVisible();
  });

  test('should logout successfully', async ({ page }) => {
    // Create and login as admin
    await execDrushInTestSite(
      'user:create admin --mail="admin@example.com" --password="admin"',
    );
    await execDrushInTestSite('user:role:add administrator admin');

    await page.goto('/user/login');
    await page.fill('#edit-name', 'admin');
    await page.fill('#edit-pass', 'admin');
    await page.click('#edit-submit');

    // Verify we're logged in
    await expect(page.locator('#toolbar-administration')).toBeVisible();

    // Logout
    await page.goto('/user/logout');

    // Wait for logout to complete - should be redirected to login page
    await page.waitForURL('**/user/login');
    await expect(page.locator('#user-login-form')).toBeVisible();

    // Verify admin toolbar is not visible
    await expect(page.locator('#toolbar-administration')).not.toBeVisible();
  });

  test('should handle invalid login credentials gracefully', async ({
    page,
  }) => {
    // Navigate to login page
    await page.goto('/user/login');
    await page.waitForLoadState('networkidle');

    // Fill in invalid credentials
    await page.fill('#edit-name', 'invalid_user');
    await page.fill('#edit-pass', 'invalid_password');

    // Submit login form
    await page.click('#edit-submit');

    // Should remain on login page with error message
    await expect(page.locator('#user-login-form')).toBeVisible();
    await expect(page.locator('.messages--error')).toBeVisible();

    // Should not have admin toolbar
    await expect(page.locator('#toolbar-administration')).not.toBeVisible();
  });

  test('should verify test environment configuration', async ({ page }) => {
    // Create admin user
    await execDrushInTestSite(
      'user:create admin --mail="admin@example.com" --password="admin"',
    );
    await execDrushInTestSite('user:role:add administrator admin');

    // Login
    await page.goto('/user/login');
    await page.fill('#edit-name', 'admin');
    await page.fill('#edit-pass', 'admin');
    await page.click('#edit-submit');

    // Basic environment checks
    if (ENVIRONMENT.isDDev) {
      console.log('Running in DDEV environment');
    }

    if (ENVIRONMENT.isCI) {
      console.log('Running in CI environment');
    }

    // Verify current theme
    await page.goto('/admin/appearance');
    await expect(page.locator('h1')).toContainText('Appearance');

    // Verify test isolation is working by checking we have a clean site
    await page.goto('/admin/content');
    await expect(page.locator('h1')).toContainText('Content');
  });

  test('should verify browser console has no critical errors', async ({
    page,
  }) => {
    const consoleErrors = [];

    // Capture console messages
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });

    // Create admin user and login
    await execDrushInTestSite(
      'user:create admin --mail="admin@example.com" --password="admin"',
    );
    await execDrushInTestSite('user:role:add administrator admin');

    await page.goto('/user/login');
    await page.fill('#edit-name', 'admin');
    await page.fill('#edit-pass', 'admin');
    await page.click('#edit-submit');

    // Navigate to a few key pages
    await page.goto('/admin/structure/block');
    await page.waitForLoadState('networkidle');

    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Check for critical JavaScript errors
    const criticalErrors = consoleErrors.filter(
      error =>
        error.includes('Uncaught') ||
        error.includes('TypeError') ||
        error.includes('ReferenceError'),
    );

    expect(criticalErrors).toHaveLength(0);
  });
});
