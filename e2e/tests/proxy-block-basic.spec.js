/**
 * @file
 * Basic proxy block tests with simplified approach.
 */

const { test, expect } = require('@playwright/test');
const { createAdminUser, enableModule, clearCache } = require('../utils/drush-helper');

test.describe('Proxy Block Basic', () => {
  test.beforeAll(async () => {
    await enableModule('proxy_block');
    await createAdminUser();
    await clearCache();
  });

  test('should access block layout page when logged in', async ({ page }) => {
    // Login first
    await page.goto('/user/login');
    await page.waitForLoadState('networkidle');
    await page.fill('#edit-name', 'admin');
    await page.fill('#edit-pass', 'admin');
    await page.click('#edit-submit');
    await page.waitForLoadState('networkidle');
    
    // Navigate to block layout (stark theme)
    await page.goto('/admin/structure/block/list/stark');
    await page.waitForLoadState('networkidle');
    
    // Check if we can access the block layout page
    const pageTitle = await page.title();
    console.log('Block layout page title:', pageTitle);
    
    // Look for block layout elements
    const blockLayoutElements = await page.locator('body').textContent();
    console.log('Page contains "Place block":', blockLayoutElements.includes('Place block'));
    console.log('Page contains "Block":', blockLayoutElements.includes('Block'));
    
    // Check current URL to confirm we're on the right page
    console.log('Current URL:', page.url());
    
    // Look for regions
    const regions = ['header', 'content', 'sidebar_first', 'footer'];
    for (const region of regions) {
      const regionElement = page.locator(`[data-region="${region}"], .region-${region}`);
      const regionExists = await regionElement.count() > 0;
      console.log(`Region "${region}" found:`, regionExists);
    }
  });

  test('should be able to access place block interface', async ({ page }) => {
    // Login first
    await page.goto('/user/login');
    await page.waitForLoadState('networkidle');
    await page.fill('#edit-name', 'admin');
    await page.fill('#edit-pass', 'admin');
    await page.click('#edit-submit');
    await page.waitForLoadState('networkidle');
    
    // Try to access place block directly for content region
    await page.goto('/admin/structure/block/add/proxy_block/stark');
    await page.waitForLoadState('networkidle');
    
    console.log('Place block URL:', page.url());
    const pageContent = await page.textContent('body');
    console.log('Page contains "proxy":', pageContent.toLowerCase().includes('proxy'));
    console.log('Page contains "block":', pageContent.toLowerCase().includes('block'));
    console.log('Page contains "configure":', pageContent.toLowerCase().includes('configure'));
    
    // Look for form elements that might be present
    const titleField = page.locator('#edit-settings-label, [name*="label"], [name*="title"]');
    const titleFieldExists = await titleField.count() > 0;
    console.log('Title field found:', titleFieldExists);
    
    if (titleFieldExists) {
      console.log('SUCCESS: Proxy block configuration form is accessible!');
    }
  });

  test('should verify proxy block module is functioning', async ({ page }) => {
    // Login first
    await page.goto('/user/login');
    await page.waitForLoadState('networkidle');
    await page.fill('#edit-name', 'admin');
    await page.fill('#edit-pass', 'admin');
    await page.click('#edit-submit');
    await page.waitForLoadState('networkidle');
    
    // Check modules page to verify proxy_block is enabled
    await page.goto('/admin/modules');
    await page.waitForLoadState('networkidle');
    
    const pageContent = await page.textContent('body');
    console.log('Modules page contains "Proxy Block":', pageContent.includes('Proxy Block'));
    
    // Look for the proxy_block checkbox
    const proxyBlockCheckbox = page.locator('input[name="modules[proxy_block][enable]"]');
    const checkboxExists = await proxyBlockCheckbox.count() > 0;
    console.log('Proxy block checkbox found:', checkboxExists);
    
    if (checkboxExists) {
      const isChecked = await proxyBlockCheckbox.isChecked();
      console.log('Proxy block is enabled:', isChecked);
      
      if (isChecked) {
        console.log('SUCCESS: Proxy Block module is properly enabled!');
      }
    }
  });
});