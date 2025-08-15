/**
 * @file
 * Debug login functionality.
 */

const { test, expect } = require('@playwright/test');
const { createAdminUser, enableModule, clearCache } = require('../utils/drush-helper');

test.describe('Login Debug', () => {
  test.beforeAll(async () => {
    await enableModule('proxy_block');
    await createAdminUser();
    await clearCache();
  });

  test('should debug login process step by step', async ({ page }) => {
    console.log('Step 1: Navigate to login page');
    await page.goto('/user/login');
    await page.waitForLoadState('networkidle');
    
    console.log('Step 2: Check page title');
    const title = await page.title();
    console.log('Page title:', title);
    
    console.log('Step 3: Check for login form');
    const loginForm = await page.locator('#user-login-form');
    console.log('Login form exists:', await loginForm.count() > 0);
    
    if (await loginForm.count() > 0) {
      console.log('Step 4: Fill login form');
      await page.fill('#edit-name', 'admin');
      await page.fill('#edit-pass', 'admin');
      
      console.log('Step 5: Submit form');
      await page.click('#edit-submit');
      
      console.log('Step 6: Wait for response');
      await page.waitForLoadState('networkidle');
      
      console.log('Step 7: Check current URL');
      console.log('Current URL:', page.url());
      
      console.log('Step 8: Check page content');
      const pageContent = await page.textContent('body');
      console.log('Page contains "admin":', pageContent.toLowerCase().includes('admin'));
      console.log('Page contains "access denied":', pageContent.toLowerCase().includes('access denied'));
      console.log('Page contains "error":', pageContent.toLowerCase().includes('error'));
      
      console.log('Step 9: Check for admin toolbar');
      const adminToolbar = page.locator('#toolbar-administration');
      const toolbarExists = await adminToolbar.count() > 0;
      console.log('Admin toolbar exists:', toolbarExists);
      
      console.log('Step 10: Check for error messages');
      const errorMessages = page.locator('.messages--error');
      const errorCount = await errorMessages.count();
      console.log('Error message count:', errorCount);
      
      if (errorCount > 0) {
        const errorText = await errorMessages.textContent();
        console.log('Error messages:', errorText);
      }
      
      console.log('Step 11: Check for status messages');
      const statusMessages = page.locator('.messages--status');
      const statusCount = await statusMessages.count();
      console.log('Status message count:', statusCount);
      
      if (statusCount > 0) {
        const statusText = await statusMessages.textContent();
        console.log('Status messages:', statusText);
      }
    }
  });
});