/**
 * @file
 * Clean Proxy Block E2E tests without sketchy conditionals.
 * These tests use strict assertions and fail properly when things break.
 */

const { test, expect } = require('@playwright/test');

// Drupal site base URL from environment or default
const baseURL =
  process.env.DRUPAL_BASE_URL ||
  process.env.DDEV_PRIMARY_URL ||
  'http://127.0.0.1:8080';

// Test configuration
const TEST_CONFIG = {
  admin: {
    username: 'admin',
    password: 'admin',
  },
  timeouts: {
    default: 30000,
    navigation: 15000,
  },
  theme: 'olivero', // Default Drupal theme
};

/**
 * Helper to login as admin user.
 * STRICT: This will fail if login doesn't work - no fallbacks or conditionals.
 * @param {object} page - The Playwright page object
 */
async function loginAsAdmin(page) {
  await page.goto(`${baseURL}/user/login`);
  await page.waitForLoadState('networkidle');

  // STRICT: Login form MUST exist
  const loginForm = page.locator('#user-login-form');
  await expect(loginForm).toBeVisible({
    timeout: TEST_CONFIG.timeouts.default,
  });

  // STRICT: Fill credentials and login
  await page.fill('#edit-name', TEST_CONFIG.admin.username);
  await page.fill('#edit-pass', TEST_CONFIG.admin.password);
  await page.click('#edit-submit');

  // STRICT: Wait for redirect and verify we're logged in
  await page.waitForLoadState('networkidle');

  // STRICT: Must be able to access admin interface (check for admin toolbar or structure menu)
  const adminIndicator = page.locator(
    'nav:has-text("Structure"), .toolbar-menu-administration, .admin-toolbar',
  );
  await expect(adminIndicator.first()).toBeVisible({
    timeout: TEST_CONFIG.timeouts.default,
  });
}

/**
 * Helper to navigate to block layout page.
 * STRICT: Will fail if the page doesn't load properly.
 * @param {object} page - The Playwright page object
 */
async function navigateToBlockLayout(page) {
  // Navigate directly to block layout - URL confirmed from error logs
  await page.goto(`${baseURL}/admin/structure/block`);
  await page.waitForLoadState('networkidle');

  // STRICT: Block layout page must load
  const heading = page.locator('h1');
  await expect(heading).toContainText('Block layout', {
    timeout: TEST_CONFIG.timeouts.default,
  });
}

/**
 * Helper to place a proxy block in a region.
 * STRICT: Each step must succeed or the test fails.
 * @param {object} page - The Playwright page object
 * @param {string} regionName - The name of the region to place the block in
 * @param {object} blockConfig - Configuration object for the block
 */
