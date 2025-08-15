/**
 * @file
 * Simple test to verify basic Playwright functionality works.
 */

const { test, expect } = require('@playwright/test');

test.describe('Basic Infrastructure', () => {
  test('should be able to run basic test', async ({ page }) => {
    // Just verify Playwright is working
    await page.goto('https://example.com');
    await expect(page.locator('h1')).toContainText('Example Domain');
  });
});