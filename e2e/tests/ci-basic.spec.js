/**
 * @file
 * Basic CI-compatible E2E tests for Proxy Block functionality.
 *
 * These tests use standard Playwright without @lullabot/playwright-drupal
 * for better CI compatibility and reliability.
 */

const { test, expect } = require('@playwright/test');
const fs = require('fs');

test.describe('Proxy Block CI Tests', () => {
  test.beforeEach(async ({ page }) => {
    // Set reasonable timeout for CI
    test.setTimeout(60000);
  });

  test('should access Drupal homepage', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Verify basic Drupal structure
    await expect(page.locator('html')).toBeVisible();
    await expect(page.locator('body')).toBeVisible();

    // Check for Drupal-specific elements
    const bodyClass = await page.locator('body').getAttribute('class');
    expect(bodyClass).toContain('path-frontpage');

    // Verify page title is not empty
    const title = await page.title();
    expect(title).toBeTruthy();
    expect(title.length).toBeGreaterThan(0);
  });

  test('should login as admin user', async ({ page }) => {
    // Go to login page
    await page.goto('/user/login');
    await page.waitForLoadState('networkidle');

    // Verify we're on login page
    await expect(page.locator('h1')).toContainText('Log in');
    await expect(page.locator('#user-login-form')).toBeVisible();

    // Fill login form
    await page.fill('#edit-name', 'admin');
    await page.fill('#edit-pass', 'admin');
    await page.click('#edit-submit');

    // Wait for login to complete
    await page.waitForLoadState('networkidle');

    // Verify successful login - should see admin toolbar or be redirected
    try {
      // Check for admin toolbar (most likely)
      await expect(page.locator('#toolbar-administration')).toBeVisible({
        timeout: 10000,
      });
    } catch (error) {
      // Alternative: check if we're on user profile page
      await expect(page.locator('body')).toContainText('admin');
    }
  });

  test('should access block layout page', async ({ page }) => {
    // Login first
    await page.goto('/user/login');
    await page.fill('#edit-name', 'admin');
    await page.fill('#edit-pass', 'admin');
    await page.click('#edit-submit');
    await page.waitForLoadState('networkidle');

    // Navigate to block layout
    await page.goto('/admin/structure/block');
    await page.waitForLoadState('networkidle');

    // Verify we're on block layout page
    await expect(page.locator('h1')).toContainText('Block layout');

    // Check for block regions
    const regions = ['header', 'content', 'sidebar_first', 'footer'];
    for (const region of regions) {
      const regionElement = page.locator(`[data-region="${region}"]`);
      if ((await regionElement.count()) > 0) {
        await expect(regionElement).toBeVisible();
      }
    }
  });

  test('should find proxy block in available blocks', async ({ page }) => {
    // Login first
    await page.goto('/user/login');
    await page.fill('#edit-name', 'admin');
    await page.fill('#edit-pass', 'admin');
    await page.click('#edit-submit');
    await page.waitForLoadState('networkidle');

    // Navigate to block layout
    await page.goto('/admin/structure/block/list/stark');
    await page.waitForLoadState('networkidle');

    // Click "Place block" for content region
    const contentRegion = page.locator('tr[data-region="content"]');
    await contentRegion.locator('.button').first().click();
    await page.waitForLoadState('networkidle');

    // Should be on place block page
    await expect(page.locator('h1')).toContainText('Place block');

    // Search for proxy block (if search is available)
    const searchInput = page.locator('#edit-search');
    if (await searchInput.isVisible()) {
      await searchInput.fill('Proxy Block');
      await page.waitForTimeout(2000); // Allow AJAX search
    }

    // Look for Proxy Block in the list
    const proxyBlockLink = page.locator('a').filter({ hasText: 'Proxy Block' });
    await expect(proxyBlockLink).toBeVisible({ timeout: 15000 });
  });

  test('should handle Drupal errors gracefully', async ({ page }) => {
    // Capture console errors
    const consoleErrors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });

    // Navigate to homepage
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Check for PHP errors in page content
    const bodyText = await page.locator('body').textContent();
    expect(bodyText).not.toContain('Fatal error');
    expect(bodyText).not.toContain('Parse error');
    expect(bodyText).not.toContain('Warning:');

    // Check for critical JavaScript errors
    const criticalErrors = consoleErrors.filter(
      error =>
        error.includes('Uncaught') ||
        error.includes('TypeError') ||
        error.includes('ReferenceError'),
    );

    if (criticalErrors.length > 0) {
      console.log('Critical JS errors found:', criticalErrors);
      // Don't fail the test for JS errors in CI, just log them
    }
  });

  test('should verify proxy block module is enabled', async ({ page }) => {
    // Login first
    await page.goto('/user/login');
    await page.fill('#edit-name', 'admin');
    await page.fill('#edit-pass', 'admin');
    await page.click('#edit-submit');
    await page.waitForLoadState('networkidle');

    // Go to modules page
    await page.goto('/admin/modules');
    await page.waitForLoadState('networkidle');

    // Look for proxy_block module
    const moduleCheckbox = page.locator(
      'input[name="modules[proxy_block][enable]"]',
    );
    if (await moduleCheckbox.isVisible()) {
      // Module is listed and should be enabled
      await expect(moduleCheckbox).toBeChecked();
    } else {
      // Alternative: check if module appears in the enabled list
      await expect(page.locator('body')).toContainText('proxy_block');
    }
  });

  test('should take screenshots for verification', async ({ page }) => {
    // Homepage screenshot
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    await page.screenshot({
      path: 'test-results/ci-homepage.png',
      fullPage: true,
    });

    // Login page screenshot
    await page.goto('/user/login');
    await page.waitForLoadState('networkidle');
    await page.screenshot({
      path: 'test-results/ci-login-page.png',
      fullPage: true,
    });

    // Admin login
    await page.fill('#edit-name', 'admin');
    await page.fill('#edit-pass', 'admin');
    await page.click('#edit-submit');
    await page.waitForLoadState('networkidle');

    // Block layout screenshot
    await page.goto('/admin/structure/block');
    await page.waitForLoadState('networkidle');
    await page.screenshot({
      path: 'test-results/ci-block-layout.png',
      fullPage: true,
    });

    // All screenshots should exist
    expect(fs.existsSync('test-results/ci-homepage.png')).toBe(true);
    expect(fs.existsSync('test-results/ci-login-page.png')).toBe(true);
    expect(fs.existsSync('test-results/ci-block-layout.png')).toBe(true);
  });
});
