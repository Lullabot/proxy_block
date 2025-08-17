/**
 * @file
 * Page Object Model for Drupal Block Placement interface.
 */

const { expect } = require('@playwright/test');

class BlockPlacementPage {
  constructor(page) {
    this.page = page;

    // Selectors
    this.selectors = {
      placeBlockButton:
        '.block-list-secondary .button, .button--small, .button[title*="Place"], a[title*="Place"], a:text("Place block")',
      blockSearchInput: '#edit-search',
      blockFilterSelect: '#edit-filter-category',
      proxyBlockLink: 'a[href*="proxy_block"]',
      blockConfigForm: '.block-form',
      blockTitleField: '#edit-settings-label',
      blockDisplayTitleCheckbox: '#edit-settings-label-display',
      regionSelect: '#edit-region',
      saveButton: '#edit-submit, .form-submit',
      cancelButton: '.form-cancel',
      blockList: '.block-list',
      placedBlocks: '.draggable',
    };
  }

  /**
   * Wait for AJAX operations to complete.
   */
  async waitForAjax() {
    // Wait for any AJAX throbbers to disappear
    await this.page.waitForFunction(
      () => {
        const throbbers = document.querySelectorAll(
          '.ajax-progress-throbber, .ajax-progress-bar',
        );
        return throbbers.length === 0;
      },
      { timeout: 30000 },
    );

    // Wait for network to be idle
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Navigate to block layout page for specified theme.
   *
   * @param {string} theme - Theme name
   */
  async navigate(theme = 'olivero') {
    await this.page.goto(`/admin/structure/block/list/${theme}`);

    // Debug: If we get "Access denied", capture the page content and logged-in status
    const h1Text = await this.page.locator('h1').textContent();
    if (h1Text && h1Text.includes('Access denied')) {
      console.log('Access denied detected! Current page content:');
      console.log('URL:', this.page.url());
      console.log('H1 text:', h1Text);

      // Check if we're actually logged in
      const loggedInElements = await this.page
        .locator('.toolbar, .user-logged-in, #toolbar-administration')
        .count();
      console.log('Logged in elements found:', loggedInElements);

      // Check for any error messages
      const errorMessages = await this.page
        .locator('.messages--error, .error')
        .textContent();
      if (errorMessages) {
        console.log('Error messages:', errorMessages);
      }

      // Get the full body content to understand what's happening
      const bodyContent = await this.page.locator('body').textContent();
      console.log(
        'Body content (first 500 chars):',
        bodyContent?.substring(0, 500),
      );
    }

    await expect(this.page.locator('h1')).toContainText('Block layout');
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Click "Place block" button for a specific region.
   *
   * @param {string} region - Region name (e.g., 'content', 'sidebar')
   */
  async clickPlaceBlockForRegion(region = 'content') {
    // Find the "Place block in the [Region Name] region" link - it MUST exist
    const placeLink = this.page
      .locator('a')
      .filter({
        hasText: /Place block in the .* region/,
      })
      .filter({
        hasText: new RegExp(region, 'i'),
      })
      .first();

    // The place block link MUST be found - if not, the test should fail
    await expect(placeLink).toBeVisible();
    await placeLink.click();

    await this.page.waitForLoadState('networkidle');

    // Check if modal dialog opened (which has the "Place block" title)
    const modalTitle = this.page.locator(
      '.ui-dialog-title, h1:has-text("Place block")',
    );
    await expect(modalTitle).toContainText('Place block');
  }

  /**
   * Search for a block in the placement interface.
   *
   * @param {string} searchTerm - The block name or text to search for
   */
  async searchForBlock(searchTerm) {
    const searchInput = this.page.locator(this.selectors.blockSearchInput);
    if (await searchInput.isVisible()) {
      await searchInput.fill(searchTerm);
      await this.waitForAjax();
    }
  }

  /**
   * Filter blocks by category.
   *
   * @param {string} category - The block category to filter by
   */
  async filterByCategory(category) {
    const filterSelect = this.page.locator(this.selectors.blockFilterSelect);
    if (await filterSelect.isVisible()) {
      await filterSelect.selectOption(category);
      await this.waitForAjax();
    }
  }

  /**
   * Click on Proxy Block to configure it.
   */
  async selectProxyBlock() {
    await this.searchForBlock('Proxy Block');

    // Look for Proxy Block link
    const proxyBlockLink = this.page.locator(this.selectors.proxyBlockLink);
    await expect(proxyBlockLink).toBeVisible();
    await proxyBlockLink.click();

    await this.page.waitForLoadState('networkidle');

    // Check for either modal or full page configuration
    const configTitle = this.page.locator(
      '.ui-dialog-title:has-text("Configure"), h1:has-text("Configure")',
    );
    await expect(configTitle).toContainText('Configure');
  }

  /**
   * Configure basic block settings.
   *
   * @param {Object} config - Block configuration options
   * @param {string} config.title - Block title
   * @param {boolean} config.displayTitle - Whether to display title
   * @param {string} config.region - Region to place block
   */
  async configureBasicSettings(config = {}) {
    const title = config.title || `Test Proxy Block ${Date.now()}`;
    const displayTitle = config.displayTitle !== false; // Default to true
    const region = config.region || 'content';

    // Fill block title
    const titleField = this.page.locator(this.selectors.blockTitleField);
    if (await titleField.isVisible()) {
      await titleField.fill(title);
    }

    // Set display title checkbox
    const displayCheckbox = this.page.locator(
      this.selectors.blockDisplayTitleCheckbox,
    );
    if (await displayCheckbox.isVisible()) {
      if (displayTitle) {
        await displayCheckbox.check();
      } else {
        await displayCheckbox.uncheck();
      }
    }

    // Select region
    const regionSelect = this.page.locator(this.selectors.regionSelect);
    if (await regionSelect.isVisible()) {
      await regionSelect.selectOption(region);
    }

    return { title, displayTitle, region };
  }

  /**
   * Configure Proxy Block specific settings.
   *
   * @param {Object} config - Proxy block configuration options
   * @param {string} config.targetBlock - Target block plugin ID
   */
  async configureProxySettings(config = {}) {
    // Look for target block selection dropdown
    const targetBlockSelect = this.page.locator('#edit-settings-target-block');
    if (await targetBlockSelect.isVisible()) {
      if (config.targetBlock) {
        await targetBlockSelect.selectOption(config.targetBlock);
        await this.waitForAjax();
      }
    }

    // If target block has additional configuration, handle it
    const configurationSection = this.page.locator(
      '.proxy-block-target-configuration',
    );
    if (await configurationSection.isVisible()) {
      // Additional configuration would be handled here
      // This depends on the specific target block selected
    }
  }

  /**
   * Save the block configuration.
   */
  async saveBlock() {
    // Find the specific save button for the block configuration modal
    const saveButton = this.page
      .locator('.ui-dialog input[value="Save block"]')
      .first();

    // The save button MUST exist and be visible
    await expect(saveButton).toBeVisible();
    await saveButton.click();

    await this.page.waitForLoadState('networkidle');

    // Wait for modal to close and return to block layout
    await this.page.waitForFunction(
      () => {
        const modals = document.querySelectorAll('.ui-dialog, .modal');
        return modals.length === 0;
      },
      { timeout: 10000 },
    );

    // Should be back on block layout page
    await expect(
      this.page.locator('h1:has-text("Block layout")'),
    ).toBeVisible();

    // Check for success message
    await expect(this.page.locator('.messages--status')).toBeVisible();
  }

  /**
   * Cancel block configuration.
   */
  async cancelConfiguration() {
    // Try to find cancel button in modal first
    const modalCancelButton = this.page
      .locator(
        '.ui-dialog .form-cancel, .ui-dialog .button:has-text("Cancel"), .ui-dialog-titlebar-close',
      )
      .first();

    if ((await modalCancelButton.count()) > 0) {
      await modalCancelButton.click();
    } else {
      // Fallback to general cancel button
      await this.page.locator(this.selectors.cancelButton).click();
    }

    await this.page.waitForLoadState('networkidle');

    // Should redirect back to block layout page
    await expect(
      this.page.locator('h1:has-text("Block layout")'),
    ).toContainText('Block layout');
  }

  /**
   * Verify block was placed successfully.
   *
   * @param {string} blockTitle - The title of the block to verify
   * @param {string} region - The region where the block should be placed
   */
  async verifyBlockPlaced(blockTitle, region = 'content') {
    // Look for the block in the block layout table
    // Since the layout doesn't use data-region attributes, search for the block by title
    // and verify it shows the correct region in the "Region" column

    const blockRows = this.page
      .locator('tbody tr')
      .filter({ hasText: blockTitle });
    await expect(blockRows.first()).toBeVisible();

    // Verify the region is correct by checking the Region column
    const regionCell = blockRows.first().locator('td').nth(2); // 3rd column is Region
    await expect(regionCell).toContainText(region, { ignoreCase: true });
  }

  /**
   * Remove a placed block.
   *
   * @param {string} blockTitle - The title of the block to remove
   */
  async removeBlock(blockTitle) {
    try {
      // Find the block row and click disable
      const blockRow = this.page
        .locator('.draggable')
        .filter({ hasText: blockTitle });

      // Check if block exists before trying to remove it
      if ((await blockRow.count()) === 0) {
        console.log(`Block "${blockTitle}" not found, may already be removed`);
        return;
      }

      const disableLink = blockRow.locator('a[href*="disable"]');
      if ((await disableLink.count()) > 0) {
        await disableLink.click();
        await this.page.waitForLoadState('networkidle');

        // Verify success message or that we're back on block layout page
        try {
          await expect(this.page.locator('.messages--status')).toBeVisible({
            timeout: 5000,
          });
        } catch (error) {
          // Alternative: just verify we're on the block layout page
          await expect(this.page.locator('h1')).toContainText('Block layout');
        }
      } else {
        console.log(`Disable link not found for block "${blockTitle}"`);
      }
    } catch (error) {
      console.log(`Error removing block "${blockTitle}": ${error.message}`);
      // Don't re-throw as cleanup should be non-fatal
    }
  }

  /**
   * Remove multiple blocks by title.
   * More efficient cleanup method for test teardown.
   *
   * @param {Array} blockTitles - Array of block titles to remove
   */
  async removeMultipleBlocks(blockTitles) {
    for (const blockTitle of blockTitles) {
      await this.removeBlock(blockTitle);
    }
  }

  /**
   * Get list of available blocks for placement.
   */
  async getAvailableBlocks() {
    const blocks = [];
    const blockLinks = this.page.locator('.block-list a');
    const count = await blockLinks.count();

    for (let i = 0; i < count; i++) {
      const text = await blockLinks.nth(i).textContent();
      blocks.push(text.trim());
    }

    return blocks;
  }
}

module.exports = { BlockPlacementPage };
