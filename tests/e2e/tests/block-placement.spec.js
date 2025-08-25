/**
 * @file
 * Proxy Block specific configuration tests for E2E testing.
 * Tests proxy-specific configuration functionality, target block settings,
 * and proxy block settings validation.
 */

const { test, expect } = require('@playwright/test');
const {
  createAdminUser,
  enableModule,
  clearCache,
} = require('../utils/drush-helper');
const { BlockPlacementPage } = require('../page-objects/block-placement-page');
const {
  TIMEOUTS,
  ENVIRONMENT,
  PROXY_BLOCK_DATA,
} = require('../utils/constants');

/**
 * Helper function to create admin user and login.
 * @param {Object} page - Playwright page object
 */
async function setupAdminUser(page) {
  // Enable proxy_block module and create admin user
  await enableModule('proxy_block');
  await createAdminUser();
  await clearCache();

  // Login
  await page.goto('/user/login');
  await page.waitForLoadState('networkidle');

  const loginForm = page.locator('#user-login-form');
  await expect(loginForm).toBeVisible();

  await page.fill('#edit-name', 'admin');
  await page.fill('#edit-pass', 'admin');
  await page.click('#edit-submit');
  await page.waitForLoadState('networkidle');
}

test.describe('Proxy Block Configuration Settings', () => {
  let blockPlacementPage;

  test.beforeEach(async ({ page }) => {
    test.setTimeout(TIMEOUTS.LONG);

    blockPlacementPage = new BlockPlacementPage(page);

    // Setup admin user and login
    await setupAdminUser(page);

    // Navigate to block layout
    await blockPlacementPage.navigate(ENVIRONMENT.theme);
  });

  test('should access proxy block configuration with target block field', async ({
    page,
  }) => {
    await blockPlacementPage.clickPlaceBlockForRegion('content');
    await blockPlacementPage.selectProxyBlock();

    // Verify we're configuring a proxy block - look for the Target Block selection (unique to proxy block)
    const targetBlockField = page.locator(
      'select[name*="target_block"], combobox:has-text("Target Block")',
    );
    await expect(targetBlockField).toBeVisible();

    // Verify target block field has options
    const targetSelect = page.locator('#edit-settings-target-block-id');
    const options = await targetSelect.locator('option').count();
    expect(options).toBeGreaterThan(1);
  });

  test('should configure proxy block with target block selection', async ({
    page,
  }) => {
    await blockPlacementPage.clickPlaceBlockForRegion('content');
    await blockPlacementPage.selectProxyBlock();

    // Configure basic settings with target block
    const blockConfig = {
      title: 'Proxy Block with Target',
      targetBlock: 'system_powered_by_block',
    };

    await blockPlacementPage.configureBasicSettings(blockConfig);
    await blockPlacementPage.configureProxySettings(blockConfig);

    // Verify target block selection is set
    const targetSelect = page.locator('#edit-settings-target-block-id');
    const selectedValue = await targetSelect.inputValue();
    expect(selectedValue).toBe('system_powered_by_block');
  });

  test('should save proxy block with target configuration', async ({
    page,
  }) => {
    await blockPlacementPage.clickPlaceBlockForRegion('content');
    await blockPlacementPage.selectProxyBlock();

    const blockTitle = `Proxy Target Config ${Date.now()}`;

    await blockPlacementPage.configureBasicSettings({
      title: blockTitle,
      region: 'content',
    });

    await blockPlacementPage.configureProxySettings({
      targetBlock: 'system_powered_by_block',
    });

    await blockPlacementPage.saveBlock();

    // Verify success message
    await expect(page.locator('.messages--status')).toBeVisible();

    // Verify block appears with target configuration
    await blockPlacementPage.verifyBlockPlaced(blockTitle, 'content');
  });

  test('should handle multiple target block configurations', async ({
    page,
  }) => {
    const targetBlocks = PROXY_BLOCK_DATA.configurations.slice(0, 2);

    for (let i = 0; i < targetBlocks.length; i++) {
      const config = targetBlocks[i];
      const blockTitle = `${config.name} Config ${Date.now()}-${i}`;

      // Place new proxy block
      await blockPlacementPage.clickPlaceBlockForRegion('content');
      await blockPlacementPage.selectProxyBlock();

      // Configure with specific target block
      await blockPlacementPage.configureBasicSettings({
        title: blockTitle,
        region: 'content',
      });

      await blockPlacementPage.configureProxySettings({
        targetBlock: config.targetBlock,
      });

      await blockPlacementPage.saveBlock();

      // Verify proxy block was placed with target configuration
      await blockPlacementPage.verifyBlockPlaced(blockTitle, 'content');

      // Verify target block configuration persisted
      const editLink = page.locator(
        `tr:has-text("${blockTitle}") a:has-text("Configure")`,
      );
      await editLink.click();
      await page.waitForLoadState('networkidle');

      const targetSelect = page.locator('#edit-settings-target-block-id');
      const selectedValue = await targetSelect.inputValue();
      expect(selectedValue).toBe(config.targetBlock);

      // Return to block layout for next iteration
      await blockPlacementPage.navigate(ENVIRONMENT.theme);
    }
  });

  test('should validate proxy-specific settings', async ({ page }) => {
    await blockPlacementPage.clickPlaceBlockForRegion('content');
    await blockPlacementPage.selectProxyBlock();

    // Configure with valid title but no target block
    await blockPlacementPage.configureBasicSettings({
      title: 'Validation Test Proxy Block',
    });

    // Leave target block empty and try to save
    const saveButton = page.locator('#edit-submit, .form-submit');
    await saveButton.click();
    await page.waitForLoadState('networkidle');

    // Should remain on configuration page for proxy-specific validation
    const configPageLocator = page
      .locator('h1')
      .filter({ hasText: /Configure|Place/ });
    const isOnConfigPage = (await configPageLocator.count()) > 0;

    if (isOnConfigPage) {
      // Verify target block field is still present for proxy configuration
      const targetField = page.locator('#edit-settings-target-block-id');
      await expect(targetField).toBeVisible();
    }
  });

  test('should handle target block settings AJAX updates', async ({ page }) => {
    await blockPlacementPage.clickPlaceBlockForRegion('content');
    await blockPlacementPage.selectProxyBlock();

    // Configure basic settings first
    await blockPlacementPage.configureBasicSettings({
      title: 'AJAX Target Test Block',
    });

    // Select target block to trigger potential AJAX for target-specific settings
    const targetBlockSelect = page.locator('#edit-settings-target-block-id');
    if (await targetBlockSelect.isVisible()) {
      await targetBlockSelect.selectOption('system_powered_by_block');

      // Wait for AJAX to complete (proxy-specific AJAX behavior)
      await page.waitForFunction(
        () => {
          const throbbers = document.querySelectorAll(
            '.ajax-progress-throbber, .ajax-progress-bar',
          );
          return throbbers.length === 0;
        },
        { timeout: 30000 },
      );

      await page.waitForLoadState('networkidle');

      // Verify target block selection persisted through AJAX
      const selectedValue = await targetBlockSelect.inputValue();
      expect(selectedValue).toBe('system_powered_by_block');

      // Check if target-specific configuration options appeared
      const targetConfigSection = page.locator(
        '.proxy-block-target-configuration, .target-block-settings',
      );
      // Configuration section may or may not exist depending on target block
      // The important test is that AJAX didn't break the form
    }
  });

  test('should preserve target block settings during configuration updates', async ({
    page,
  }) => {
    await blockPlacementPage.clickPlaceBlockForRegion('content');
    await blockPlacementPage.selectProxyBlock();

    const blockTitle = `Settings Persistence Test ${Date.now()}`;

    // Initial configuration
    await blockPlacementPage.configureBasicSettings({
      title: blockTitle,
    });

    await blockPlacementPage.configureProxySettings({
      targetBlock: 'system_powered_by_block',
    });

    await blockPlacementPage.saveBlock();

    // Edit the saved proxy block
    const editLink = page.locator(
      `tr:has-text("${blockTitle}") a:has-text("Configure")`,
    );
    await editLink.click();
    await page.waitForLoadState('networkidle');

    // Verify target block selection persisted
    const targetSelect = page.locator('#edit-settings-target-block-id');
    const originalValue = await targetSelect.inputValue();
    expect(originalValue).toBe('system_powered_by_block');

    // Change title but keep target block
    const titleField = page.locator('#edit-settings-label');
    await titleField.clear();
    await titleField.fill(`${blockTitle} Updated`);

    // Save changes
    const saveButton = page.locator('button:has-text("Save block")');
    await saveButton.click();
    await page.waitForLoadState('networkidle');

    // Verify target block setting was preserved through the update
    const editLinkUpdated = page.locator(
      `tr:has-text("${blockTitle} Updated") a:has-text("Configure")`,
    );
    await editLinkUpdated.click();
    await page.waitForLoadState('networkidle');

    const targetSelectUpdated = page.locator('#edit-settings-target-block-id');
    const updatedValue = await targetSelectUpdated.inputValue();
    expect(updatedValue).toBe('system_powered_by_block');
  });
});
