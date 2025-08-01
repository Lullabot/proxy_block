<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\Unit;

use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\DependencyInjection\Container;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextDefinitionInterface;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\proxy_block\Service\TargetBlockCacheManager;
use Drupal\proxy_block\Service\TargetBlockContextManager;
use Drupal\proxy_block\Service\TargetBlockFactory;
use Drupal\proxy_block\Service\TargetBlockFormProcessor;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Base class for proxy_block unit tests.
 *
 * This base class provides common mock objects, setUp patterns, and helper
 * methods to reduce code duplication across proxy_block unit tests.
 *
 * @group proxy_block
 */
abstract class ProxyBlockUnitTestBase extends UnitTestCase {

  /**
   * Mock block manager.
   */
  protected BlockManagerInterface|MockObject $blockManager;

  /**
   * Mock current user.
   */
  protected AccountProxyInterface|MockObject $currentUser;

  /**
   * Mock request stack.
   */
  protected RequestStack|MockObject $requestStack;

  /**
   * Mock string translation service.
   */
  protected TranslationInterface|MockObject $stringTranslation;

  /**
   * Mock target block factory.
   */
  protected TargetBlockFactory|MockObject $targetBlockFactory;

  /**
   * Mock target block form processor.
   */
  protected TargetBlockFormProcessor|MockObject $formProcessor;

  /**
   * Mock target block cache manager.
   */
  protected TargetBlockCacheManager|MockObject $cacheManager;

  /**
   * Mock target block context manager.
   */
  protected TargetBlockContextManager|MockObject $contextManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set up string translation with Drupal core's stub.
    $this->stringTranslation = $this->getStringTranslationStub();
    $this->setupDrupalContainer();

