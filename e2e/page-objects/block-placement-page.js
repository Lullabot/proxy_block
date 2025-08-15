/**
 * @file
 * Page Object Model for Drupal Block Placement interface.
 */

const { expect } = require('@playwright/test');
const { waitForAjax } = require('../helpers/drupal-nav');

class BlockPlacementPage {
  constructor(page) {
    this.page = page;
    
    // Selectors
    this.selectors = {
      placeBlockButton: '.block-list-secondary .button',
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
   * Navigate to block layout page for specified theme.
   *
   * @param {string} theme - Theme name
   */
  async navigate(theme = 'olivero') {
    await this.page.goto(`/admin/structure/block/list/${theme}`);
    await expect(this.page.locator('h1')).toContainText('Block layout');
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Click "Place block" button for a specific region.
   *
   * @param {string} region - Region name (e.g., 'content', 'sidebar_first')
   */
  async clickPlaceBlockForRegion(region = 'content') {
    const regionRow = this.page.locator(`tr[data-region="${region}"]`);
    await regionRow.locator(this.selectors.placeBlockButton).click();
    await this.page.waitForLoadState('networkidle');
    await expect(this.page.locator('h1')).toContainText('Place block');
  }

  /**
   * Search for a block in the placement interface.
   *
   * @param {string} searchTerm
   */
  async searchForBlock(searchTerm) {
    const searchInput = this.page.locator(this.selectors.blockSearchInput);
    if (await searchInput.isVisible()) {
      await searchInput.fill(searchTerm);
      await waitForAjax(this.page);
    }
  }

  /**
   * Filter blocks by category.
   *
   * @param {string} category
   */
  async filterByCategory(category) {
    const filterSelect = this.page.locator(this.selectors.blockFilterSelect);
    if (await filterSelect.isVisible()) {
      await filterSelect.selectOption(category);
      await waitForAjax(this.page);
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
    await expect(this.page.locator('h1')).toContainText('Configure block');
  }

  /**
   * Configure basic block settings.
   *
   * @param {Object} config
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
    const displayCheckbox = this.page.locator(this.selectors.blockDisplayTitleCheckbox);
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
   * @param {Object} config
   * @param {string} config.targetBlock - Target block plugin ID
   */
  async configureProxySettings(config = {}) {
    // Look for target block selection dropdown
    const targetBlockSelect = this.page.locator('#edit-settings-target-block');
    if (await targetBlockSelect.isVisible()) {
      if (config.targetBlock) {
        await targetBlockSelect.selectOption(config.targetBlock);
        await waitForAjax(this.page);
      }
    }

    // If target block has additional configuration, handle it
    const configurationSection = this.page.locator('.proxy-block-target-configuration');
    if (await configurationSection.isVisible()) {
      // Additional configuration would be handled here
      // This depends on the specific target block selected
    }
  }

  /**
   * Save the block configuration.
   */
  async saveBlock() {
    await this.page.locator(this.selectors.saveButton).click();
    await this.page.waitForLoadState('networkidle');
    
    // Should redirect back to block layout page
    await expect(this.page.locator('h1')).toContainText('Block layout');
    
    // Check for success message
    await expect(this.page.locator('.messages--status')).toBeVisible();
  }

  /**
   * Cancel block configuration.
   */
  async cancelConfiguration() {
    await this.page.locator(this.selectors.cancelButton).click();
    await this.page.waitForLoadState('networkidle');
    
    // Should redirect back to block layout page
    await expect(this.page.locator('h1')).toContainText('Block layout');
  }

  /**
   * Verify block was placed successfully.
   *
   * @param {string} blockTitle
   * @param {string} region
   */
  async verifyBlockPlaced(blockTitle, region = 'content') {
    // Look for the block in the specified region
    const regionRow = this.page.locator(`tr[data-region="${region}"]`);
    await expect(regionRow.locator('.draggable').filter({ hasText: blockTitle })).toBeVisible();
  }

  /**
   * Remove a placed block.
   *
   * @param {string} blockTitle
   */
  async removeBlock(blockTitle) {
    // Find the block row and click disable
    const blockRow = this.page.locator('.draggable').filter({ hasText: blockTitle });
    await blockRow.locator('a[href*="disable"]').click();
    
    await this.page.waitForLoadState('networkidle');
    await expect(this.page.locator('.messages--status')).toBeVisible();
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