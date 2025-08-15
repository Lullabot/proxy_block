/**
 * @file
 * Drupal authentication helpers for Playwright tests.
 */

const { expect } = require('@playwright/test');

/**
 * Default admin credentials for DDEV environment.
 */
const DEFAULT_ADMIN_CREDENTIALS = {
  username: 'admin',
  password: 'admin',
};

/**
 * Login as admin user.
 *
 * @param {import('@playwright/test').Page} page
 * @param {Object} credentials
 * @param {string} credentials.username
 * @param {string} credentials.password
 */
async function loginAsAdmin(page, credentials = DEFAULT_ADMIN_CREDENTIALS) {
  // Navigate to user login page
  await page.goto('/user/login');
  
  // Wait for login form to be visible
  await expect(page.locator('#user-login-form')).toBeVisible();
  
  // Fill in credentials
  await page.fill('#edit-name', credentials.username);
  await page.fill('#edit-pass', credentials.password);
  
  // Submit login form
  await page.click('#edit-submit');
  
  // Wait for successful login (admin toolbar should be visible)
  await expect(page.locator('#toolbar-administration')).toBeVisible({ timeout: 10000 });
  
  // Verify we're logged in by checking for admin menu
  await expect(page.locator('.toolbar-menu-administration')).toBeVisible();
}

/**
 * Logout current user.
 *
 * @param {import('@playwright/test').Page} page
 */
async function logout(page) {
  // Navigate to logout URL
  await page.goto('/user/logout');
  
  // Wait for logout to complete - login form should be visible again
  await page.waitForURL('**/user/login');
  await expect(page.locator('#user-login-form')).toBeVisible();
}

/**
 * Verify admin user has required permissions.
 *
 * @param {import('@playwright/test').Page} page
 */
async function verifyAdminPermissions(page) {
  // Check access to admin pages
  await page.goto('/admin');
  await expect(page.locator('h1')).toContainText('Administration');
  
  // Check access to block layout
  await page.goto('/admin/structure/block');
  await expect(page.locator('h1')).toContainText('Block layout');
  
  // Check access to modules page
  await page.goto('/admin/modules');
  await expect(page.locator('h1')).toContainText('Modules');
}

/**
 * Create a test user with specific permissions.
 *
 * @param {import('@playwright/test').Page} page
 * @param {Object} userConfig
 * @param {string} userConfig.username
 * @param {string} userConfig.email
 * @param {string} userConfig.password
 * @param {string[]} userConfig.roles
 */
async function createTestUser(page, userConfig) {
  // Navigate to user creation page
  await page.goto('/admin/people/create');
  
  // Fill user form
  await page.fill('#edit-name', userConfig.username);
  await page.fill('#edit-mail', userConfig.email);
  await page.fill('#edit-pass-pass1', userConfig.password);
  await page.fill('#edit-pass-pass2', userConfig.password);
  
  // Assign roles if specified
  if (userConfig.roles) {
    for (const role of userConfig.roles) {
      await page.check(`#edit-roles-${role}`);
    }
  }
  
  // Submit form
  await page.click('#edit-submit');
  
  // Verify user was created
  await expect(page.locator('.messages--status')).toContainText('Created a new user account');
}

/**
 * Navigate to admin page with authentication check.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} path
 */
async function navigateToAdminPage(page, path) {
  await page.goto(path);
  
  // If redirected to login, we need to authenticate
  if (page.url().includes('/user/login')) {
    await loginAsAdmin(page);
    // Navigate to original page after login
    await page.goto(path);
  }
  
  // Wait for page to load
  await page.waitForLoadState('networkidle');
}

module.exports = {
  loginAsAdmin,
  logout,
  verifyAdminPermissions,
  createTestUser,
  navigateToAdminPage,
  DEFAULT_ADMIN_CREDENTIALS,
};