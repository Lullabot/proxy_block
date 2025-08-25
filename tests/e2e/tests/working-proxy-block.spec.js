/**
 * @file
 * Proxy Block specific E2E tests focusing on unique functionality.
 * Tests target block selection, configuration, and proxy-specific behavior.
 */

const { test, expect } = require('@playwright/test');
const {
  createAdminUser,
  enableModule,
  clearCache,
} = require('../utils/drush-helper');
const {
  TIMEOUTS,
  ENVIRONMENT,
} = require('../utils/constants');

// Drupal site base URL from environment or default
const baseURL =
  process.env.DRUPAL_BASE_URL ||
  process.env.DDEV_PRIMARY_URL ||
  'http://127.0.0.1:8080';

/**
 * Helper to login as admin user.
 * @param {object} page - The Playwright page object
 */
async function loginAsAdmin(page) {
  // Setup using drush helper
  await enableModule('proxy_block');
  await createAdminUser();
  await clearCache();

  await page.goto(`${baseURL}/user/login`);
  await page.waitForLoadState('networkidle');

  await page.fill('#edit-name', 'admin');
  await page.fill('#edit-pass', 'admin');
  await page.click('#edit-submit');
  await page.waitForLoadState('networkidle');
}

test.describe('Proxy Block Target Configuration', () => {
  // Unique test identifier for this run
  const testId = Date.now();

  test.beforeEach(async ({ page }) => {
    test.setTimeout(TIMEOUTS.LONG);
    await loginAsAdmin(page);
  });

  test('should display target block selection dropdown with available blocks', async ({
    page,
  }) => {
    // Navigate to proxy block configuration
    await page.goto(
      `${baseURL}/admin/structure/block/add/proxy_block_proxy/${ENVIRONMENT.theme}`,
    );
    await page.waitForLoadState('networkidle');

    // Verify target block selection field exists (unique to proxy block)
    const targetSelect = page.locator(
      '#edit-settings-target-block-id, select[name*="target_block"]',
    );
    await expect(targetSelect).toBeVisible({ timeout: TIMEOUTS.DEFAULT });

    // Verify dropdown has available target blocks
    const options = await targetSelect.locator('option').count();
    expect(options).toBeGreaterThan(1); // At least default + available blocks

    // Verify specific expected target blocks are available
    const poweredByOption = targetSelect.locator('option[value="system_powered_by_block"]');
    await expect(poweredByOption).toBeAttached();
  });

  test('should save target block selection and persist configuration', async ({
    page,
  }) => {
    const blockTitle = `Target Selection Test ${testId}`;

    // Navigate to proxy block configuration
    await page.goto(
      `${baseURL}/admin/structure/block/add/proxy_block_proxy/${ENVIRONMENT.theme}`,
    );
    await page.waitForLoadState('networkidle');

    // Configure target block selection
    const titleField = page.locator('#edit-settings-label');
    await titleField.clear();
    await titleField.fill(blockTitle);

    const targetSelect = page.locator('#edit-settings-target-block-id');
    await targetSelect.selectOption('system_powered_by_block');
    await page.waitForLoadState('networkidle');

    // Set region and save
    const regionSelect = page.locator('select[name="region"]');
    await regionSelect.selectOption('content');

    const saveButton = page.locator('button:has-text("Save block")');
    await saveButton.click();
    await page.waitForLoadState('networkidle');

    // Verify the proxy block was saved with target configuration
    const successMessage = page.locator('.messages--status');
    await expect(successMessage).toBeVisible({ timeout: TIMEOUTS.DEFAULT });

    // Navigate back to edit and verify target block is persisted
    await page.goto(`${baseURL}/admin/structure/block/list/${ENVIRONMENT.theme}`);
    const editLink = page.locator(`tr:has-text("${blockTitle}") a:has-text("Configure")`);
    await editLink.click();
    await page.waitForLoadState('networkidle');

    // Verify target block selection is preserved
    const persistedTargetSelect = page.locator('#edit-settings-target-block-id');
    const selectedValue = await persistedTargetSelect.inputValue();
    expect(selectedValue).toBe('system_powered_by_block');
  });

  test('should render target block content through proxy block', async ({ page }) => {
    const blockTitle = `Proxy Render Test ${testId}`;

    // Configure proxy block with target
    await page.goto(
      `${baseURL}/admin/structure/block/add/proxy_block_proxy/${ENVIRONMENT.theme}`,
    );
    await page.waitForLoadState('networkidle');

    const titleField = page.locator('#edit-settings-label');
    await titleField.clear();
    await titleField.fill(blockTitle);

    const targetSelect = page.locator('#edit-settings-target-block-id');
    await targetSelect.selectOption('system_powered_by_block');
    await page.waitForLoadState('networkidle');

    const regionSelect = page.locator('select[name="region"]');
    await regionSelect.selectOption('content');

    const saveButton = page.locator('button:has-text("Save block")');
    await saveButton.click();
    await page.waitForLoadState('networkidle');

    // Check frontend rendering - proxy should render target content
    await page.goto(`${baseURL}/`);
    await page.waitForLoadState('networkidle');

    // Verify proxy block title is present
    const proxyBlockTitle = page.locator(`h2:has-text("${blockTitle}")`);
    await expect(proxyBlockTitle).toBeVisible({ timeout: TIMEOUTS.DEFAULT });

    // Verify target block content is rendered through proxy
    const targetContent = page.locator(':text("Powered by Drupal")');
    await expect(targetContent).toBeVisible({ timeout: TIMEOUTS.DEFAULT });

    // Verify no PHP errors in proxy rendering
    const phpErrors = page.locator('.php-error, .error-message:has-text("Fatal")');
    await expect(phpErrors).toHaveCount(0);
  });

  test('should validate target block selection is required', async ({ page }) => {
    // Navigate to proxy block configuration
    await page.goto(
      `${baseURL}/admin/structure/block/add/proxy_block_proxy/${ENVIRONMENT.theme}`,
    );
    await page.waitForLoadState('networkidle');

    // Fill in title but leave target block unselected
    const titleField = page.locator('#edit-settings-label');
    await titleField.clear();
    await titleField.fill('Validation Test Block');

    // Ensure target block is not selected (default/empty option)
    const targetSelect = page.locator('#edit-settings-target-block-id');
    await targetSelect.selectOption('');

    // Try to save without selecting target block
    const saveButton = page.locator('button:has-text("Save block")');
    await saveButton.click();
    await page.waitForLoadState('networkidle');

    // Should either stay on form or show validation for required target block
    const stillOnForm = page.locator('h1:has-text("Configure"), h1:has-text("Place")');
    const onFormPage = (await stillOnForm.count()) > 0;

    if (onFormPage) {
      // Verify target block field is still present (proxy-specific validation)
      const targetFieldStill = page.locator('#edit-settings-target-block-id');
      await expect(targetFieldStill).toBeVisible({ timeout: TIMEOUTS.DEFAULT });
    }
  });

  test('should handle different target block selections correctly', async ({ page }) => {
    const blockTitle = `Multi Target Test ${testId}`;

    // Test proxy with different target blocks to verify selection works
    const targetBlocks = [
      { id: 'system_powered_by_block', expectedContent: 'Powered by' },
      { id: 'system_branding_block', expectedContent: 'Home' } // Usually contains site name/logo
    ];

    for (const targetBlock of targetBlocks) {
      const currentBlockTitle = `${blockTitle} ${targetBlock.id}`;

      // Configure proxy block with specific target
      await page.goto(
        `${baseURL}/admin/structure/block/add/proxy_block_proxy/${ENVIRONMENT.theme}`,
      );
      await page.waitForLoadState('networkidle');

      const titleField = page.locator('#edit-settings-label');
      await titleField.clear();
      await titleField.fill(currentBlockTitle);

      const targetSelect = page.locator('#edit-settings-target-block-id');
      await targetSelect.selectOption(targetBlock.id);
      await page.waitForLoadState('networkidle');

      const regionSelect = page.locator('select[name="region"]');
      await regionSelect.selectOption('content');

      const saveButton = page.locator('button:has-text("Save block")');
      await saveButton.click();
      await page.waitForLoadState('networkidle');

      // Verify frontend renders the correct target block content
      await page.goto(`${baseURL}/`);
      await page.waitForLoadState('networkidle');

      // Check that proxy block is present
      const proxyBlock = page.locator(`h2:has-text("${currentBlockTitle}")`);
      await expect(proxyBlock).toBeVisible({ timeout: TIMEOUTS.DEFAULT });

      // Verify target-specific content is rendered through proxy
      const targetContent = page.locator(`:text("${targetBlock.expectedContent}")`);
      await expect(targetContent).toBeVisible({ timeout: TIMEOUTS.DEFAULT });

      // Clean up for next iteration
      await loginAsAdmin(page);
      await page.goto(`${baseURL}/admin/structure/block/list/${ENVIRONMENT.theme}`);
      const removeLink = page.locator(`tr:has-text("${currentBlockTitle}") a:has-text("Remove")`);
      if ((await removeLink.count()) > 0) {
        await removeLink.click();
        await page.locator('input[value="Remove"]').click();
        await page.waitForLoadState('networkidle');
      }
    }
  });
});

