# Proxy Block

A Drupal module that provides a block plugin that can render any other block plugin the system. This allows for dynamic block selection and configuration through an
administrative interface.

## Overview

The proxy block is likely only useful for the A/B Blocks sub-module in the [A/B Tests](https://www.github.com/Lullabot/ab_tests) project.

## Features

- **Dynamic Block Selection**: Choose any available block plugin from a dropdown
- **AJAX Configuration**: Real-time configuration forms that update based on block
  selection
- **Context Mapping**: Pass page contexts to target blocks that require them
- **Access Control**: Respects target block access permissions
- **Cache Integration**: Properly handles cache metadata from target blocks
- **Layout Builder Compatible**: Works seamlessly in Layout Builder and traditional
  Block UI

## Use Cases

- **A/B Testing**: Switch between different blocks for testing
- **Conditional Block Display**: Show different blocks based on configuration
- **Block Reusability**: Use the same block configuration in multiple places
- **Dynamic Content**: Change block content without rebuilding layouts

## User Interface

### Block Configuration

1.  **Target Block Selection**
    - Dropdown list of all available block plugins (excluding the proxy block itself)
    - Option to select "Do not render any block" to hide the block completely
    - AJAX-powered selection that immediately updates the configuration form

2.  **Target Block Configuration**
    - Dynamic configuration form that appears after selecting a target block
    - Shows the same configuration options as the target block would normally have
    - Updated in real-time via AJAX when changing target block selection
    - Displays informational message for blocks without configuration options

3.  **Context Mapping** (when applicable)
    - Appears for blocks that require contexts (e.g., node, user, term contexts)
    - Dropdown for each required context to map to available page contexts
    - Required context mappings are marked as mandatory
    - Validates that all required contexts are properly mapped

### Administrative Experience

The administrative interface follows Drupal's standard block configuration patterns:

- **Block Library**: Available through standard block placement UI
- **Layout Builder**: Can be added as any other block in Layout Builder
- **Configuration**: Accessed through standard block configuration forms
- **Validation**: Real-time validation of target block configuration and context
  mapping

## Technical Architecture

### Core Components

#### ProxyBlock Plugin (`src/Plugin/Block/ProxyBlock.php`)

The main block plugin that implements the proxy functionality:

- **Extends**: `BlockBase`
- **Implements**: `ContainerFactoryPluginInterface`, `ContextAwarePluginInterface`
- **Pattern**: Uses final class with constructor promotion and dependency injection

### Render Logic

#### Initialization Phase

1.  **Block Creation**: Proxy block is instantiated by Drupal's block system
2.  **Service Injection**: Required services (BlockManager, Logger, CurrentUser) are
    injected
3.  **Configuration Loading**: Saved configuration is loaded from storage

#### Configuration Phase

1.  **Form Building**: Administrative configuration form is built
2.  **Target Selection**: Available block plugins are enumerated and presented
3.  **AJAX Handling**: Target block selection triggers AJAX callback
4.  **Dynamic Form**: Target block configuration form is dynamically loaded
5.  **Context Discovery**: Required contexts for target block are identified
6.  **Validation**: Form submission validates target block configuration and context
    mapping

#### Render Phase

1.  **Target Block Creation**:

    ```php
    $target_block = $this->blockManager->createInstance($plugin_id, $block_config);
    ```

2.  **Context Mapping**:

    ```php
    if ($target_block instanceof ContextAwarePluginInterface) {
      $this->passContextsToTargetBlock($target_block);
    }
    ```

3.  **Access Control**:

    ```php
    $access_result = $target_block->access($this->currentUser, TRUE);
    ```

4.  **Content Generation**:

    ```php
    $build = $target_block->build();
    ```

5.  **Cache Metadata**:
    ```php
    $this->bubbleTargetBlockCacheMetadata($build, $target_block);
    ```

### Context Handling

The module handles context passing through a sophisticated mapping system:

#### Context Discovery

- Inspects target block's context definitions using `getContextDefinitions()`
- Identifies required vs optional contexts
- Builds mapping form for each context requirement

#### Context Mapping

- Maps proxy block's available contexts to target block's required contexts
- Supports both automatic mapping (same context name) and manual mapping
- Validates that all required contexts are mapped before allowing form submission

#### Context Application

```php
protected function passContextsToTargetBlock(ContextAwarePluginInterface
$target_block): void {
  $proxy_contexts = $this->getContexts();
  $context_mapping = $this->getConfiguration()['context_mapping'] ?? [];

  // Map contexts based on configuration
  foreach ($target_context_definitions as $name => $definition) {
    $source_name = $context_mapping[$name] ?? $name;
    if (isset($proxy_contexts[$source_name])) {
      $target_block->setContext($name, $proxy_contexts[$source_name]);
    }
  }
}
```

### Cache Integration

The module properly handles cache metadata to ensure optimal performance:

#### Cache Contexts

- Merges proxy block cache contexts with target block cache contexts
- Ensures cache varies on all relevant parameters

#### Cache Tags

- Bubbles target block cache tags to proxy block
- Adds proxy-specific cache tags for invalidation

#### Cache Max Age

- Uses most restrictive max-age between proxy and target blocks
- Ensures proper cache invalidation timing

### Error Handling

The module implements comprehensive error handling:

#### Plugin Creation Errors

- Catches `PluginException` during target block instantiation
- Logs errors with context information
- Gracefully degrades to empty render array

#### Context Errors

- Catches `ContextException` during context mapping
- Logs context-related errors
- Continues execution with available contexts

#### Form Errors

- Validates target block configuration
- Validates required context mappings
- Provides user-friendly error messages

## Development Patterns

This module follows several advanced Drupal development patterns:

### Functional Programming

- Uses `array_map`, `array_filter`, `array_reduce` instead of foreach loops
- Implements functional composition for data transformation
- Uses arrow functions for simple transformations

### Polymorphism Over Conditionals

- Uses strategy pattern for different block types
- Leverages PHP 8 match expressions for clean branching
- Implements interface-based behavior detection

### Dependency Injection

- Constructor promotion for clean dependency injection
- Auto-wiring of services through ContainerFactoryPluginInterface
- Minimal service coupling

### Modern PHP Features

- PHP 8.1+ features including constructor promotion
- Strict typing with `declare(strict_types=1)`
- Final classes for performance and encapsulation
- Union types for intersection constraints

## Installation

1.  Place module in `modules/contrib/proxy_block` or install via Composer
2.  Enable the module: `drush en proxy_block`
3.  Clear caches: `drush cr`
4.  The "Proxy Block" will be available in the block library

## Configuration

No global configuration is required. Each proxy block instance is configured
individually through the standard Drupal block configuration interface.

## Compatibility

- **Drupal**: 10.x, 11.x
- **PHP**: 8.1+
- **Layout Builder**: Full compatibility
- **Block UI**: Full compatibility
- **Context System**: Full integration

## Testing

This module includes comprehensive test suites to ensure functionality across different environments.

### PHPUnit Tests

Run standard Drupal tests using PHPUnit:

```bash
# From Drupal root directory
vendor/bin/phpunit --configuration web/core/phpunit.xml.dist web/modules/contrib/proxy_block/tests

# Run specific test types
vendor/bin/phpunit --debug -c web/core/phpunit.xml.dist web/modules/contrib/proxy_block/tests/src/Unit/
vendor/bin/phpunit --debug -c web/core/phpunit.xml.dist web/modules/contrib/proxy_block/tests/src/Kernel/
vendor/bin/phpunit --debug -c web/core/phpunit.xml.dist web/modules/contrib/proxy_block/tests/src/Functional/
vendor/bin/phpunit --debug -c web/core/phpunit.xml.dist web/modules/contrib/proxy_block/tests/src/FunctionalJavascript/
```

### Playwright E2E Tests

The module includes comprehensive end-to-end tests using Playwright to validate the full user workflow.

#### Prerequisites

1. **Node.js 18+** and **npm** installed
2. **DDEV environment** (recommended) or working Drupal site
3. **Proxy Block module** enabled on the test site

#### Local Setup

```bash
# Navigate to the module directory
cd web/modules/contrib/proxy_block

# Install Node.js dependencies
npm ci

# Install Playwright browsers
npm run e2e:install
# Or manually: npx playwright install
```

#### Running Tests

**Option 1: DDEV Environment (Recommended)**

```bash
# Ensure DDEV is running
ddev start

# Run all E2E tests
npm run e2e:test

# Run tests with browser UI (for debugging)
npm run e2e:test:headed

# Run in debug mode with step-by-step execution
npm run e2e:test:debug

# Run specific test files
npx playwright test tests/e2e/tests/auth-simple.spec.js
npx playwright test tests/e2e/tests/ci-basic.spec.js
```

**Option 2: Standard Drupal Site**

```bash
# Set the base URL for your Drupal site
export DRUPAL_BASE_URL="http://your-drupal-site.local"

# Run tests
npm run e2e:test
```

#### Test Structure

The E2E test suite includes:

- **`ci-basic.spec.js`**: Core functionality tests (CI-compatible, no external dependencies)
- **`auth-simple.spec.js`**: Authentication and basic admin operations
- **`simple.spec.js`**: Infrastructure and basic site validation
- **Page Objects**: Reusable components in `tests/e2e/page-objects/`
- **Utilities**: Helper functions in `tests/e2e/utils/`

#### Test Configuration

The tests are configured through `playwright.config.js` with these defaults:

```javascript
// Automatically detects environment
baseURL: process.env.DRUPAL_BASE_URL ||
  process.env.DDEV_PRIMARY_URL ||
  'http://127.0.0.1:8080';

// CI-optimized settings
workers: process.env.CI ? 1 : undefined;
retries: process.env.CI ? 2 : 0;
```

#### Viewing Test Results

```bash
# View the last test report
npm run e2e:report
# Or manually: npx playwright show-report

# Test results are saved to:
# - playwright-report/ (HTML report)
# - test-results/ (screenshots, videos, traces)
```

#### Debugging Tests

1. **Interactive Mode**: Use `npm run e2e:test:debug` to step through tests
2. **Screenshots**: Automatically captured on failure
3. **Videos**: Recorded for failed tests
4. **Browser Console**: Console errors are captured and reported

#### Test Environment Requirements

**For Full Test Suite:**

- Drupal site with proxy_block module enabled
- Admin user with username: `admin`, password: `admin`
- Devel module enabled (for content generation)

**For Basic Tests Only:**

- Any accessible Drupal site
- Tests will create necessary users and content

#### Common Issues and Solutions

**Test Timeouts:**

```bash
# Increase timeout for slow environments
npx playwright test --timeout 60000
```

**Authentication Issues:**

```bash
# Verify admin user exists
drush user:information admin

# Create admin user if needed
drush user:create admin --mail="admin@example.com" --password="admin"
drush user:role:add administrator admin
```

**Module Not Found:**

```bash
# Ensure module is enabled
drush pm:enable proxy_block -y
drush cr
```

## Limitations

- Only supports block plugins, not content blocks from the Block Library
- Context mapping requires manual configuration for complex scenarios
- Performance depends on target block performance characteristics

## Security Considerations

- Respects all target block access permissions
- Does not bypass Drupal's security layer
- Validates all user input through Drupal's form API
- Logs security-relevant events for audit trails

## Performance

- Lazy-loads target blocks only when needed
- Properly caches target block output
- Minimizes database queries through proper caching
- Uses AJAX for responsive administrative interface

## Contributing

This module is not open to public contributions.
