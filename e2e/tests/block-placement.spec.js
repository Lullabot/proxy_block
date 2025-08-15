/**
 * @file
 * Block placement tests for Proxy Block E2E testing.
 */

const { test, expect } = require('@playwright/test');
const { loginAsAdmin } = require('../helpers/drupal-auth');
const { waitForAjax, checkForPHPErrors } = require('../helpers/drupal-nav');
const { BlockPlacementPage } = require('../page-objects/block-placement-page');
const { TIMEOUTS, TEST_DATA, ENVIRONMENT, PROXY_BLOCK_DATA } = require('../utils/constants');

test.describe('Block Placement Interface', () => {
  let blockPlacementPage;

  test.beforeEach(async ({ page }) => {
    test.setTimeout(TIMEOUTS.LONG);
    
    blockPlacementPage = new BlockPlacementPage(page);
    
    // Login as admin
    await loginAsAdmin(page, TEST_DATA.admin);
    
    // Navigate to block layout
    await blockPlacementPage.navigate(ENVIRONMENT.theme);
  });

  test('should navigate to block placement interface successfully', async ({ page }) => {
    // Should be on block layout page
    await expect(page.locator('h1')).toContainText('Block layout');
    
    // Verify regions are visible
    const regions = ['header', 'content', 'sidebar_first', 'footer'];
    for (const region of regions) {
      const regionRow = page.locator(`tr[data-region="${region}"]`);
      if (await regionRow.count() > 0) {
        await expect(regionRow).toBeVisible();
      }
    }
    
    await checkForPHPErrors(page);
  });

  test('should open place block dialog for content region', async ({ page }) => {
    await blockPlacementPage.clickPlaceBlockForRegion('content');
    
    // Should be on place block page
    await expect(page.locator('h1')).toContainText('Place block');
    
    // Verify block list is visible
    await expect(page.locator('.block-list')).toBeVisible();
    
    await checkForPHPErrors(page);
  });

  test('should find and select Proxy Block', async ({ page }) => {
    await blockPlacementPage.clickPlaceBlockForRegion('content');
    await blockPlacementPage.selectProxyBlock();
    
    // Should be on block configuration page
    await expect(page.locator('h1')).toContainText('Configure block');
    
    // Verify we're configuring a proxy block
    const formIdElement = page.locator('form[id*="proxy-block"]');
    if (await formIdElement.count() === 0) {
      // Fallback: check for proxy block specific elements
      await expect(page.locator('body')).toContainText('proxy');
    }
    
    await checkForPHPErrors(page);
  });

  test('should configure basic proxy block settings', async ({ page }) => {
    await blockPlacementPage.clickPlaceBlockForRegion('content');
    await blockPlacementPage.selectProxyBlock();
    
    const blockConfig = {
      title: `Test Proxy Block ${Date.now()}`,
      displayTitle: true,
      region: 'content',
    };
    
    const savedConfig = await blockPlacementPage.configureBasicSettings(blockConfig);
    
    // Verify configuration was applied
    expect(savedConfig.title).toBe(blockConfig.title);
    expect(savedConfig.displayTitle).toBe(blockConfig.displayTitle);
    expect(savedConfig.region).toBe(blockConfig.region);
    
    await checkForPHPErrors(page);
  });

  test('should configure proxy block with target block', async ({ page }) => {
    await blockPlacementPage.clickPlaceBlockForRegion('content');
    await blockPlacementPage.selectProxyBlock();
    
    // Configure basic settings
    const blockConfig = {
      title: 'Proxy Block with Target',
      targetBlock: 'system_powered_by_block',
    };
    
    await blockPlacementPage.configureBasicSettings(blockConfig);
    
    // Configure proxy-specific settings
    await blockPlacementPage.configureProxySettings(blockConfig);
    
    await checkForPHPErrors(page);
  });

  test('should save proxy block configuration successfully', async ({ page }) => {
    await blockPlacementPage.clickPlaceBlockForRegion('content');
    await blockPlacementPage.selectProxyBlock();
    
    const blockTitle = `Test Proxy Block ${Date.now()}`;
    
    await blockPlacementPage.configureBasicSettings({
      title: blockTitle,
      region: 'content',
    });
    
    await blockPlacementPage.configureProxySettings({
      targetBlock: 'system_powered_by_block',
    });
    
    await blockPlacementPage.saveBlock();
    
    // Should be back on block layout page
    await expect(page.locator('h1')).toContainText('Block layout');
    
    // Verify success message
    await expect(page.locator('.messages--status')).toBeVisible();
    
    // Verify block appears in the layout
    await blockPlacementPage.verifyBlockPlaced(blockTitle, 'content');
    
    await checkForPHPErrors(page);
  });

  test('should handle proxy block configuration with multiple target blocks', async ({ page }) => {
    const targetBlocks = PROXY_BLOCK_DATA.configurations;
    
    for (let i = 0; i < Math.min(targetBlocks.length, 2); i++) {
      const config = targetBlocks[i];
      const blockTitle = `${config.name} ${Date.now()}`;
      
      // Place new block
      await blockPlacementPage.clickPlaceBlockForRegion('content');
      await blockPlacementPage.selectProxyBlock();
      
      // Configure the block
      await blockPlacementPage.configureBasicSettings({
        title: blockTitle,
        region: 'content',
      });
      
      await blockPlacementPage.configureProxySettings({
        targetBlock: config.targetBlock,
      });
      
      await blockPlacementPage.saveBlock();
      
      // Verify block was placed
      await blockPlacementPage.verifyBlockPlaced(blockTitle, 'content');
    }
    
    await checkForPHPErrors(page);
  });

  test('should cancel proxy block configuration', async ({ page }) => {
    await blockPlacementPage.clickPlaceBlockForRegion('content');
    await blockPlacementPage.selectProxyBlock();
    
    await blockPlacementPage.configureBasicSettings({
      title: 'Block to Cancel',
    });
    
    await blockPlacementPage.cancelConfiguration();
    
    // Should be back on block layout page
    await expect(page.locator('h1')).toContainText('Block layout');
    
    // Block should not be placed
    const cancelledBlock = page.locator('.draggable').filter({ hasText: 'Block to Cancel' });
    await expect(cancelledBlock).not.toBeVisible();
    
    await checkForPHPErrors(page);
  });

  test('should validate required fields in proxy block configuration', async ({ page }) => {
    await blockPlacementPage.clickPlaceBlockForRegion('content');
    await blockPlacementPage.selectProxyBlock();
    
    // Try to save without filling required fields
    const saveButton = page.locator('#edit-submit, .form-submit');
    await saveButton.click();
    
    // Should still be on configuration page with validation errors
    await expect(page.locator('h1')).toContainText('Configure block');
    
    // Look for validation messages
    const validationMessages = page.locator('.form-error, .error, .messages--error');
    if (await validationMessages.count() > 0) {
      await expect(validationMessages).toBeVisible();
    }
    
    await checkForPHPErrors(page);
  });

  test('should test AJAX functionality in proxy block configuration', async ({ page }) => {
    await blockPlacementPage.clickPlaceBlockForRegion('content');
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
      
      // Verify no AJAX errors occurred
      await checkForPHPErrors(page);
      
      // Check if additional configuration options appeared
      const configSection = page.locator('.proxy-block-target-configuration');
      // This section may or may not exist depending on target block
    }
  });

  test('should remove placed proxy block', async ({ page }) => {
    // First, place a block
    await blockPlacementPage.clickPlaceBlockForRegion('content');
    await blockPlacementPage.selectProxyBlock();
    
    const blockTitle = `Block to Remove ${Date.now()}`;
    await blockPlacementPage.configureBasicSettings({
      title: blockTitle,
    });
    
    await blockPlacementPage.saveBlock();
    await blockPlacementPage.verifyBlockPlaced(blockTitle, 'content');
    
    // Now remove the block
    await blockPlacementPage.removeBlock(blockTitle);
    
    // Verify block is no longer visible in the region
    const removedBlock = page.locator('.draggable').filter({ hasText: blockTitle });
    await expect(removedBlock).not.toBeVisible();
    
    await checkForPHPErrors(page);
  });

  test('should verify block placement across different regions', async ({ page }) => {
    const regions = ['content', 'sidebar_first'];
    
    for (const region of regions) {
      // Check if region exists
      const regionRow = page.locator(`tr[data-region="${region}"]`);
      if (await regionRow.count() === 0) {
        console.log(`Region ${region} not available in ${ENVIRONMENT.theme} theme`);
        continue;
      }
      
      const blockTitle = `Block in ${region} ${Date.now()}`;
      
      await blockPlacementPage.clickPlaceBlockForRegion(region);
      await blockPlacementPage.selectProxyBlock();
      
      await blockPlacementPage.configureBasicSettings({
        title: blockTitle,
        region: region,
      });
      
      await blockPlacementPage.saveBlock();
      await blockPlacementPage.verifyBlockPlaced(blockTitle, region);
    }
    
    await checkForPHPErrors(page);
  });
});