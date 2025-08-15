/**
 * @file
 * Drupal navigation utilities for Playwright tests.
 */

const { expect } = require('@playwright/test');

/**
 * Navigate to block layout administration.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} theme - Theme name (default: 'olivero')
 */
async function navigateToBlockLayout(page, theme = 'olivero') {
  await page.goto(`/admin/structure/block/list/${theme}`);
  await expect(page.locator('h1')).toContainText('Block layout');
  await page.waitForLoadState('networkidle');
}

/**
 * Navigate to modules administration.
 *
 * @param {import('@playwright/test').Page} page
 */
async function navigateToModules(page) {
  await page.goto('/admin/modules');
  await expect(page.locator('h1')).toContainText('Modules');
  await page.waitForLoadState('networkidle');
}

/**
 * Verify module is enabled.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} moduleName
 */
async function verifyModuleEnabled(page, moduleName) {
  await navigateToModules(page);
  
  // Look for the module checkbox and verify it's checked
  const moduleCheckbox = page.locator(`input[name="modules[${moduleName}][enable]"]`);
  await expect(moduleCheckbox).toBeChecked();
}

/**
 * Navigate to content type management.
 *
 * @param {import('@playwright/test').Page} page
 */
async function navigateToContentTypes(page) {
  await page.goto('/admin/structure/types');
  await expect(page.locator('h1')).toContainText('Content types');
  await page.waitForLoadState('networkidle');
}

/**
 * Create a test node of specified type.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} contentType
 * @param {Object} nodeData
 * @param {string} nodeData.title
 * @param {string} nodeData.body
 */
async function createTestNode(page, contentType = 'page', nodeData = {}) {
  const title = nodeData.title || `Test ${contentType} ${Date.now()}`;
  const body = nodeData.body || `Test content for ${title}`;
  
  await page.goto(`/node/add/${contentType}`);
  
  // Fill title
  await page.fill('#edit-title-0-value', title);
  
  // Fill body if it exists
  const bodyField = page.locator('#edit-body-0-value');
  if (await bodyField.isVisible()) {
    await bodyField.fill(body);
  }
  
  // Save the node
  await page.click('#edit-submit');
  
  // Wait for node to be created
  await expect(page.locator('h1')).toContainText(title);
  
  return { title, body, url: page.url() };
}

/**
 * Navigate to Layout Builder for a content type.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} contentType
 */
async function navigateToLayoutBuilder(page, contentType = 'page') {
  await page.goto(`/admin/structure/types/manage/${contentType}/display`);
  await expect(page.locator('h1')).toContainText('Manage display');
  
  // Enable Layout Builder if not already enabled
  const layoutBuilderCheckbox = page.locator('#edit-layout-enabled');
  if (!(await layoutBuilderCheckbox.isChecked())) {
    await layoutBuilderCheckbox.check();
    await page.click('#edit-submit');
    await expect(page.locator('.messages--status')).toBeVisible();
  }
}

/**
 * Clear Drupal cache.
 *
 * @param {import('@playwright/test').Page} page
 */
async function clearCache(page) {
  await page.goto('/admin/config/development/performance');
  await page.click('#edit-clear');
  await expect(page.locator('.messages--status')).toContainText('Caches cleared');
}

/**
 * Check for PHP errors on page.
 *
 * @param {import('@playwright/test').Page} page
 */
async function checkForPHPErrors(page) {
  // Check for visible PHP errors
  const phpErrors = page.locator('.php-error, .error-message');
  await expect(phpErrors).toHaveCount(0);
  
  // Check for watchdog errors in admin interface
  const adminMessages = page.locator('.messages--error');
  if (await adminMessages.count() > 0) {
    const errorText = await adminMessages.textContent();
    if (errorText && errorText.includes('PHP')) {
      throw new Error(`PHP error detected: ${errorText}`);
    }
  }
}

/**
 * Wait for AJAX to complete.
 *
 * @param {import('@playwright/test').Page} page
 */
async function waitForAjax(page) {
  // Wait for any AJAX throbbers to disappear
  await page.waitForFunction(() => {
    const throbbers = document.querySelectorAll('.ajax-progress-throbber, .ajax-progress-bar');
    return throbbers.length === 0;
  }, { timeout: 30000 });
  
  // Wait for network to be idle
  await page.waitForLoadState('networkidle');
}

module.exports = {
  navigateToBlockLayout,
  navigateToModules,
  verifyModuleEnabled,
  navigateToContentTypes,
  createTestNode,
  navigateToLayoutBuilder,
  clearCache,
  checkForPHPErrors,
  waitForAjax,
};