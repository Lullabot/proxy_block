/**
 * @file
 * Authentication and basic setup tests for Proxy Block E2E testing.
 */

const { test, expect } = require('@playwright/test');
const { loginAsAdmin, logout, verifyAdminPermissions } = require('../helpers/drupal-auth');
const { verifyModuleEnabled, checkForPHPErrors } = require('../helpers/drupal-nav');
const { TIMEOUTS, TEST_DATA, ENVIRONMENT } = require('../utils/constants');

test.describe('Authentication and Setup', () => {
  test.beforeEach(async ({ page }) => {
    // Set longer timeout for admin operations
    test.setTimeout(TIMEOUTS.LONG);
    
    // Navigate to homepage first to verify site is accessible
    await page.goto('/');
    await page.waitForLoadState('networkidle');
  });

  test('should verify site is accessible and functional', async ({ page }) => {
    // Verify homepage loads
    await expect(page.locator('html')).toBeVisible();
    await expect(page.locator('body')).toBeVisible();
    
    // Verify page title is not empty
    const title = await page.title();
    expect(title).toBeTruthy();
    expect(title.length).toBeGreaterThan(0);
    
    // Check for any PHP errors
    await checkForPHPErrors(page);
  });

  test('should login as admin user successfully', async ({ page }) => {
    // Attempt to login
    await loginAsAdmin(page, TEST_DATA.admin);
    
    // Verify admin toolbar is visible
    await expect(page.locator('#toolbar-administration')).toBeVisible();
    
    // Verify we're on an admin page or dashboard
    const url = page.url();
    expect(url).toMatch(/\/(admin|user\/\d+)$/);
    
    // Check for any PHP errors after login
    await checkForPHPErrors(page);
  });

  test('should verify admin user has required permissions', async ({ page }) => {
    await loginAsAdmin(page, TEST_DATA.admin);
    await verifyAdminPermissions(page);
    
    // Verify access to specific proxy block related pages
    await page.goto('/admin/structure');
    await expect(page.locator('h1')).toContainText('Structure');
    
    await page.goto('/admin/structure/block');
    await expect(page.locator('h1')).toContainText('Block layout');
    
    // Check for any PHP errors
    await checkForPHPErrors(page);
  });

  test('should verify proxy_block module is enabled', async ({ page }) => {
    await loginAsAdmin(page, TEST_DATA.admin);
    await verifyModuleEnabled(page, 'proxy_block');
  });

  test('should verify proxy block is available in block placement', async ({ page }) => {
    await loginAsAdmin(page, TEST_DATA.admin);
    
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
    
    // Verify it's in the correct category
    const blockItem = proxyBlockLink.locator('xpath=ancestor::div[contains(@class, "block-list-item")]');
    await expect(blockItem).toBeVisible();
  });

  test('should logout successfully', async ({ page }) => {
    await loginAsAdmin(page, TEST_DATA.admin);
    
    // Verify we're logged in
    await expect(page.locator('#toolbar-administration')).toBeVisible();
    
    // Logout
    await logout(page);
    
    // Verify we're logged out (should be on login page)
    await expect(page.locator('#user-login-form')).toBeVisible();
    
    // Verify admin toolbar is not visible
    await expect(page.locator('#toolbar-administration')).not.toBeVisible();
  });

  test('should handle invalid login credentials gracefully', async ({ page }) => {
    // Navigate to login page
    await page.goto('/user/login');
    
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
    await loginAsAdmin(page, TEST_DATA.admin);
    
    // Check if this is a proper test environment
    await page.goto('/admin/config/development/settings');
    
    // Verify we can access development settings (indicates test environment)
    const pageContent = await page.textContent('body');
    
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
    
    const currentTheme = page.locator('.theme-default');
    await expect(currentTheme).toBeVisible();
  });

  test('should verify browser console has no critical errors', async ({ page }) => {
    const consoleErrors = [];
    
    // Capture console messages
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });
    
    await loginAsAdmin(page, TEST_DATA.admin);
    
    // Navigate to a few key pages
    await page.goto('/admin/structure/block');
    await page.waitForLoadState('networkidle');
    
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    
    // Check for critical JavaScript errors
    const criticalErrors = consoleErrors.filter(error => 
      error.includes('Uncaught') || 
      error.includes('TypeError') ||
      error.includes('ReferenceError')
    );
    
    expect(criticalErrors).toHaveLength(0);
  });
});