/**
 * @file
 * Proxy Block rendering tests for E2E testing.
 * Tests proxy-specific rendering functionality including target block
 * content rendering, cache handling, and context passing.
 */

const { test, expect } = require('@playwright/test');
const {
  createAdminUser,
  enableModule,
  clearCache,
  execDrushInTestSite,
} = require('../utils/drush-helper');
const { BlockPlacementPage } = require('../page-objects/block-placement-page');
const { FrontendPage } = require('../page-objects/frontend-page');
const {
  TIMEOUTS,
  ENVIRONMENT,
  PROXY_BLOCK_DATA,
} = require('../utils/constants');

/**
 * Helper function to create admin user and login.
 * @param {Object} page - The Playwright page object
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

test.describe('Proxy Block Rendering', () => {
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

  test('should render target block content through proxy block', async ({
    page,
  }) => {
    const blockTitle = `Proxy Render Test ${Date.now()}`;
    testBlocks.push(blockTitle);

    // Place proxy block with target
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

    // Logout and view frontend to test proxy rendering
    await page.goto('/user/logout');
    await frontendPage.navigateToHomepage();

    // Verify proxy block is rendered with its title
    await frontendPage.verifyProxyBlockPresent(blockTitle);

    // Verify target block content is rendered through proxy
    await frontendPage.verifyProxyBlockContent('Powered by');

    // Verify no rendering errors
    await frontendPage.verifyNoPHPErrors();
  });

  test('should render different target blocks correctly through proxy', async ({
    page,
  }) => {
    const configurations = PROXY_BLOCK_DATA.configurations.slice(0, 2); // Test first 2

    for (const config of configurations) {
      const blockTitle = `${config.name} Render ${Date.now()}`;
      testBlocks.push(blockTitle);

      // Place proxy block with specific target
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

      // View on frontend to test target-specific rendering
      await page.goto('/user/logout');
      await frontendPage.navigateToHomepage();

      // Verify proxy block renders the specific target content
      await frontendPage.verifyProxyBlockPresent(blockTitle);

      // Verify expected content if specified for this target
      if (config.expectedContent) {
        await frontendPage.verifyProxyBlockContent(config.expectedContent);
      }

      await frontendPage.verifyNoPHPErrors();

      // Re-login for next iteration
      await setupAdminUser(page);
    }
  });

  test('should handle proxy block title display settings', async ({ page }) => {
    const blockTitleVisible = `Visible Title Proxy ${Date.now()}`;
    const blockTitleHidden = `Hidden Title Proxy ${Date.now()}`;
    testBlocks.push(blockTitleVisible, blockTitleHidden);

    // Test proxy block with visible title
    await blockPlacementPage.navigate(ENVIRONMENT.theme);
    await blockPlacementPage.clickPlaceBlockForRegion('content');
    await blockPlacementPage.selectProxyBlock();

    await blockPlacementPage.configureBasicSettings({
      title: blockTitleVisible,
      displayTitle: true,
    });

    await blockPlacementPage.configureProxySettings({
      targetBlock: 'system_powered_by_block',
    });

    await blockPlacementPage.saveBlock();

    // Test proxy block with hidden title
    await blockPlacementPage.clickPlaceBlockForRegion('content');
    await blockPlacementPage.selectProxyBlock();

    await blockPlacementPage.configureBasicSettings({
      title: blockTitleHidden,
      displayTitle: false, // Hide the title
    });

    await blockPlacementPage.configureProxySettings({
      targetBlock: 'system_powered_by_block',
    });

    await blockPlacementPage.saveBlock();

    // View on frontend to test title display behavior
    await page.goto('/user/logout');
    await frontendPage.navigateToHomepage();

    // Verify visible title proxy block shows title
    await frontendPage.verifyProxyBlockPresent(blockTitleVisible);

    // Verify hidden title proxy block doesn't show title but still renders content
    const hiddenTitleBlock = await frontendPage.verifyProxyBlockPresent(null); // Don't check for title

    // Verify the hidden title block doesn't display its title
    const blockTitleElement = hiddenTitleBlock.locator('h2, .block-title');
    if ((await blockTitleElement.count()) > 0) {
      await expect(blockTitleElement).not.toContainText(blockTitleHidden);
    }

    // But target content should still be rendered in both
    await frontendPage.verifyProxyBlockContent('Powered by');

    await frontendPage.verifyNoPHPErrors();
  });

  test('should handle proxy block cache correctly', async ({ page }) => {
    const blockTitle = `Cache Test Proxy ${Date.now()}`;
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

    // Test proxy rendering with caching across multiple requests
    await page.goto('/user/logout');

    for (let i = 0; i < 3; i++) {
      await frontendPage.navigateToHomepage();

      // Verify proxy block renders consistently across cache hits/misses
      await frontendPage.verifyProxyBlockPresent(blockTitle);
      await frontendPage.verifyProxyBlockContent('Powered by');
      await frontendPage.verifyNoPHPErrors();

      // Wait for network to stabilize between requests
      await page.waitForLoadState('networkidle');
    }
  });

  test('should pass contexts correctly from proxy to target block', async ({
    page,
  }) => {
    const blockTitle = `Context Proxy Test ${Date.now()}`;
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

    // Test on frontend - proxy should pass contexts to target
    await page.goto('/user/logout');
    await frontendPage.navigateToHomepage();

    // Verify proxy block renders target content (indicating contexts passed correctly)
    await frontendPage.verifyProxyBlockPresent(blockTitle);
    await frontendPage.verifyProxyBlockContent('Powered by');

    // Verify no context-related errors in proxy rendering
    const contextErrors = page.locator(
      '.error:has-text("context"), .error:has-text("Context")',
    );
    await expect(contextErrors).toHaveCount(0);

    await frontendPage.verifyNoPHPErrors();
  });

  test('should handle proxy block permissions correctly', async ({ page }) => {
    const blockTitle = `Permission Proxy Test ${Date.now()}`;
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

    // Test as anonymous user (proxy should respect target block permissions)
    await page.goto('/user/logout');
    await frontendPage.navigateToHomepage();

    // Verify proxy block is visible to anonymous users (system_powered_by_block is public)
    await frontendPage.verifyProxyBlockPresent(blockTitle);
    await frontendPage.verifyProxyBlockContent('Powered by');

    // Verify no permission errors
    const permissionErrors = page.locator(
      '.error:has-text("permission"), .error:has-text("access")',
    );
    await expect(permissionErrors).toHaveCount(0);

    await frontendPage.verifyNoPHPErrors();
  });

  test('should handle proxy block rendering errors gracefully', async ({
    page,
  }) => {
    const blockTitle = `Error Handling Proxy ${Date.now()}`;
    testBlocks.push(blockTitle);

    // Place proxy block with potentially problematic target
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

    // Test frontend rendering - should handle any errors gracefully
    await page.goto('/user/logout');
    await frontendPage.navigateToHomepage();

    // Page should load without fatal errors
    await frontendPage.verifyPageLoads();

    // Verify no PHP fatal errors in proxy rendering
    const fatalErrors = page.locator(
      '.php-error, .error-message:has-text("Fatal")',
    );
    await expect(fatalErrors).toHaveCount(0);

    // Proxy should either render successfully or fail gracefully
    const proxyBlock = page.locator(`h2:has-text("${blockTitle}")`);
    const blockExists = (await proxyBlock.count()) > 0;

    if (blockExists) {
      // If block renders, verify it has content
      await frontendPage.verifyProxyBlockPresent(blockTitle);
      await frontendPage.verifyProxyBlockContent('Powered by');
    }
    // If block doesn't render, that's acceptable as long as no fatal errors occurred

    await frontendPage.verifyNoPHPErrors();
  });
});