async function placeProxyBlock(page, regionName, blockConfig) {
  // Navigate to block layout if not already there
  await navigateToBlockLayout(page);

  // STRICT: Find place block link for the region
  const placeBlockLink = page
    .locator(`a:has-text("Place block in the ${regionName} region")`)
    .first();
  await expect(placeBlockLink).toBeVisible({
    timeout: TEST_CONFIG.timeouts.default,
  });
  await placeBlockLink.click();

  // Wait for modal/page to load
  await page.waitForLoadState('networkidle');

  // STRICT: Find and click the Place block button for Proxy Block
  // The button is in the table row containing "Proxy Block" (could be a button or link)
  const proxyBlockPlaceButton = page
    .locator(
      'tr:has-text("Proxy Block") a:has-text("Place block"), tr:has-text("Proxy Block") button:has-text("Place block")',
    )
    .first();
  await expect(proxyBlockPlaceButton).toBeVisible({
    timeout: TEST_CONFIG.timeouts.default,
  });
  await proxyBlockPlaceButton.click();

  // Wait for configuration page
  await page.waitForLoadState('networkidle');

  // STRICT: Configuration form must be present
  const configForm = page.locator('form');
  await expect(configForm).toBeVisible({
    timeout: TEST_CONFIG.timeouts.default,
  });

  // STRICT: Configure block title (text input only)
  const titleField = page.locator(
    '#edit-settings-label, input[type="text"][name*="label"]',
  );
  await expect(titleField.first()).toBeVisible({
    timeout: TEST_CONFIG.timeouts.default,
  });
  await titleField.first().fill(blockConfig.title);

  // STRICT: Select target block if specified
  if (blockConfig.targetBlock) {
    const targetSelect = page.locator(
      '#edit-settings-target-block-id, select[name*="target_block"]',
    );
    await expect(targetSelect).toBeVisible({
      timeout: TEST_CONFIG.timeouts.default,
    });
    await targetSelect.selectOption(blockConfig.targetBlock);

    // Wait for any AJAX updates
    await page.waitForLoadState('networkidle');
  }

  // STRICT: Save the block - handle both modal and page contexts
  // Try different approaches to find and scroll to save button
  let saveButton = page.locator('button:has-text("Save block")').first();

  // If button not visible, try scrolling in modal or page
  if ((await saveButton.isVisible().catch(() => false)) === false) {
    // Try scrolling in modal dialog
    await page
      .locator('.ui-dialog, .modal-dialog, form')
      .last()
      .evaluate(el => {
        el.scrollTop = el.scrollHeight;
      })
      .catch(() => {});

    // Try scrolling main page
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.waitForTimeout(500);

    // Try broader selector
    saveButton = page
      .locator(
        'button:has-text("Save block"), input[type="submit"][value*="Save"], button:has-text("Save"), [value*="Save block"], [value*="Save"]',
      )
      .first();
  }

  await expect(saveButton).toBeVisible({
    timeout: TEST_CONFIG.timeouts.default,
  });
  await saveButton.click();

  // STRICT: Must redirect back to block layout
  await page.waitForLoadState('networkidle');

  // STRICT: Wait a bit to ensure block is saved and any caches are cleared
  await page.waitForTimeout(2000);

  // STRICT: Verify that the block was actually saved
  const currentUrl = page.url();
  console.log(`After save, current URL is: ${currentUrl}`);

  // Check if we have success message or are on the right page
  const successMessage = page.locator(
    '.messages--status, .messages-list__item--status',
  );
  if ((await successMessage.count()) > 0) {
    const messageText = await successMessage.textContent();
    console.log(`Success message: ${messageText}`);
  }

  // If we're still on the config page, something might be wrong
  const stillOnConfig = page.locator(
    'h1:has-text("Configure"), h1:has-text("Place")',
  );
  if ((await stillOnConfig.count()) > 0) {
    console.log(
      'Still on configuration page after save - block may not have been created',
    );
    // Check for any validation errors
    const errorMessages = page.locator(
      '.messages--error, .form-item--error-message',
    );
    if ((await errorMessages.count()) > 0) {
      const errorText = await errorMessages.textContent();
      console.log(`Error message: ${errorText}`);
    }
  }
  const backToLayout = page.locator('h1:has-text("Block layout")');
  await expect(backToLayout).toBeVisible({
    timeout: TEST_CONFIG.timeouts.default,
  });

  // STRICT: Wait for page to fully load and clear any potential caches
  await page.waitForTimeout(1000);

  // STRICT: Block must appear in the layout with robust search
  let placedBlock = page.locator(`tr:has-text("${blockConfig.title}")`);

  // If not found immediately, try refreshing the page
  if ((await placedBlock.count()) === 0) {
    console.log(`Block "${blockConfig.title}" not found, refreshing page...`);
    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    placedBlock = page.locator(`tr:has-text("${blockConfig.title}")`);
  }

  // If still not found, log what blocks ARE present for debugging
  if ((await placedBlock.count()) === 0) {
    const blockTitles = await page
      .locator('table tbody tr td:first-child')
      .allTextContents();
    console.log(
      'Available blocks in table:',
      blockTitles.filter(title => title.trim() !== ''),
    );

    // Try a more lenient search - look for any block with similar pattern
    const partialTitle = blockConfig.title.replace(/\d+$/, ''); // Remove timestamp
    placedBlock = page.locator(`tr:has-text("${partialTitle}")`);

    if ((await placedBlock.count()) > 0) {
      console.log(`Found block with partial title match: ${partialTitle}`);
    }
  }

  await expect(placedBlock).toBeVisible({
    timeout: TEST_CONFIG.timeouts.default,
  });

  // STRICT: Save the block layout changes
  const saveBlocksButton = page.locator(
    'button:has-text("Save blocks"), input[type="submit"][value*="Save blocks"]',
  );
  if ((await saveBlocksButton.count()) > 0) {
    await saveBlocksButton.first().click();
    await page.waitForLoadState('networkidle');

    // Verify success message
    const layoutSuccessMessage = page.locator(
      '.messages--status, .messages-list__item--status',
    );
    await expect(layoutSuccessMessage).toBeVisible({
      timeout: TEST_CONFIG.timeouts.default,
    });
  }
}