    // Initialize common mock objects.
    $this->setupCommonMocks();
  }

  /**
   * Sets up the Drupal container with essential services.
   *
   * This method configures the static Drupal container with services
   * commonly needed by proxy_block tests.
   */
  protected function setupDrupalContainer(): void {
    $container = new Container();
    $container->set('string_translation', $this->stringTranslation);
    \Drupal::setContainer($container);
  }

  /**
   * Sets up common mock objects used across proxy_block tests.
   *
   * This method initializes all the common service mocks that are
   * frequently used in proxy_block unit tests.
   */
  protected function setupCommonMocks(): void {
    $this->blockManager = $this->createMock(BlockManagerInterface::class);
    $this->currentUser = $this->createMock(AccountProxyInterface::class);
    $this->requestStack = $this->createMock(RequestStack::class);
    $this->targetBlockFactory = $this->createMock(TargetBlockFactory::class);
    $this->formProcessor = $this->createMock(TargetBlockFormProcessor::class);
    $this->cacheManager = $this->createMock(TargetBlockCacheManager::class);
    $this->contextManager = $this->createMock(TargetBlockContextManager::class);
  }

  /**
   * Creates a mock BlockPluginInterface with common configuration.
   *
   * @param string $plugin_id
   *   The plugin ID for the mock block.
   * @param array $configuration
   *   The configuration array for the mock block.
   * @param array $cache_contexts
   *   Optional cache contexts for the mock block.
   * @param array $cache_tags
   *   Optional cache tags for the mock block.
   * @param int $cache_max_age
   *   Optional cache max age for the mock block.
   *
   * @return \Drupal\Core\Block\BlockPluginInterface|\PHPUnit\Framework\MockObject\MockObject
   *   A configured mock block plugin.
   */
  protected function createMockBlockPlugin(
    string $plugin_id,
    array $configuration = [],
    array $cache_contexts = [],
    array $cache_tags = [],
    int $cache_max_age = 0,
  ): BlockPluginInterface|MockObject {
    $block = $this->createMock(BlockPluginInterface::class);

    $block->method('getPluginId')
      ->willReturn($plugin_id);

    $block->method('getConfiguration')
      ->willReturn($configuration);

    $block->method('getCacheContexts')
      ->willReturn($cache_contexts);

    $block->method('getCacheTags')
      ->willReturn($cache_tags);

    $block->method('getCacheMaxAge')
      ->willReturn($cache_max_age);

    return $block;
  }

  /**
   * Creates a mock FormStateInterface with common getValue behavior.
   *
   * @param array $value_map
   *   An associative array mapping form value keys to their expected values.
   *   Example: [['target_block', 'id'] => 'test_block'].
   * @param array $user_input
   *   Optional user input array for getUserInput() method.
   * @param array $triggering_element
   *   Optional triggering element array for getTriggeringElement() method.
   *
   * @return \Drupal\Core\Form\FormStateInterface|\PHPUnit\Framework\MockObject\MockObject
   *   A configured mock form state.
   */
  protected function createMockFormState(
    array $value_map = [],
    array $user_input = [],
    array $triggering_element = [],
  ): FormStateInterface|MockObject {
    $form_state = $this->createMock(FormStateInterface::class);

    if (!empty($value_map)) {
      $form_state->method('getValue')
        ->willReturnCallback(function ($key) use ($value_map) {
          return $value_map[$key] ?? NULL;
        });
    }

    if (!empty($user_input)) {
      $form_state->method('getUserInput')
        ->willReturn($user_input);
    }

    if (!empty($triggering_element)) {
      $form_state->method('getTriggeringElement')
        ->willReturn($triggering_element);
    }

    return $form_state;
  }

  /**
   * Creates a mock ContextInterface with basic configuration.
   *
   * @param mixed $context_value
   *   The value to return for getContextValue().
   * @param string $context_data_type
   *   Optional data type for the context.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface|\PHPUnit\Framework\MockObject\MockObject
   *   A configured mock context.
   */
  protected function createMockContext(
    mixed $context_value = NULL,
    string $context_data_type = 'any',
  ): ContextInterface|MockObject {
    $context = $this->createMock(ContextInterface::class);

    $context->method('getContextValue')
      ->willReturn($context_value);

    $context->method('getContextData')
      ->willReturn($context_value);

    return $context;
  }

  /**
   * Creates a mock ContextDefinitionInterface with basic configuration.
   *
   * @param bool $is_required
   *   Whether the context is required.
   * @param string $data_type
   *   The data type of the context.
   * @param string $label
   *   Optional label for the context definition.
   *
   * @return \Drupal\Core\Plugin\Context\ContextDefinitionInterface|\PHPUnit\Framework\MockObject\MockObject
   *   A configured mock context definition.
   */
  protected function createMockContextDefinition(
    bool $is_required = TRUE,
    string $data_type = 'entity:node',
    string $label = 'Test Context',
  ): ContextDefinitionInterface|MockObject {
    $definition = $this->createMock(ContextDefinitionInterface::class);

    $definition->method('isRequired')
      ->willReturn($is_required);

    $definition->method('getDataType')
      ->willReturn($data_type);

    $definition->method('getLabel')
      ->willReturn($label);

    return $definition;
  }

  /**
   * Creates a proxy block configuration array with common structure.
   *
   * @param string $target_plugin_id
   *   The target block plugin ID.
   * @param array $target_config
   *   The target block configuration.
   * @param array $context_mapping
   *   Optional context mapping for the target block.
   *
   * @return array
   *   A complete proxy block configuration array.
   */
  protected function createProxyBlockConfiguration(
    string $target_plugin_id = '',
    array $target_config = [],
    array $context_mapping = [],
  ): array {
    $config = [
      'target_block' => [
        'id' => $target_plugin_id,
        'config' => $target_config,
      ],
    ];

    if (!empty($context_mapping)) {
      $config['target_block']['config']['context_mapping'] = $context_mapping;
    }

    return $config;
  }

  /**
   * Layout Builder admin path pattern.
   */
  protected const LAYOUT_BUILDER_ADMIN_PATH = '/admin/structure/types/article/display/default/layout';

  /**
   * Creates common block definitions for testing.
   *
   * This method returns a standard set of block definitions that can be
   * used for testing block manager getDefinitions() calls.
   *
   * @param bool $exclude_proxy_block
   *   Whether to exclude the proxy_block_proxy from the definitions.
   *
   * @return array
   *   Array of block plugin definitions.
   */
  protected function createCommonBlockDefinitions(bool $exclude_proxy_block = TRUE): array {
    $definitions = [
      'system_branding_block' => [
        'id' => 'system_branding_block',
        'admin_label' => 'Site branding',
        'provider' => 'system',
        'category' => 'System',
      ],
      'user_login_block' => [
        'id' => 'user_login_block',
        'admin_label' => 'User login',
        'provider' => 'user',
        'category' => 'User',
      ],
      'node_title_block' => [
        'id' => 'node_title_block',
        'admin_label' => 'Page title',
        'provider' => 'core',
        'category' => 'Content',
      ],
    ];

    if (!$exclude_proxy_block) {
      $definitions['proxy_block_proxy'] = [
        'id' => 'proxy_block_proxy',
        'admin_label' => 'Proxy Block',
        'provider' => 'proxy_block',
        'category' => 'A/B Testing',
      ];
    }

    return $definitions;
  }

  /**
   * Creates a system branding block definition for testing.
   *
   * @return array
   *   Block definition array for system branding block.
   */
  protected function createSystemBrandingDefinition(): array {
    return [
      'admin_label' => 'Site branding',
      'id' => 'system_branding_block',
      'provider' => 'system',
      'category' => 'System',
    ];
  }

  /**
   * Creates a mock request with Layout Builder admin path.
   *
   * @return \Symfony\Component\HttpFoundation\Request|\PHPUnit\Framework\MockObject\MockObject
   *   Mock request configured for Layout Builder admin mode.
   */
  protected function createLayoutBuilderAdminRequest(): MockObject {
    $request = $this->createMock(Request::class);
    $request->method('getPathInfo')
      ->willReturn(static::LAYOUT_BUILDER_ADMIN_PATH);
    return $request;
  }

  /**
   * Asserts that a value is a TranslatableMarkup with expected string.
   *
   * This helper method checks both that the value is a TranslatableMarkup
   * instance and that its untranslated string matches the expected value.
   *
   * @param mixed $actual
   *   The actual value to check.
   * @param string $expected_string
   *   The expected untranslated string.
   * @param string $message
   *   Optional message for the assertion.
   */
  protected function assertTranslatableMarkup(mixed $actual, string $expected_string, string $message = ''): void {
    $this->assertInstanceOf(TranslatableMarkup::class, $actual, $message);
    $this->assertEquals($expected_string, $actual->getUntranslatedString(), $message);
  }

  /**
   * Invokes a protected or private method on an object using reflection.
   *
   * This helper method uses reflection to call protected or private methods
   * during unit testing.
   *
   * @param object $object
   *   The object instance.
   * @param string $method_name
   *   The name of the method to invoke.
   * @param array $parameters
   *   Optional parameters to pass to the method.
   *
   * @return mixed
   *   The method's return value.
   */
  protected function invokeMethod(object $object, string $method_name, array $parameters = []): mixed {
    $reflection = new \ReflectionClass($object);
    $method = $reflection->getMethod($method_name);
    $method->setAccessible(TRUE);
    return $method->invokeArgs($object, $parameters);
  }

  /**
   * Sets a protected or private property on an object using reflection.
   *
   * This helper method uses reflection to set protected or private properties
   * during unit testing.
   *
   * @param object $object
   *   The object instance.
   * @param string $property_name
   *   The name of the property to set.
   * @param mixed $value
   *   The value to set.
   */
  protected function setProperty(object $object, string $property_name, mixed $value): void {
    $reflection = new \ReflectionClass($object);
    $property = $reflection->getProperty($property_name);
    $property->setAccessible(TRUE);
    $property->setValue($object, $value);
  }

  /**
   * Gets a protected or private property from an object using reflection.
   *
   * This helper method uses reflection to get protected or private properties
   * during unit testing.
   *
   * @param object $object
   *   The object instance.
   * @param string $property_name
   *   The name of the property to get.
   *
   * @return mixed
   *   The property value.
   */
  protected function getProperty(object $object, string $property_name): mixed {
    $reflection = new \ReflectionClass($object);
    $property = $reflection->getProperty($property_name);
    $property->setAccessible(TRUE);
    return $property->getValue($object);
  }

  /**
   * Creates a test block stub with configurable form capabilities.
   *
   * This method provides a convenient way to create configurable block mocks.
   *
   * @param string $plugin_id
   *   The plugin ID for the test block.
   * @param array $config_form
   *   The configuration form array to return.
   * @param array $configuration
   *   The block configuration.
   *
   * @return \Drupal\Tests\proxy_block\Unit\TestBlockStub
   *   A test block stub with configurable form capabilities.
   */
  protected function createConfigurableBlockMock(
    string $plugin_id,
    array $config_form = [],
    array $configuration = [],
  ): TestBlockStub {
    $stub = new TestBlockStub($plugin_id, [], $config_form);
    if (!empty($configuration)) {
      $stub->setConfiguration($configuration);
    }
    return $stub;
  }

  /**
   * Creates a test block stub with context-aware capabilities.
   *
   * This method provides a convenient way to create context-aware block mocks.
   *
   * @param string $plugin_id
   *   The plugin ID for the test block.
   * @param array $context_definitions
   *   The context definitions to return.
   * @param array $context_mapping
   *   Initial context mapping.
   * @param array $configuration
   *   The block configuration.
   *
   * @return \Drupal\Tests\proxy_block\Unit\TestBlockStub
   *   A test block stub with context-aware capabilities.
   */
  protected function createContextAwareBlockMock(
    string $plugin_id,
    array $context_definitions = [],
    array $context_mapping = [],
    array $configuration = [],
  ): TestBlockStub {
    $stub = new TestBlockStub($plugin_id, $context_definitions);
    if (!empty($context_mapping)) {
      $stub->setContextMapping($context_mapping);
    }
    if (!empty($configuration)) {
      $stub->setConfiguration($configuration);
    }
    return $stub;
  }

  /**
   * Creates a test block stub with configurable and context-aware capabilities.
   *
   * This method provides a convenient way to create full-featured block mocks
   * with both configuration forms and context awareness.
   *
   * @param string $plugin_id
   *   The plugin ID for the test block.
   * @param array $config_form
   *   The configuration form array to return.
   * @param array $context_definitions
   *   The context definitions to return.
   * @param array $context_mapping
   *   Initial context mapping.
   * @param array $configuration
   *   The block configuration.
   *
   * @return \Drupal\Tests\proxy_block\Unit\TestBlockStub
   *   A test block stub with full capabilities.
   */
  protected function createFullFeaturedBlockMock(
    string $plugin_id,
    array $config_form = [],
    array $context_definitions = [],
    array $context_mapping = [],
    array $configuration = [],
  ): TestBlockStub {
    $stub = new TestBlockStub($plugin_id, $context_definitions, $config_form);
    if (!empty($context_mapping)) {
      $stub->setContextMapping($context_mapping);
    }
    if (!empty($configuration)) {
      $stub->setConfiguration($configuration);
    }
    return $stub;
  }

}
