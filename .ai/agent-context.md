# Agent Context

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the **Proxy Block** module for Drupal 10/11 - a contributed module that provides a block plugin capable of rendering any other block plugin in the system. This enables dynamic block selection and configuration through an administrative interface, primarily designed for A/B testing scenarios.

The module is part of the A/B Testing ecosystem and integrates with the [A/B Tests](https://www.github.com/Lullabot/ab_tests) project.

## Common Development Commands

### Drupal Commands

```bash
# Navigate to Drupal root first
cd /path/to/your/drupal/root

# Use drush from vendor/bin
vendor/bin/drush

# Clear cache (frequently needed during development)
vendor/bin/drush cache:rebuild
vendor/bin/drush cr

# Enable/disable the proxy_block module
vendor/bin/drush pm:enable proxy_block
vendor/bin/drush pm:uninstall proxy_block

# Export/import configuration
vendor/bin/drush config:export
vendor/bin/drush config:import
```

### Testing Commands

```bash
# Run all tests (from Drupal root)
vendor/bin/phpunit

# Run specific test groups
vendor/bin/phpunit --group proxy_block

# Run tests for this module specifically
vendor/bin/phpunit web/modules/contrib/proxy_block/tests/
```

### PHP Code Quality

```bash
# Run PHP static analysis (if PHPStan is configured)
vendor/bin/phpstan analyse web/modules/contrib/proxy_block/

# Run PHP Code Sniffer (if configured)
vendor/bin/phpcs web/modules/contrib/proxy_block/
```

## Code Architecture

### Core Component: ProxyBlock Plugin

**Location**: `src/Plugin/Block/ProxyBlock.php`

The main block plugin implements a sophisticated proxy pattern with the following key characteristics:

#### Modern PHP Patterns

- **Final class** with constructor promotion and dependency injection
- **Strict typing** with `declare(strict_types=1)`
- **PHP 8.1+ features** including union types and match expressions
- **Functional programming** patterns using `array_map`, `array_filter`, `array_reduce`

#### Key Interfaces

- `ContainerFactoryPluginInterface` - Dependency injection support
- `ContextAwarePluginInterface` - Context passing to target blocks
- `BlockPluginInterface` - Standard Drupal block behavior

#### Dependency Injection

```php
public function __construct(
  array $configuration,
  string $plugin_id,
  mixed $plugin_definition,
  BlockManagerInterface $block_manager,      // Creates target block instances
  LoggerInterface $logger,                   // Logs errors and warnings
  AccountProxyInterface $current_user,       // Access control
)
```

### Render Pipeline

#### 1. Configuration Phase

- **Target Block Selection**: Dropdown of all available block plugins (excluding self)
- **AJAX Configuration**: Real-time form updates when target block changes
- **Context Mapping**: Dynamic form for blocks requiring contexts (node, user, term, etc.)

#### 2. Validation Phase

- **Plugin Validation**: Ensures target block plugin exists and can be instantiated
- **Context Validation**: Verifies all required contexts are mapped
- **Configuration Validation**: Validates target block's own configuration

#### 3. Render Phase

```php
// Core render flow
$target_block = $this->getTargetBlock();           // Create/get cached instance
$access_result = $target_block->access(...);       // Check access permissions
$build = $target_block->build();                   // Generate render array
$this->bubbleTargetBlockCacheMetadata($build);     // Merge cache metadata
```

### Context Handling System

The module implements sophisticated context mapping for blocks that require contexts:

#### Context Discovery

- Inspects target block's `getContextDefinitions()`
- Identifies required vs optional contexts
- Builds dynamic mapping form

#### Context Application

- Maps proxy block contexts to target block contexts
- Supports both automatic (same name) and manual mapping
- Handles `ContextException` gracefully

### Cache Integration

Critical for performance - the module properly bubbles cache metadata:

```php
protected function bubbleTargetBlockCacheMetadata(array &$build, ...): void {
  $cache_metadata = CacheableMetadata::createFromRenderArray($build);

  // Merge target block cache metadata
  $cache_metadata->addCacheContexts($target_block->getCacheContexts());
  $cache_metadata->addCacheTags($target_block->getCacheTags());
  $cache_metadata->setCacheMaxAge(Cache::mergeMaxAges(...));

  // Apply to render array
  $cache_metadata->applyTo($build);
}
```

### Error Handling Strategy

Comprehensive error handling with graceful degradation:

- **Plugin Creation Errors**: Catches `PluginException`, logs error, returns empty render
- **Context Errors**: Catches `ContextException`, logs warning, continues with available contexts
- **Form Errors**: Validates configuration, provides user-friendly error messages

## Development Patterns

### Functional Programming Over Loops

```php
// Preferred: functional approach
$block_options = array_map(
  static fn($definition) => $definition['admin_label'] ?? $definition['id'],
  array_filter($block_definitions, fn($definition, $plugin_id) => $plugin_id !== $this->getPluginId())
);

// Avoid: traditional foreach loops for data transformation
```

### Polymorphism Over Conditionals

```php
// Uses interface detection instead of string comparisons
if ($target_block instanceof ContextAwarePluginInterface) {
  $this->passContextsToTargetBlock($target_block);
}

if ($target_block instanceof PluginFormInterface) {
  $config_form = $target_block->buildConfigurationForm([], $form_state);
}
```

### Early Returns (Guard Clauses)

```php
// Preferred pattern throughout codebase
if (empty($plugin_id)) {
  return NULL;
}

if (empty($context_definitions)) {
  return [];
}
```

## Module Integration

### A/B Testing Integration

- Designed as foundation for A/B testing blocks
- Works with the [A/B Tests](https://www.github.com/Lullabot/ab_tests) project
- Block category: "A/B Testing"

### Layout Builder Compatibility

- Full Layout Builder integration
- Standard block placement UI support
- Respects all Drupal block placement patterns

### Access Control Integration

- Respects target block access permissions
- No security bypass - maintains Drupal's access layer
- Proper access result caching

## Key Files

```
web/modules/contrib/proxy_block/
├── proxy_block.info.yml          # Module definition
├── README.md                     # Comprehensive documentation
└── src/Plugin/Block/
    └── ProxyBlock.php            # Main plugin implementation (650+ lines)
```

## Development Workflow

1. **Make changes** to `ProxyBlock.php`
2. **Clear cache**: `vendor/bin/drush cr`
3. **Test changes** through Drupal's block placement UI
4. **Run tests**: `vendor/bin/phpunit --group proxy_block`
5. **Validate code**: Use available composer scripts

### Code Quality Commands

The module includes composer scripts for code quality checks:

```bash
# Run PHP CodeSniffer (PHPCS) to check coding standards
composer run-script lint:check

# Fix coding standards violations automatically
composer run-script lint:fix
```

### Additional Testing Commands

```bash
# Run PHPStan static analysis (if configured in Drupal project)
vendor/bin/phpstan analyse web/modules/contrib/proxy_block/

# Run ESLint for JavaScript files
npx eslint **/*.js

# Run Stylelint for CSS files
npx stylelint **/*.css

# Run CSpell for spell checking
npx cspell "**/*.{php,md,yml,yaml,txt}"
```

## Performance Considerations

- **Lazy Loading**: Target blocks created only when needed
- **Instance Caching**: Target block instances cached within request
- **Cache Metadata**: Proper cache tag/context bubbling prevents cache pollution
- **AJAX Forms**: Responsive admin interface without full page reloads

## Security Notes

- Module respects all existing Drupal security layers
- No privilege escalation - proxy block access ≠ target block access
- All user input validated through Drupal Form API
- Security events logged for audit trails
