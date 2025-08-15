/**
 * @file
 * Frontend rendering tests for Proxy Block E2E testing.
 */

const { test, expect } = require('@playwright/test');
const { loginAsAdmin, logout } = require('../helpers/drupal-auth');
const { createTestNode, checkForPHPErrors } = require('../helpers/drupal-nav');
const { BlockPlacementPage } = require('../page-objects/block-placement-page');
const { FrontendPage } = require('../page-objects/frontend-page');
const { TIMEOUTS, TEST_DATA, ENVIRONMENT, PROXY_BLOCK_DATA } = require('../utils/constants');

test.describe('Frontend Rendering', () => {
  let blockPlacementPage;
  let frontendPage;
  let testBlocks = [];

  test.beforeEach(async ({ page }) => {
    test.setTimeout(TIMEOUTS.LONG);
    
    blockPlacementPage = new BlockPlacementPage(page);
    frontendPage = new FrontendPage(page);
    
    // Login as admin to set up test blocks
    await loginAsAdmin(page, TEST_DATA.admin);
  });

  test.afterEach(async ({ page }) => {
    // Clean up test blocks after each test
    await loginAsAdmin(page, TEST_DATA.admin);
    await blockPlacementPage.navigate(ENVIRONMENT.theme);
    
    for (const blockTitle of testBlocks) {
      try {
        await blockPlacementPage.removeBlock(blockTitle);
      } catch (error) {
        console.log(`Could not remove block: ${blockTitle}`);
      }
    }
    
    testBlocks = [];
  });

  test('should render proxy block on homepage', async ({ page }) => {
    const blockTitle = `Homepage Proxy Block ${Date.now()}`;
    testBlocks.push(blockTitle);
    
    // Place proxy block
    await blockPlacementPage.navigate(ENVIRONMENT.theme);
    await blockPlacementPage.clickPlaceBlockForRegion('content');
    await blockPlacementPage.selectProxyBlock();
    
    await blockPlacementPage.configureBasicSettings({
      title: blockTitle,
      displayTitle: true,
    });
    
    await blockPlacementPage.configureProxySettings({
      targetBlock: 'system_powered_by_block',
    });
    
    await blockPlacementPage.saveBlock();
    
    // Logout and view frontend
    await logout(page);
    await frontendPage.navigateToHomepage();
    
    // Verify proxy block is rendered
    await frontendPage.verifyProxyBlockPresent(blockTitle);
    
    // Verify target block content is rendered
    await frontendPage.verifyProxyBlockContent('Powered by');
    
    await frontendPage.verifyNoPHPErrors();
    await checkForPHPErrors(page);
  });

  test('should render proxy block with different target blocks', async ({ page }) => {
    const configurations = PROXY_BLOCK_DATA.configurations.slice(0, 2); // Test first 2
    
    for (const config of configurations) {
      const blockTitle = `${config.name} ${Date.now()}`;
      testBlocks.push(blockTitle);
      
      // Place proxy block
      await blockPlacementPage.navigate(ENVIRONMENT.theme);
      await blockPlacementPage.clickPlaceBlockForRegion('content');
      await blockPlacementPage.selectProxyBlock();
      
      await blockPlacementPage.configureBasicSettings({
        title: blockTitle,
        displayTitle: true,
      });
      
      await blockPlacementPage.configureProxySettings({
        targetBlock: config.targetBlock,
      });
      
      await blockPlacementPage.saveBlock();
      
      // View on frontend
      await logout(page);
      await frontendPage.navigateToHomepage();
      
      // Verify proxy block renders
      await frontendPage.verifyProxyBlockPresent(blockTitle);
      
      // Verify expected content if specified
      if (config.expectedContent) {
        await frontendPage.verifyProxyBlockContent(config.expectedContent);
      }
      
      await frontendPage.verifyNoPHPErrors();
      
      // Re-login for next iteration
      await loginAsAdmin(page, TEST_DATA.admin);
    }
  });

  test('should render proxy block on content pages', async ({ page }) => {
    // Create a test node first
    const nodeData = await createTestNode(page, 'page', {
      title: 'Test Page for Proxy Block',
      body: 'This page is used to test proxy block rendering.',
    });
    
    const blockTitle = `Content Page Proxy Block ${Date.now()}`;
    testBlocks.push(blockTitle);
    
    // Place proxy block
    await blockPlacementPage.navigate(ENVIRONMENT.theme);
    await blockPlacementPage.clickPlaceBlockForRegion('content');
    await blockPlacementPage.selectProxyBlock();
    
    await blockPlacementPage.configureBasicSettings({
      title: blockTitle,
      displayTitle: true,
    });
    
    await blockPlacementPage.configureProxySettings({
      targetBlock: 'system_branding_block',
    });
    
    await blockPlacementPage.saveBlock();
    
    // View the test node
    await logout(page);
    await page.goto(nodeData.url);
    
    // Verify page loaded correctly
    await frontendPage.verifyPageLoads();
    await expect(page.locator('h1')).toContainText(nodeData.title);
    
    // Verify proxy block is present
    await frontendPage.verifyProxyBlockPresent(blockTitle);
    
    await frontendPage.verifyNoPHPErrors();
    await checkForPHPErrors(page);
  });

  test('should handle proxy block with hidden title', async ({ page }) => {
    const blockTitle = `Hidden Title Block ${Date.now()}`;
    testBlocks.push(blockTitle);
    
    // Place proxy block with hidden title
    await blockPlacementPage.navigate(ENVIRONMENT.theme);
    await blockPlacementPage.clickPlaceBlockForRegion('content');
    await blockPlacementPage.selectProxyBlock();
    
    await blockPlacementPage.configureBasicSettings({
      title: blockTitle,
      displayTitle: false, // Hide the title
    });
    
    await blockPlacementPage.configureProxySettings({
      targetBlock: 'system_powered_by_block',
    });
    
    await blockPlacementPage.saveBlock();
    
    // View on frontend
    await logout(page);
    await frontendPage.navigateToHomepage();
    
    // Verify proxy block is present but title is not displayed
    const proxyBlock = await frontendPage.verifyProxyBlockPresent(null); // Don't check for title
    
    // Verify the block title is not visible
    const blockTitleElement = proxyBlock.locator('h2, .block-title');
    if (await blockTitleElement.count() > 0) {
      await expect(blockTitleElement).not.toContainText(blockTitle);
    }
    
    // But content should still be rendered
    await frontendPage.verifyProxyBlockContent('Powered by');
    
    await frontendPage.verifyNoPHPErrors();
  });

  test('should render proxy block in different regions', async ({ page }) => {
    const regions = ['content', 'sidebar_first'];
    
    for (const region of regions) {
      // Check if region exists in theme
      await blockPlacementPage.navigate(ENVIRONMENT.theme);
      const regionRow = page.locator(`tr[data-region="${region}"]`);
      if (await regionRow.count() === 0) {
        console.log(`Region ${region} not available in ${ENVIRONMENT.theme} theme`);
        continue;
      }
      
      const blockTitle = `${region} Proxy Block ${Date.now()}`;
      testBlocks.push(blockTitle);
      
      // Place proxy block in the region
      await blockPlacementPage.clickPlaceBlockForRegion(region);
      await blockPlacementPage.selectProxyBlock();
      
      await blockPlacementPage.configureBasicSettings({
        title: blockTitle,
        region: region,
      });
      
      await blockPlacementPage.configureProxySettings({
        targetBlock: 'system_powered_by_block',
      });
      
      await blockPlacementPage.saveBlock();
      
      // View on frontend
      await logout(page);
      await frontendPage.navigateToHomepage();
      
      // Verify proxy block is present in the correct region
      await frontendPage.verifyProxyBlockPresent(blockTitle, region);
      
      await frontendPage.verifyNoPHPErrors();
      
      // Re-login for next iteration
      await loginAsAdmin(page, TEST_DATA.admin);
    }
  });

  test('should handle proxy block cache correctly', async ({ page }) => {
    const blockTitle = `Cache Test Block ${Date.now()}`;
    testBlocks.push(blockTitle);
    
    // Place proxy block
    await blockPlacementPage.navigate(ENVIRONMENT.theme);
    await blockPlacementPage.clickPlaceBlockForRegion('content');
    await blockPlacementPage.selectProxyBlock();
    
    await blockPlacementPage.configureBasicSettings({
      title: blockTitle,
    });
    
    await blockPlacementPage.configureProxySettings({
      targetBlock: 'system_powered_by_block',
    });
    
    await blockPlacementPage.saveBlock();
    
    // View on frontend multiple times to test caching
    await logout(page);
    
    for (let i = 0; i < 3; i++) {
      await frontendPage.navigateToHomepage();
      await frontendPage.verifyProxyBlockPresent(blockTitle);
      await frontendPage.verifyProxyBlockContent('Powered by');
      await frontendPage.verifyNoPHPErrors();
      
      // Wait a bit between requests
      await page.waitForTimeout(500);
    }
  });

  test('should handle responsive rendering', async ({ page }) => {
    const blockTitle = `Responsive Test Block ${Date.now()}`;
    testBlocks.push(blockTitle);
    
    // Place proxy block
    await blockPlacementPage.navigate(ENVIRONMENT.theme);
    await blockPlacementPage.clickPlaceBlockForRegion('content');
    await blockPlacementPage.selectProxyBlock();
    
    await blockPlacementPage.configureBasicSettings({
      title: blockTitle,
    });
    
    await blockPlacementPage.configureProxySettings({
      targetBlock: 'system_powered_by_block',
    });
    
    await blockPlacementPage.saveBlock();
    
    // Test different viewport sizes
    await logout(page);
    await frontendPage.navigateToHomepage();
    
    // Desktop view
    await page.setViewportSize({ width: 1200, height: 800 });
    await frontendPage.verifyProxyBlockPresent(blockTitle);
    
    // Mobile view
    await frontendPage.verifyResponsiveBehavior({ width: 375, height: 667 });
    await frontendPage.verifyProxyBlockPresent(blockTitle);
    
    await frontendPage.verifyNoPHPErrors();
  });

  test('should capture screenshots for visual verification', async ({ page }) => {
    const blockTitle = `Visual Test Block ${Date.now()}`;
    testBlocks.push(blockTitle);
    
    // Place proxy block
    await blockPlacementPage.navigate(ENVIRONMENT.theme);
    await blockPlacementPage.clickPlaceBlockForRegion('content');
    await blockPlacementPage.selectProxyBlock();
    
    await blockPlacementPage.configureBasicSettings({
      title: blockTitle,
    });
    
    await blockPlacementPage.configureProxySettings({
      targetBlock: 'system_branding_block',
    });
    
    await blockPlacementPage.saveBlock();
    
    // View on frontend and capture screenshot
    await logout(page);
    await frontendPage.navigateToHomepage();
    
    await frontendPage.verifyProxyBlockPresent(blockTitle);
    await frontendPage.takeScreenshot('proxy-block-homepage', {
      fullPage: true,
    });
    
    await frontendPage.verifyNoPHPErrors();
  });

  test('should verify proxy block accessibility basics', async ({ page }) => {
    const blockTitle = `Accessibility Test Block ${Date.now()}`;
    testBlocks.push(blockTitle);
    
    // Place proxy block
    await blockPlacementPage.navigate(ENVIRONMENT.theme);
    await blockPlacementPage.clickPlaceBlockForRegion('content');
    await blockPlacementPage.selectProxyBlock();
    
    await blockPlacementPage.configureBasicSettings({
      title: blockTitle,
    });
    
    await blockPlacementPage.configureProxySettings({
      targetBlock: 'system_powered_by_block',
    });
    
    await blockPlacementPage.saveBlock();
    
    // View on frontend
    await logout(page);
    await frontendPage.navigateToHomepage();
    
    // Verify basic accessibility
    await frontendPage.verifyBasicAccessibility();
    await frontendPage.verifyProxyBlockPresent(blockTitle);
    
    // Verify proxy block has proper structure
    const proxyBlock = page.locator('[data-block-plugin-id*="proxy_block"]');
    await expect(proxyBlock).toBeVisible();
    
    // Check for proper semantic structure
    const blockContent = proxyBlock.locator('.block-content');
    if (await blockContent.count() > 0) {
      await expect(blockContent).toBeVisible();
    }
    
    await frontendPage.verifyNoPHPErrors();
  });
});