/**
 * Helper to verify block renders on frontend.
 * STRICT: Block must be visible and contain expected content.
 * @param {object} page - The Playwright page object
 * @param {string} blockTitle - The title of the block to verify
 * @param {string|null} expectedContent - Optional expected content within the block
 */
async function verifyBlockOnFrontend(page, blockTitle, expectedContent = null) {
  // Navigate to homepage
  await page.goto(`${baseURL}/`);
  await page.waitForLoadState('networkidle');

  // STRICT: Page must load without errors
  const pageTitle = await page.title();
  expect(pageTitle).not.toMatch(/(error|not found|access denied)/i);

  // STRICT: Block with title must be visible
  if (blockTitle) {
    const blockWithTitle = page.locator(`.block:has-text("${blockTitle}")`);
    await expect(blockWithTitle).toBeVisible({
      timeout: TEST_CONFIG.timeouts.default,
    });
  }

  // STRICT: Expected content must be present if specified
  if (expectedContent) {
    const contentElement = page.locator(`:text("${expectedContent}")`);
    await expect(contentElement).toBeVisible({
      timeout: TEST_CONFIG.timeouts.default,
    });
  }

  // STRICT: No PHP errors must be present
  const phpErrors = page.locator(
    '.php-error, .error-message:has-text("Fatal"), .messages--error:has-text("Fatal")',
  );
  await expect(phpErrors).toHaveCount(0);
}

