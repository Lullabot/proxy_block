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
  execDrush,
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
 *
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

  // Login form MUST be available
  const loginForm = page.locator('#user-login-form');
  await expect(loginForm).toBeVisible();

  await page.fill('#edit-name', 'admin');
  await page.fill('#edit-pass', 'admin');
  await page.click('#edit-submit');
  await page.waitForLoadState('networkidle');
}

/**
 * Helper function to create a test node.
 *
 * @param {Object} page - The Playwright page object
 * @param {string} contentType - The content type to create
 * @param {Object} nodeData - Additional node data
 */
async function createTestNode(page, contentType = 'page', nodeData = {}) {
  const title = nodeData.title || `Test ${contentType} ${Date.now()}`;
  const body = nodeData.body || `Test content for ${title}`;

  try {
    // First try to create via Drush (faster and more reliable)
    await execDrush(
      `devel:generate-content --types=${contentType} --num=1 --kill`,
    );

    // Get the latest node via Drush
    const result = await execDrush(
      `sql:query "SELECT nid FROM node WHERE type='${contentType}' ORDER BY nid DESC LIMIT 1"`,
    );

    if (result.trim()) {
      const nodeId = result.trim().split('\n').pop();
      return {
        title: `Generated ${contentType}`,
        body: `Generated content`,
        url: `/node/${nodeId}`,
        nodeId: parseInt(nodeId, 10),
      };
    }
  } catch (error) {
    console.log(
      `Drush content generation failed: ${error.message}, falling back to UI creation`,
    );
  }

  // Fallback to UI creation if Drush fails
  await page.goto(`/node/add/${contentType}`);

  // Verify we're on the correct page
  await expect(page.locator('h1')).toContainText('Create');

  // Fill title - handle both field widget formats
  const titleField = page.locator('#edit-title-0-value, #edit-title');
  await expect(titleField).toBeVisible();
  await titleField.fill(title);

  // Fill body if it exists - handle different field formats
  const bodySelectors = [
    '#edit-body-0-value',
    '#edit-body',
    '[name="body[0][value]"]',
  ];

  for (const selector of bodySelectors) {
    const bodyField = page.locator(selector);
    if (await bodyField.isVisible()) {
      await bodyField.fill(body);
      break;
    }
  }

  // Save the node
  await page.click('#edit-submit');

  // Wait for node to be created - handle different success patterns
  try {
    await expect(page.locator('h1')).toContainText(title, { timeout: 10000 });
  } catch (error) {
    // Alternative: check for success message if title doesn't match
    await expect(page.locator('.messages--status')).toBeVisible();
  }

  return { title, body, url: page.url() };
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

    await frontendPage.verifyNoPHPErrors();
  });

  test('should render proxy block with different target blocks', async ({
    page,
  }) => {
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
  });

  test('should render proxy block on content pages', async ({ page }) => {
    // Create a test node first using Drush
    await execDrush('devel:generate-content --types=page --num=1');

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
    const nodeLink = page
      .locator('table tbody tr')
      .first()
      .locator('a')
      .first();
    const nodeUrl = await nodeLink.getAttribute('href');

    // View the test node
    await page.goto('/user/logout');
    await page.goto(nodeUrl);

    // Verify page loaded correctly
    await frontendPage.verifyPageLoads();

    // Verify proxy block is present
    await frontendPage.verifyProxyBlockPresent(blockTitle);

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
    if ((await blockTitleElement.count()) > 0) {
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
      if ((await regionRow.count()) === 0) {
        console.log(
          `Region ${region} not available in ${ENVIRONMENT.theme} theme`,
        );
        continue;
      }

      const blockTitle = `${region} Proxy Block ${Date.now()}`;
      testBlocks.push(blockTitle);

      // Place proxy block in the region
      await blockPlacementPage.clickPlaceBlockForRegion(region);
      await blockPlacementPage.selectProxyBlock();

      await blockPlacementPage.configureBasicSettings({
        title: blockTitle,
        region,
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

      // Wait for network to stabilize between requests instead of arbitrary timeout
      await page.waitForLoadState('networkidle');
    }
  });
});
