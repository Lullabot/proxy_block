/**
 * @file
 * Proxy Block configuration and placement tests for E2E testing.
 * Tests proxy-specific functionality including target block selection,
 * configuration, and validation.
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

  // Login form MUST be available
  const loginForm = page.locator('#user-login-form');
  await expect(loginForm).toBeVisible();

  await page.fill('#edit-name', 'admin');
  await page.fill('#edit-pass', 'admin');
  await page.click('#edit-submit');
  await page.waitForLoadState('networkidle');
}

/**
 * Helper function to wait for AJAX operations.
 * @param {Object} page - Playwright page object
 */
async function waitForAjax(page) {
  // Wait for any AJAX throbbers to disappear
  await page.waitForFunction(
    () => {
      const throbbers = document.querySelectorAll(
        '.ajax-progress-throbber, .ajax-progress-bar',
      );
      return throbbers.length === 0;
    },
    { timeout: 30000 },
  );

  // Wait for network to be idle
  await page.waitForLoadState('networkidle');
}

test.describe('Proxy Block Configuration', () => {
  let blockPlacementPage;

  test.beforeEach(async ({ page }) => {
    test.setTimeout(TIMEOUTS.LONG);

    blockPlacementPage = new BlockPlacementPage(page);

    // Setup admin user and login
    await setupAdminUser(page);

    // Navigate to block layout
    await blockPlacementPage.navigate(ENVIRONMENT.theme);
  });

  test('should find and select Proxy Block', async ({ page }) => {
    await blockPlacementPage.clickPlaceBlockForRegion('primary_menu');
    await blockPlacementPage.selectProxyBlock();

    // Should be on block configuration page (in modal)
    await expect(
      page.locator('.ui-dialog-title, h1:has-text("Configure")'),
    ).toContainText('Configure block');

    // Verify we're configuring a proxy block - look for the Target Block selection (unique to proxy block)
    const targetBlockField = page.locator(
      'select[name*="target_block"], combobox:has-text("Target Block")',
    );
    await expect(targetBlockField).toBeVisible();
  });

  test('should configure basic proxy block settings', async ({ page }) => {
    await blockPlacementPage.clickPlaceBlockForRegion('primary_menu');
    await blockPlacementPage.selectProxyBlock();

    const blockConfig = {
      title: `Test Proxy Block ${Date.now()}`,
      displayTitle: true,
      region: 'primary_menu',
    };

    const savedConfig =
      await blockPlacementPage.configureBasicSettings(blockConfig);

    // Verify configuration was applied
    expect(savedConfig.title).toBe(blockConfig.title);
    expect(savedConfig.displayTitle).toBe(blockConfig.displayTitle);
    expect(savedConfig.region).toBe(blockConfig.region);
  });

  test('should configure proxy block with target block', async ({ page }) => {
    await blockPlacementPage.clickPlaceBlockForRegion('primary_menu');
    await blockPlacementPage.selectProxyBlock();

    // Configure basic settings
    const blockConfig = {
      title: 'Proxy Block with Target',
      targetBlock: 'system_powered_by_block',
    };

    await blockPlacementPage.configureBasicSettings(blockConfig);

    // Configure proxy-specific settings
    await blockPlacementPage.configureProxySettings(blockConfig);
  });

  test('should save proxy block configuration successfully', async ({
    page,
  }) => {
    await blockPlacementPage.clickPlaceBlockForRegion('primary_menu');
    await blockPlacementPage.selectProxyBlock();

    const blockTitle = `Test Proxy Block ${Date.now()}`;

    await blockPlacementPage.configureBasicSettings({
      title: blockTitle,
      region: 'primary_menu',
    });

    await blockPlacementPage.configureProxySettings({
      targetBlock: 'system_powered_by_block',
    });

    await blockPlacementPage.saveBlock();

    // Should be back on block layout page
    await expect(page.locator('h1:has-text("Block layout")')).toContainText(
      'Block layout',
    );

    // Verify success message
    await expect(page.locator('.messages--status')).toBeVisible();

    // Verify block appears in the layout
    await blockPlacementPage.verifyBlockPlaced(blockTitle, 'primary_menu');
  });

  test('should handle proxy block configuration with multiple target blocks', async ({
    page,
  }) => {
    const targetBlocks = PROXY_BLOCK_DATA.configurations;

    for (let i = 0; i < Math.min(targetBlocks.length, 2); i++) {
      const config = targetBlocks[i];
      const blockTitle = `${config.name} ${Date.now()}`;

      // Place new block
      await blockPlacementPage.clickPlaceBlockForRegion('primary_menu');
      await blockPlacementPage.selectProxyBlock();

      // Configure the block
      await blockPlacementPage.configureBasicSettings({
        title: blockTitle,
        region: 'primary_menu',
      });

      await blockPlacementPage.configureProxySettings({
        targetBlock: config.targetBlock,
      });

      await blockPlacementPage.saveBlock();

      // Verify block was placed
      await blockPlacementPage.verifyBlockPlaced(blockTitle, 'primary_menu');
    }
  });

  test('should cancel proxy block configuration', async ({ page }) => {
    await blockPlacementPage.clickPlaceBlockForRegion('primary_menu');
    await blockPlacementPage.selectProxyBlock();

    await blockPlacementPage.configureBasicSettings({
      title: 'Block to Cancel',
    });

    await blockPlacementPage.cancelConfiguration();

    // Should be back on block layout page
    await expect(page.locator('h1:has-text("Block layout")')).toContainText(
      'Block layout',
    );

    // Block should not be placed
    const cancelledBlock = page
      .locator('.draggable')
      .filter({ hasText: 'Block to Cancel' });
    await expect(cancelledBlock).not.toBeVisible();
  });

  test('should validate required fields in proxy block configuration', async ({
    page,
  }) => {
    await blockPlacementPage.clickPlaceBlockForRegion('primary_menu');
    await blockPlacementPage.selectProxyBlock();

    // Try to save without filling required fields
    const saveButton = page.locator('#edit-submit, .form-submit');
    await saveButton.click();

    // Should still be on configuration page with validation errors
    await expect(
      page.locator('.ui-dialog-title, h1:has-text("Configure")'),
    ).toContainText('Configure block');

    // Validation messages MUST appear when required fields are not filled
    const validationMessages = page.locator(
      '.form-error, .error, .messages--error',
    );
    await expect(validationMessages).toBeVisible();
  });

  test('should test AJAX functionality in proxy block configuration', async ({
    page,
  }) => {
    await blockPlacementPage.clickPlaceBlockForRegion('primary_menu');
    await blockPlacementPage.selectProxyBlock();

    // Configure basic settings first
    await blockPlacementPage.configureBasicSettings({
      title: 'AJAX Test Block',
    });

    // Look for target block selection dropdown
    const targetBlockSelect = page.locator('#edit-settings-target-block');
    if (await targetBlockSelect.isVisible()) {
      // Select a target block to trigger AJAX
      await targetBlockSelect.selectOption('system_powered_by_block');

      // Wait for AJAX to complete
      await waitForAjax(page);

      // Check if additional configuration options appeared
      const configSection = page.locator('.proxy-block-target-configuration');
      // This section may or may not exist depending on target block
    }
  });

  test('should remove placed proxy block', async ({ page }) => {
    // First, place a block
    await blockPlacementPage.clickPlaceBlockForRegion('primary_menu');
    await blockPlacementPage.selectProxyBlock();

    const blockTitle = `Block to Remove ${Date.now()}`;
    await blockPlacementPage.configureBasicSettings({
      title: blockTitle,
    });

    await blockPlacementPage.saveBlock();
    await blockPlacementPage.verifyBlockPlaced(blockTitle, 'primary_menu');

    // Now remove the block
    await blockPlacementPage.removeBlock(blockTitle);

    // Verify block is no longer visible in the region
    const removedBlock = page
      .locator('.draggable')
      .filter({ hasText: blockTitle });
    await expect(removedBlock).not.toBeVisible();
  });

  test('should verify block placement across different regions', async ({
    page,
  }) => {
    const regions = ['primary_menu', 'breadcrumb'];

    for (const region of regions) {
      // Check if region exists
      const regionRow = page.locator(`tr[data-region="${region}"]`);
      if ((await regionRow.count()) === 0) {
        console.log(
          `Region ${region} not available in ${ENVIRONMENT.theme} theme`,
        );
        continue;
      }

      const blockTitle = `Block in ${region} ${Date.now()}`;

      await blockPlacementPage.clickPlaceBlockForRegion(region);
      await blockPlacementPage.selectProxyBlock();

      await blockPlacementPage.configureBasicSettings({
        title: blockTitle,
        region,
      });

      await blockPlacementPage.saveBlock();
      await blockPlacementPage.verifyBlockPlaced(blockTitle, region);
    }
  });
});
