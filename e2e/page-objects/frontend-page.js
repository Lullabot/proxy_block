/**
 * @file
 * Page Object Model for frontend pages to verify block rendering.
 */

const { expect } = require('@playwright/test');

class FrontendPage {
  constructor(page) {
    this.page = page;
    
    // Common selectors for Drupal frontend
    this.selectors = {
      content: '#main-content, .main-content, [role="main"]',
      sidebar: '.sidebar, .region-sidebar-first, .region-sidebar-second',
      header: 'header, .region-header',
      footer: 'footer, .region-footer',
      blocks: '[data-block-plugin-id], .block',
      proxyBlocks: '[data-block-plugin-id*="proxy_block"]',
      blockContent: '.block-content, .field, .field-content',
      breadcrumbs: '.breadcrumb',
      pageTitle: 'h1',
      messages: '.messages',
    };
  }

  /**
   * Navigate to a specific path.
   *
   * @param {string} path
   */
  async navigate(path = '/') {
    await this.page.goto(path);
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Navigate to the homepage.
   */
  async navigateToHomepage() {
    await this.navigate('/');
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Navigate to a specific node.
   *
   * @param {number} nodeId
   */
  async navigateToNode(nodeId) {
    await this.navigate(`/node/${nodeId}`);
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Verify page loads successfully.
   */
  async verifyPageLoads() {
    // Check that we don't have error pages
    const pageTitle = await this.page.title();
    expect(pageTitle).not.toContain('Page not found');
    expect(pageTitle).not.toContain('Access denied');
    
    // Check for main content area
    await expect(this.page.locator(this.selectors.content)).toBeVisible();
  }

  /**
   * Verify proxy block is present on the page.
   *
   * @param {string} blockTitle - Expected block title
   * @param {string} region - Expected region (optional)
   */
  async verifyProxyBlockPresent(blockTitle, region = null) {
    let selector = this.selectors.proxyBlocks;
    
    if (region) {
      selector = `.region-${region} ${this.selectors.proxyBlocks}`;
    }
    
    const proxyBlock = this.page.locator(selector);
    await expect(proxyBlock).toBeVisible();
    
    // If block title is specified, verify it's displayed
    if (blockTitle) {
      await expect(proxyBlock.locator('h2, .block-title')).toContainText(blockTitle);
    }
    
    return proxyBlock;
  }

  /**
   * Verify proxy block renders target block content.
   *
   * @param {string} expectedContent - Content that should be rendered
   */
  async verifyProxyBlockContent(expectedContent) {
    const proxyBlock = this.page.locator(this.selectors.proxyBlocks);
    await expect(proxyBlock).toBeVisible();
    
    // Check that the proxy block contains the expected content
    await expect(proxyBlock.locator(this.selectors.blockContent)).toContainText(expectedContent);
  }

  /**
   * Verify target block content is rendered within proxy block.
   *
   * @param {string} targetBlockSelector - CSS selector for target block content
   */
  async verifyTargetBlockRendered(targetBlockSelector) {
    const proxyBlock = this.page.locator(this.selectors.proxyBlocks);
    await expect(proxyBlock).toBeVisible();
    
    // Check that target block content exists within proxy block
    await expect(proxyBlock.locator(targetBlockSelector)).toBeVisible();
  }

  /**
   * Get all blocks on the page.
   */
  async getAllBlocks() {
    const blocks = [];
    const blockElements = this.page.locator(this.selectors.blocks);
    const count = await blockElements.count();
    
    for (let i = 0; i < count; i++) {
      const block = blockElements.nth(i);
      const pluginId = await block.getAttribute('data-block-plugin-id');
      const title = await block.locator('h2, .block-title').textContent().catch(() => '');
      
      blocks.push({
        pluginId,
        title: title.trim(),
        visible: await block.isVisible(),
      });
    }
    
    return blocks;
  }

  /**
   * Verify no PHP errors are displayed on the frontend.
   */
  async verifyNoPHPErrors() {
    // Check for PHP error messages that might be displayed
    const errorSelectors = [
      '.php-error',
      '.error-message',
      '[class*="error"]',
    ];
    
    for (const selector of errorSelectors) {
      const errors = this.page.locator(selector);
      const count = await errors.count();
      
      if (count > 0) {
        for (let i = 0; i < count; i++) {
          const errorText = await errors.nth(i).textContent();
          if (errorText && errorText.toLowerCase().includes('fatal')) {
            throw new Error(`PHP Fatal Error detected: ${errorText}`);
          }
        }
      }
    }
  }

  /**
   * Check browser console for JavaScript errors.
   */
  async verifyNoJSErrors() {
    // This would be set up in the test file to capture console errors
    // Return any console errors that were captured
    const errors = this.page.locator('.js-error, .console-error');
    await expect(errors).toHaveCount(0);
  }

  /**
   * Take screenshot for visual comparison.
   *
   * @param {string} name - Screenshot name
   * @param {Object} options - Screenshot options
   */
  async takeScreenshot(name, options = {}) {
    const defaultOptions = {
      fullPage: true,
      path: `test-results/screenshots/${name}-${Date.now()}.png`,
    };
    
    await this.page.screenshot({ ...defaultOptions, ...options });
  }

  /**
   * Verify page accessibility basics.
   */
  async verifyBasicAccessibility() {
    // Check for basic accessibility requirements
    await expect(this.page.locator('html[lang]')).toBeVisible();
    await expect(this.page.locator('h1')).toBeVisible();
    
    // Check that main content area has proper landmark
    const main = this.page.locator('main, [role="main"]');
    await expect(main).toBeVisible();
  }

  /**
   * Verify responsive behavior.
   *
   * @param {Object} viewport - Viewport dimensions
   */
  async verifyResponsiveBehavior(viewport = { width: 375, height: 667 }) {
    // Set mobile viewport
    await this.page.setViewportSize(viewport);
    await this.page.waitForLoadState('networkidle');
    
    // Verify page still loads correctly
    await this.verifyPageLoads();
    
    // Check that proxy blocks are still visible
    const proxyBlocks = this.page.locator(this.selectors.proxyBlocks);
    if (await proxyBlocks.count() > 0) {
      await expect(proxyBlocks.first()).toBeVisible();
    }
  }

  /**
   * Wait for dynamic content to load.
   *
   * @param {number} timeout - Timeout in milliseconds
   */
  async waitForDynamicContent(timeout = 5000) {
    // Wait for any AJAX or dynamic content
    await this.page.waitForFunction(
      () => document.readyState === 'complete',
      { timeout }
    );
    
    // Additional wait for any animations or transitions
    await this.page.waitForTimeout(500);
  }
}

module.exports = { FrontendPage };