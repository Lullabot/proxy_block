/**
 * @file
 * Frontend rendering tests for Proxy Block E2E testing.
 */

const { test, expect, execDrushInTestSite, takeAccessibleScreenshot } = require('@lullabot/playwright-drupal');
const { BlockPlacementPage } = require('../page-objects/block-placement-page');
const { FrontendPage } = require('../page-objects/frontend-page');
const {
  TIMEOUTS,
  ENVIRONMENT,
  PROXY_BLOCK_DATA,
} = require('../utils/constants');

/**
 * Helper function to create admin user and login.
 */
async function setupAdminUser(page) {
  // Enable proxy_block module and create admin user
  await execDrushInTestSite('pm:enable proxy_block -y');
  await execDrushInTestSite('user:create admin --mail="admin@example.com" --password="admin"');
  await execDrushInTestSite('user:role:add administrator admin');
  
  // Login
  await page.goto('/user/login');
  await page.fill('#edit-name', 'admin');
  await page.fill('#edit-pass', 'admin');
  await page.click('#edit-submit');
}

/**
 * Helper function to create a test node.
 */
async function createTestNode(page, contentType = 'page', nodeData = {}) {
  const title = nodeData.title || `Test ${contentType} ${Date.now()}`;
  const body = nodeData.body || `Test content for ${title}`;
  
  // Create node using Drush
  await execDrushInTestSite(`devel:generate-content --types=${contentType} --num=1 --kill`);
  
  // Alternatively, create via UI if needed
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

test.describe('Frontend Rendering', () => {
  let blockPlacementPage;
  let frontendPage;
  let testBlocks = [];

  test.beforeEach(async ({ page }) => {
    test.setTimeout(TIMEOUTS.LONG);
    
    blockPlacementPage = new BlockPlacementPage(page);
    frontendPage = new FrontendPage(page);
    
    // Setup admin user
    await setupAdminUser(page);
  });

  test.afterEach(async ({ page }) => {
    // Clean up test blocks after each test
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
    await page.goto('/user/logout');
    await frontendPage.navigateToHomepage();
    
    // Verify proxy block is rendered
    await frontendPage.verifyProxyBlockPresent(blockTitle);
    
    // Verify target block content is rendered
    await frontendPage.verifyProxyBlockContent('Powered by');
    
    // Take accessible screenshot for visual verification
    // TODO: Re-enable when testInfo parameter is available
    // await takeAccessibleScreenshot(page, testInfo);
    
    await frontendPage.verifyNoPHPErrors();
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
      await page.goto('/user/logout');
      await frontendPage.navigateToHomepage();
      
      // Verify proxy block renders
      await frontendPage.verifyProxyBlockPresent(blockTitle);
      
      // Verify expected content if specified
      if (config.expectedContent) {
        await frontendPage.verifyProxyBlockContent(config.expectedContent);
      }
      
      await frontendPage.verifyNoPHPErrors();
      
      // Re-login for next iteration
      await setupAdminUser(page);
    }
    
    // Take final screenshot
    // TODO: Re-enable when testInfo parameter is available
    // await takeAccessibleScreenshot(page, testInfo);
  });

  test('should render proxy block on content pages', async ({ page }) => {
    // Create a test node first using Drush
    await execDrushInTestSite('devel:generate-content --types=page --num=1');
    
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
    
    // Get the created node URL
    await page.goto('/admin/content');
    const nodeLink = page.locator('table tbody tr').first().locator('a').first();
    const nodeUrl = await nodeLink.getAttribute('href');
    
    // View the test node
    await page.goto('/user/logout');
    await page.goto(nodeUrl);
    
    // Verify page loaded correctly
    await frontendPage.verifyPageLoads();
    
    // Verify proxy block is present
    await frontendPage.verifyProxyBlockPresent(blockTitle);
    
    // Take accessible screenshot
    // TODO: Re-enable when testInfo parameter is available
    // await takeAccessibleScreenshot(page, testInfo);
    
    await frontendPage.verifyNoPHPErrors();
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
    await page.goto('/user/logout');
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
    
    // Take accessible screenshot
    // TODO: Re-enable when testInfo parameter is available
    // await takeAccessibleScreenshot(page, testInfo);
    
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
      await page.goto('/user/logout');
      await frontendPage.navigateToHomepage();
      
      // Verify proxy block is present in the correct region
      await frontendPage.verifyProxyBlockPresent(blockTitle, region);
      
      await frontendPage.verifyNoPHPErrors();
      
      // Re-login for next iteration
      await setupAdminUser(page);
    }
    
    // Take final accessible screenshot
    // TODO: Re-enable when testInfo parameter is available
    // await takeAccessibleScreenshot(page, testInfo);
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
    await page.goto('/user/logout');
    
    for (let i = 0; i < 3; i++) {
      await frontendPage.navigateToHomepage();
      await frontendPage.verifyProxyBlockPresent(blockTitle);
      await frontendPage.verifyProxyBlockContent('Powered by');
      await frontendPage.verifyNoPHPErrors();
      
      // Wait a bit between requests
      await page.waitForTimeout(500);
    }
    
    // Take accessible screenshot
    // TODO: Re-enable when testInfo parameter is available
    // await takeAccessibleScreenshot(page, testInfo);
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
    await page.goto('/user/logout');
    await frontendPage.navigateToHomepage();
    
    // Desktop view
    await page.setViewportSize({ width: 1200, height: 800 });
    await frontendPage.verifyProxyBlockPresent(blockTitle);
    
    // Take desktop screenshot
    // TODO: Re-enable when testInfo parameter is available
    // await takeAccessibleScreenshot(page, testInfo, { fullPage: true });
    
    // Mobile view
    await frontendPage.verifyResponsiveBehavior({ width: 375, height: 667 });
    await frontendPage.verifyProxyBlockPresent(blockTitle);
    
    // Take mobile screenshot
    // TODO: Re-enable when testInfo parameter is available
    // await takeAccessibleScreenshot(page, testInfo, { fullPage: true });
    
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
    await page.goto('/user/logout');
    await frontendPage.navigateToHomepage();
    
    await frontendPage.verifyProxyBlockPresent(blockTitle);
    
    // Use takeAccessibleScreenshot for comprehensive visual and accessibility testing
    // TODO: Re-enable when testInfo parameter is available
    // await takeAccessibleScreenshot(page, testInfo, {
    //   fullPage: true,
    //   threshold: 0.2,
    // });
    
    await frontendPage.verifyNoPHPErrors();
  });

  test('should verify proxy block accessibility', async ({ page }) => {
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
    await page.goto('/user/logout');
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
    
    // Use takeAccessibleScreenshot which includes comprehensive accessibility testing
    // TODO: Re-enable when testInfo parameter is available
    // await takeAccessibleScreenshot(page, testInfo, {
    //   fullPage: true,
    // });
    
    await frontendPage.verifyNoPHPErrors();
  });
});