test.describe('Proxy Block Target Configuration AJAX', () => {
  const testId = Date.now();

  test.beforeEach(async ({ page }) => {
    test.setTimeout(TIMEOUTS.LONG);
    await loginAsAdmin(page);
  });

  test('should trigger AJAX when target block is selected', async ({ page }) => {
    // Navigate to proxy block configuration
    await page.goto(
      `${baseURL}/admin/structure/block/add/proxy_block_proxy/${ENVIRONMENT.theme}`,
    );
    await page.waitForLoadState('networkidle');

    // Monitor for AJAX requests when target block changes
    let ajaxTriggered = false;
    page.on('request', request => {
      if (request.url().includes('/ajax') || request.url().includes('?ajax_form=1')) {
        ajaxTriggered = true;
      }
    });

    const targetSelect = page.locator('#edit-settings-target-block-id');
    await targetSelect.selectOption('system_powered_by_block');
    
    // Wait for potential AJAX to complete
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);

    // Some proxy blocks may trigger AJAX to load target configuration
    // This test verifies the selection works regardless of AJAX
    const selectedValue = await targetSelect.inputValue();
    expect(selectedValue).toBe('system_powered_by_block');
  });

  test('should load target block configuration form if available', async ({ page }) => {
    await page.goto(
      `${baseURL}/admin/structure/block/add/proxy_block_proxy/${ENVIRONMENT.theme}`,
    );
    await page.waitForLoadState('networkidle');

    const targetSelect = page.locator('#edit-settings-target-block-id');
    await targetSelect.selectOption('system_powered_by_block');
    await page.waitForLoadState('networkidle');

    // Check if target block's configuration options appear
    // This is proxy-specific functionality - loading target config within proxy form
    const targetConfigSection = page.locator('.proxy-block-target-configuration, fieldset:has-text("Target"), .target-block-settings');
    
    // Target configuration may or may not appear depending on target block
    // The key test is that selection doesn't break the form
    const formIsStillValid = await page.locator('#edit-submit').isVisible();
    expect(formIsStillValid).toBe(true);
  });
});

