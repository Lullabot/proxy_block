# Agent Context

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the **Proxy Block** module for Drupal 10/11 - a contributed module that provides a block plugin capable of rendering any other block plugin in the system. This enables dynamic block selection and configuration through an administrative interface, primarily designed for A/B testing scenarios.

The module is part of the A/B Testing ecosystem and integrates with the [A/B Tests](https://www.github.com/Lullabot/ab_tests) project.

## Agent Strategies

- Before starting a task, inspect the sub-agents in @.claude/agents/ Select the sub-agent that is most appropriate for the task if you are confident one of them is appropriate. If you don't think one is appropriate, do not use a sub-agent.

## Common Development Commands

### Drupal Commands

```bash
# DDEV commands (when using DDEV local environment)
ddev drush

# Clear cache (frequently needed during development)
ddev drush cache:rebuild
ddev drush cr

# Enable/disable the proxy_block module
ddev drush pm:enable proxy_block
ddev drush pm:uninstall proxy_block

# Export/import configuration
ddev drush config:export
ddev drush config:import

# Alternative: Standard commands (when not using DDEV)
# Use drush from vendor/bin
vendor/bin/drush

# Clear cache
vendor/bin/drush cache:rebuild
vendor/bin/drush cr
```

### Testing Commands

```bash
# DDEV commands (when using DDEV local environment)
ddev exec vendor/bin/phpunit

# Run specific test groups
ddev exec vendor/bin/phpunit --group proxy_block

# Run tests for this module specifically
ddev exec vendor/bin/phpunit web/modules/contrib/proxy_block/tests/

# Alternative: Standard commands (when not using DDEV)
# Run all tests (from Drupal root)
vendor/bin/phpunit

# Run specific test groups
vendor/bin/phpunit --group proxy_block

# Run tests for this module specifically
vendor/bin/phpunit web/modules/contrib/proxy_block/tests/
```

### PHP Code Quality

```bash
# DDEV commands (when using DDEV local environment)
ddev php vendor/bin/phpcs --ignore='vendor/*,node_modules/*' --standard=Drupal,DrupalPractice --extensions=php,module/php,install/php,inc/php,yml web/modules/contrib/proxy_block
ddev php vendor/bin/phpcbf --ignore='vendor/*,node_modules/*' --standard=Drupal,DrupalPractice --extensions=php,module/php,install/php,inc/php,yml web/modules/contrib/proxy_block

# Alternative: Standard commands (when not using DDEV)
php ../../../../vendor/bin/phpcs --ignore='vendor/*,node_modules/*' --standard=Drupal,DrupalPractice --extensions=php,module/php,install/php,inc/php,yml web/modules/contrib/proxy_block
php ../../../../vendor/bin/phpcbf --ignore='vendor/*,node_modules/*' --standard=Drupal,DrupalPractice --extensions=php,module/php,install/php,inc/php,yml web/modules/contrib/proxy_block
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

See the constructor in `src/Plugin/Block/ProxyBlock.php` for the complete dependency injection setup, which includes BlockManagerInterface, LoggerInterface, and AccountProxyInterface services.

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

The core render flow is implemented in the `build()` method in `src/Plugin/Block/ProxyBlock.php`. This method handles target block creation, access checking, render array generation, and cache metadata bubbling.

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

Critical for performance - the module properly bubbles cache metadata through the `bubbleTargetBlockCacheMetadata()` method in `src/Plugin/Block/ProxyBlock.php`. This method merges cache contexts, tags, and max-age from both the target block and proxy block to ensure proper caching behavior.

### Error Handling Strategy

Comprehensive error handling with graceful degradation:

- **Plugin Creation Errors**: Catches `PluginException`, logs error, returns empty render
- **Context Errors**: Catches `ContextException`, logs warning, continues with available contexts
- **Form Errors**: Validates configuration, provides user-friendly error messages

## Development Patterns

### Functional Programming Over Loops

The codebase uses functional programming patterns with `array_map`, `array_filter`, and `array_reduce` throughout. See examples in the `blockForm()` and `passContextsToTargetBlock()` methods in `src/Plugin/Block/ProxyBlock.php`.

### Polymorphism Over Conditionals

Interface detection is used instead of string comparisons throughout the codebase. The proxy block checks for `ContextAwarePluginInterface` and `PluginFormInterface` implementations to determine target block capabilities.

### Early Returns (Guard Clauses)

Early returns are used consistently throughout the codebase to reduce nesting and improve readability. See examples in validation methods and helper functions in `src/Plugin/Block/ProxyBlock.php`.

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
├── composer.json                 # PHP dependencies and scripts
├── package.json                  # Node.js dependencies and scripts
├── phpstan.neon                  # PHPStan static analysis config
├── phpunit.xml.dist              # PHPUnit test configuration
├── cspell.json                   # Spell checking configuration
├── release.config.cjs            # Semantic release configuration
├── src/Plugin/Block/
│   └── ProxyBlock.php            # Main plugin implementation (661 lines)
└── tests/
    ├── dummy.css                 # Test CSS file for linting
    ├── dummy.js                  # Test JavaScript file for linting
    └── src/
        ├── Unit/                 # Unit tests
        ├── Kernel/               # Kernel tests
        ├── Functional/           # Functional tests
        └── FunctionalJavascript/ # JavaScript functional tests
```

## Development Workflow

1. **Make changes** to `ProxyBlock.php`
2. **Clear cache**: `ddev drush cr` (or `vendor/bin/drush cr`)
3. **Test changes** through Drupal's block placement UI
4. **Run tests**: `ddev exec vendor/bin/phpunit --group proxy_block`
5. **Validate code**: `ddev composer run-script lint:check` and `ddev exec npm run check`

### Code Quality Commands

The module includes composer and npm scripts for comprehensive code quality checks:

#### PHP Code Quality

```bash
# DDEV commands (when using DDEV local environment)
ddev composer run-script lint:check
ddev composer run-script lint:fix

# Alternative: Standard commands (when not using DDEV)
composer run-script lint:check
composer run-script lint:fix
```

#### JavaScript/CSS/Spelling Code Quality

```bash
# DDEV commands (when using DDEV local environment)
ddev exec npm run check                    # Run all checks (JS, CSS, spelling)
ddev exec npm run js:check                # JavaScript linting and formatting
ddev exec npm run js:fix                  # Fix JavaScript issues
ddev exec npm run stylelint:check         # CSS linting
ddev exec npm run cspell:check           # Spell checking
ddev exec npm run format:check           # Prettier formatting check
ddev exec npm run format:fix             # Fix formatting issues

# Alternative: Standard commands (when not using DDEV)
npm run check                    # Run all checks (JS, CSS, spelling)
npm run js:check                # JavaScript linting and formatting
npm run js:fix                  # Fix JavaScript issues
npm run stylelint:check         # CSS linting
npm run cspell:check           # Spell checking
npm run format:check           # Prettier formatting check
npm run format:fix             # Fix formatting issues
```

### Release Management

The module includes semantic release configuration:

```bash
# DDEV commands (when using DDEV local environment)
ddev composer run-script release

# Alternative: Standard commands (when not using DDEV)
composer run-script release
```

### Additional Development Tools

- **PHPStan**: Static analysis configuration available in `phpstan.neon`
- **PHPUnit**: Test configuration in `phpunit.xml.dist`
- **CSpell**: Spell checking configuration in `cspell.json`
- **Semantic Release**: Automated releases via `release.config.cjs`
- **ESLint/Prettier**: JavaScript code quality and formatting
- **Stylelint**: CSS code quality

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
