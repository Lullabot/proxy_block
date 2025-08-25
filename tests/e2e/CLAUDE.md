# Playwright End-to-End Testing Methodology

This file provides guidance to Claude Code when working with Playwright tests in this Drupal module.

## Testing Philosophy

**No Conditional Logic - Real Assertions Only**

- Never skip assertions or use conditional logic to mask failures
- Tests must fail when functionality is broken
- Always debug and fix root causes, not symptoms

## Drupal-Specific Testing Patterns

### Block Management Workflow

**Critical Discovery**: Drupal block creation requires explicit region assignment during the configuration process.

```javascript
// ❌ WRONG - Creates block without region (fails silently)
await page.goto('/admin/structure/block/add/proxy_block_proxy/olivero');
await page.fill('#edit-settings-label', 'My Block');
await page.click('button:has-text("Save block")');

// ✅ CORRECT - Includes region selection
await page.goto('/admin/structure/block/add/proxy_block_proxy/olivero');
await page.fill('#edit-settings-label', 'My Block');
await page.selectOption('#edit-region', 'content_above'); // Required!
await page.click('button:has-text("Save block")');
```

### Modal Interactions

**Key Finding**: Drupal modals use `<a>` tags styled as buttons, not actual `<button>` elements.

```javascript
// ❌ WRONG - Looks only for button elements
const placeButton = page.locator(
  'tr:has-text("Block Name") button:has-text("Place block")',
);

// ✅ CORRECT - Handles both buttons and links
const placeButton = page
  .locator(
    'tr:has-text("Block Name") a:has-text("Place block"), tr:has-text("Block Name") button:has-text("Place block")',
  )
  .first();
```

### Form Button Selection

**Issue**: Drupal forms often have multiple save buttons causing Playwright strict mode violations.

```javascript
// ❌ WRONG - Matches multiple elements
const saveButton = page.locator('input[type="submit"]');

// ✅ CORRECT - Specific primary button
const saveButton = page.locator('button:has-text("Save block")').first();
```

### Page State Management

**Critical**: Always wait for complete page state after navigation and form submissions.

```javascript
// Required pattern for all form interactions
await page.click('button:has-text("Save")');
await page.waitForLoadState('networkidle'); // Wait for AJAX/redirects
await page.waitForTimeout(1000); // Additional buffer for DOM updates
```

## Robust Testing Strategies

### 1. Selector Resilience

Use multiple selector strategies for critical elements:

```javascript
// Multi-strategy selectors
const titleField = page
  .locator('#edit-settings-label, input[type="text"][name*="label"]')
  .first();
```

### 2. Error State Handling

Always check for error conditions:

```javascript
// Verify no PHP errors on page
const phpErrors = page.locator(
  '.php-error, .error-message:has-text("Fatal"), .messages--error:has-text("Fatal")',
);
await expect(phpErrors).toHaveCount(0);
```

### 3. Block Verification

Complete block workflow verification:

```javascript
async function verifyBlockPlacement(page, blockTitle, expectedContent) {
  // 1. Verify in admin layout
  await page.goto('/admin/structure/block/list/olivero');
  const adminBlock = page.locator(`tr:has-text("${blockTitle}")`);
  await expect(adminBlock).toBeVisible();

  // 2. Verify on frontend
  await page.goto('/');
  const frontendBlock = page.locator(`:text("${blockTitle}")`);
  await expect(frontendBlock).toBeVisible();

  // 3. Verify target content (for proxy blocks)
  if (expectedContent) {
    const content = page.locator(`:text("${expectedContent}")`);
    await expect(content).toBeVisible();
  }
}
```

## Common Drupal Pitfalls

### 1. Region Requirements

- **All blocks MUST have a region assigned during creation**
- Missing regions cause silent failures
- Blocks without regions don't appear in layout or frontend

### 2. Cache Invalidation

- Form submissions may require cache clearing
- Use `page.waitForLoadState('networkidle')` after saves
- Consider explicit cache clearing for complex workflows

### 3. AJAX Handling

- Many Drupal forms use AJAX for dynamic updates
- Always wait for network idle after form interactions
- Look for loading indicators and wait for them to disappear

### 4. Theme-Specific Elements

- Selectors may vary between themes
- Use theme-agnostic selectors when possible
- Test with the actual theme used in production

## Debugging Strategies

### 1. Screenshot Analysis

Always capture screenshots on failure:

```javascript
test.afterEach(async ({ page }, testInfo) => {
  if (testInfo.status === 'failed') {
    await page.screenshot({
      path: `debug-${testInfo.title}-${Date.now()}.png`,
      fullPage: true,
    });
  }
});
```

### 2. DOM State Logging

Log page state when selectors fail:

```javascript
try {
  await expect(element).toBeVisible();
} catch (error) {
  console.log('Current URL:', page.url());
  console.log('Page title:', await page.title());
  const bodyText = await page.locator('body').textContent();
  console.log('Page contains:', bodyText.substring(0, 500));
  throw error;
}
```

### 3. Network Monitoring

Monitor AJAX requests for debugging:

```javascript
page.on('response', response => {
  if (response.status() >= 400) {
    console.log(`HTTP Error: ${response.status()} ${response.url()}`);
  }
});
```

## Test Structure Template

```javascript
test.describe('Module Feature Tests', () => {
  const testId = Date.now(); // Unique identifier

  test.beforeEach(async ({ page }) => {
    // Login and setup
    await loginAsAdmin(page);
  });

  test.afterEach(async ({ page }) => {
    // Cleanup created resources
    // Log warnings but don't fail tests for cleanup issues
  });

  test('should perform complete workflow', async ({ page }) => {
    // 1. Setup
    const uniqueName = `Test Item ${testId}`;

    // 2. Action
    await performAction(page, uniqueName);

    // 3. Verification
    await verifyResult(page, uniqueName);

    // 4. Cleanup (if needed for this specific test)
  });
});
```

## Performance Considerations

- Use `test.setTimeout()` for complex workflows
- Prefer direct API calls over UI interactions when possible
- Run tests in parallel when they don't interfere with each other
- Use `page.waitForLoadState('networkidle')` judiciously (expensive)

## Key Learnings from Proxy Block Module

1. **Modal interactions work with `<a>` tags, not `<button>` elements**
2. **Region selection is mandatory for block creation success**
3. **Multiple save buttons require specific selectors to avoid conflicts**
4. **PHP configuration warnings can prevent frontend rendering**
5. **Complete workflows require: Create → Configure → Place → Verify**
6. **Drupal's block placement is a multi-step process requiring region assignment**

This methodology ensures robust, maintainable tests that accurately reflect real user workflows in Drupal.