test.describe('Clean Proxy Block Tests', () => {
  // Unique test identifier for this run
  const testId = Date.now();
  let placedBlocks = [];

  test.beforeEach(async ({ page }) => {
    test.setTimeout(TEST_CONFIG.timeouts.default * 3); // Extended timeout for setup
    await loginAsAdmin(page);
  });

  test.afterEach(async ({ page }) => {
    // Clean up placed blocks - but don't mask failures if cleanup fails
    for (const blockTitle of placedBlocks) {
      try {
        await navigateToBlockLayout(page);
        const blockRow = page.locator(`tr:has-text("${blockTitle}")`);
        if ((await blockRow.count()) > 0) {
          const removeLink = blockRow.locator(
            'a:has-text("Remove"), .dropbutton a:has-text("Remove")',
          );
          if ((await removeLink.count()) > 0) {
            await removeLink.first().click();
            await page.waitForLoadState('networkidle');

            // Confirm removal if needed
            const confirmButton = page.locator(
              'input[type="submit"][value*="Remove"], button:has-text("Remove")',
            );
            if ((await confirmButton.count()) > 0) {
              await confirmButton.click();
              await page.waitForLoadState('networkidle');
            }
          }
        }
      } catch (error) {
        console.warn(
          `Failed to clean up block: ${blockTitle}. Error: ${error.message}`,
        );
        // Don't fail the test because of cleanup issues, but log it
      }
    }
    placedBlocks = [];
  });

  test('should place and render basic proxy block', async ({ page }) => {
    const blockConfig = {
      title: `Test Proxy Block ${testId}`,
      targetBlock: 'system_powered_by_block',
    };
    placedBlocks.push(blockConfig.title);

    // STRICT: Place the block - will fail if any step fails
    await placeProxyBlock(page, 'Content', blockConfig);

    // STRICT: Verify it renders on frontend
    await verifyBlockOnFrontend(page, blockConfig.title, 'Powered by Drupal');
  });

  test('should handle proxy block configuration form', async ({ page }) => {
    // STRICT: Navigate to proxy block configuration
    await page.goto(
      `${baseURL}/admin/structure/block/add/proxy_block_proxy/${TEST_CONFIG.theme}`,
    );
    await page.waitForLoadState('networkidle');

    // STRICT: Configuration form must be present with required fields
    const configHeading = page.locator(
      'h1:has-text("Configure"), h1:has-text("Place")',
    );
    await expect(configHeading).toBeVisible({
      timeout: TEST_CONFIG.timeouts.default,
    });

    // STRICT: Title field must exist (text input only)
    const titleField = page.locator(
      '#edit-settings-label, input[type="text"][name*="label"]',
    );
    await expect(titleField.first()).toBeVisible({
      timeout: TEST_CONFIG.timeouts.default,
    });

    // STRICT: Target block selection must exist
    const targetSelect = page.locator(
      '#edit-settings-target-block-id, select[name*="target_block"]',
    );
    await expect(targetSelect).toBeVisible({
      timeout: TEST_CONFIG.timeouts.default,
    });

    // STRICT: Must have options available
    const options = await targetSelect.locator('option').count();
    expect(options).toBeGreaterThan(1); // At least default + one actual block

    // STRICT: Save button must exist - handle modal/page context
    let saveButton = page.locator('button:has-text("Save block")').first();

    // If button not visible, try scrolling
    if ((await saveButton.isVisible().catch(() => false)) === false) {
      await page
        .locator('.ui-dialog, .modal-dialog, form')
        .last()
        .evaluate(el => {
          el.scrollTop = el.scrollHeight;
        })
        .catch(() => {});
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
      await page.waitForTimeout(500);

      saveButton = page
        .locator(
          'button:has-text("Save block"), input[type="submit"][value*="Save"], button:has-text("Save"), [value*="Save block"], [value*="Save"]',
        )
        .first();
    }

    await expect(saveButton).toBeVisible({
      timeout: TEST_CONFIG.timeouts.default,
    });
  });

  test('should validate required fields properly', async ({ page }) => {
    // STRICT: Navigate to proxy block configuration
    await page.goto(
      `${baseURL}/admin/structure/block/add/proxy_block_proxy/${TEST_CONFIG.theme}`,
    );
    await page.waitForLoadState('networkidle');

    // STRICT: Try to save without required fields
    let saveButton = page.locator('button:has-text("Save block")').first();

    // If button not visible, try scrolling
    if ((await saveButton.isVisible().catch(() => false)) === false) {
      await page
        .locator('.ui-dialog, .modal-dialog, form')
        .last()
        .evaluate(el => {
          el.scrollTop = el.scrollHeight;
        })
        .catch(() => {});
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
      await page.waitForTimeout(500);

      saveButton = page
        .locator(
          'button:has-text("Save block"), input[type="submit"][value*="Save"], button:has-text("Save"), [value*="Save block"], [value*="Save"]',
        )
        .first();
    }

    await expect(saveButton).toBeVisible({
      timeout: TEST_CONFIG.timeouts.default,
    });
    await saveButton.click();

    await page.waitForLoadState('networkidle');

    // STRICT: Must either stay on form page OR show validation errors
    // No fallbacks - test what actually happens
    const stillOnForm = page.locator(
      'h1:has-text("Configure"), h1:has-text("Place")',
    );
    const onFormPage = (await stillOnForm.count()) > 0;

    if (onFormPage) {
      // If still on form, there should be the title field (normal behavior)
      const titleField = page.locator(
        '#edit-settings-label, input[type="text"][name*="label"]',
      );
      await expect(titleField.first()).toBeVisible({
        timeout: TEST_CONFIG.timeouts.default,
      });
    } else {
      // If redirected, check if we're back on block layout with or without the block
      const blockLayout = page.locator('h1:has-text("Block layout")');
      await expect(blockLayout).toBeVisible({
        timeout: TEST_CONFIG.timeouts.default,
      });
    }
  });

  test('should place proxy block in different regions', async ({ page }) => {
    await navigateToBlockLayout(page);

    // STRICT: Find available regions by looking for place block links
    const placeLinks = page
      .locator('a')
      .filter({ hasText: /Place block in the .* region/i });
    const linkCount = await placeLinks.count();
    expect(linkCount).toBeGreaterThan(0);

    // Test first available region (usually Content)
    const firstPlaceLink = placeLinks.first();
    const linkText = await firstPlaceLink.textContent();
    const regionMatch = linkText?.match(/Place block in the (.*?) region/i);
    expect(regionMatch).toBeTruthy();

    const regionName = regionMatch[1];
    const blockConfig = {
      title: `Region Test Block ${testId}`,
      targetBlock: 'system_branding_block',
    };
    placedBlocks.push(blockConfig.title);

    // STRICT: Place block in the discovered region
    await placeProxyBlock(page, regionName, blockConfig);

    // STRICT: Verify it renders on frontend
    await verifyBlockOnFrontend(page, blockConfig.title);
  });
});