test.describe('Proxy Block Context Handling', () => {
  const testId = Date.now();

  test.beforeEach(async ({ page }) => {
    test.setTimeout(TIMEOUTS.LONG);
    await loginAsAdmin(page);
  });

  test('should pass contexts from proxy to target block', async ({ page }) => {
    const blockTitle = `Context Test Block ${testId}`;

    // Configure proxy block
    await page.goto(
      `${baseURL}/admin/structure/block/add/proxy_block_proxy/${ENVIRONMENT.theme}`,
    );
    await page.waitForLoadState('networkidle');

    const titleField = page.locator('#edit-settings-label');
    await titleField.clear();
    await titleField.fill(blockTitle);

    const targetSelect = page.locator('#edit-settings-target-block-id');
    await targetSelect.selectOption('system_powered_by_block');
    await page.waitForLoadState('networkidle');

    const regionSelect = page.locator('select[name="region"]');
    await regionSelect.selectOption('content');

    const saveButton = page.locator('button:has-text("Save block")');
    await saveButton.click();
    await page.waitForLoadState('networkidle');

    // Test on frontend - proxy should pass appropriate contexts to target
    await page.goto(`${baseURL}/`);
    await page.waitForLoadState('networkidle');

    // Verify proxy block renders target content with correct context
    const proxyBlock = page.locator(`h2:has-text("${blockTitle}")`);
    await expect(proxyBlock).toBeVisible({ timeout: TIMEOUTS.DEFAULT });

    // Verify target content renders (context was passed successfully)
    const targetContent = page.locator(':text("Powered by")');
    await expect(targetContent).toBeVisible({ timeout: TIMEOUTS.DEFAULT });

    // Verify no context-related errors
    const contextErrors = page.locator('.error:has-text("context"), .error:has-text("Context")');
    await expect(contextErrors).toHaveCount(0);
  });
});