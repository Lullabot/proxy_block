<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\Unit;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\proxy_block\Service\TargetBlockContextManager;
use Drupal\proxy_block\Service\TargetBlockFactory;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests the TargetBlockFactory service.
 *
 * @group proxy_block
 *
 * @coversDefaultClass \Drupal\proxy_block\Service\TargetBlockFactory
 */
class TargetBlockFactoryTest extends ProxyBlockUnitTestBase {

  /**
   * The target block factory under test.
   */
  private TargetBlockFactory $factory;

  /**
   * Helper method to invoke the protected generateCacheKey method.
   *
   * @param array $configuration
   *   The configuration array.
   *
   * @return string
   *   The generated cache key.
   */
  private function invokeGenerateCacheKey(array $configuration): string {
    $reflection = new \ReflectionClass($this->factory);
    $method = $reflection->getMethod('generateCacheKey');
    $method->setAccessible(TRUE);
    return $method->invoke($this->factory, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->blockManager = $this->createMock(BlockManagerInterface::class);
    $this->contextManager = $this->createMock(TargetBlockContextManager::class);

    $this->factory = new TargetBlockFactory(
      $this->blockManager,
      $this->contextManager
    );
  }

  /**
   * Tests getTargetBlock with valid configuration.
   *
   * @covers ::getTargetBlock
   * @covers ::createTargetBlock
   * @covers ::generateCacheKey
   */
  public function testGetTargetBlockWithValidConfiguration(): void {
    $configuration = [
      'target_block' => [
        'id' => 'test_block',
        'config' => ['setting' => 'value'],
      ],
    ];

    $target_block = $this->createMock(BlockPluginInterface::class);

    $this->blockManager
      ->expects($this->once())
      ->method('createInstance')
      ->with('test_block', ['setting' => 'value'])
      ->willReturn($target_block);

    $result = $this->factory->getTargetBlock($configuration);

    $this->assertSame($target_block, $result);
  }

  /**
   * Tests getTargetBlock caching behavior.
   *
   * @covers ::getTargetBlock
   * @covers ::generateCacheKey
   */
  public function testGetTargetBlockCachingBehavior(): void {
    $configuration = [
      'target_block' => [
        'id' => 'cached_block',
        'config' => ['cached_setting' => 'cached_value'],
      ],
    ];

    $target_block = $this->createMock(BlockPluginInterface::class);

    // Should only create the instance once due to caching.
    $this->blockManager
      ->expects($this->once())
      ->method('createInstance')
      ->with('cached_block', ['cached_setting' => 'cached_value'])
      ->willReturn($target_block);

    // First call - creates the block.
    $result1 = $this->factory->getTargetBlock($configuration);
    $this->assertSame($target_block, $result1);

    // Second call - returns cached instance.
    $result2 = $this->factory->getTargetBlock($configuration);
    $this->assertSame($target_block, $result2);

    // Verify same instance is returned.
    $this->assertSame($result1, $result2);
  }

  /**
   * Tests getTargetBlock with various invalid configurations.
   *
   * @dataProvider provideInvalidConfigurations
   *
   * @covers ::getTargetBlock
   * @covers ::createTargetBlock
   */
  public function testGetTargetBlockWithInvalidConfiguration(array $configuration): void {
    $this->blockManager
      ->expects($this->never())
      ->method('createInstance');

    $result = $this->factory->getTargetBlock($configuration);

    $this->assertNull($result);
  }

  /**
   * Provides invalid configurations for testing getTargetBlock.
   *
   * @return array<string, array<array>>
   *   Array of test cases with invalid configurations.
   */
  public static function provideInvalidConfigurations(): array {
    return [
      'empty configuration' => [
        [],
      ],
      'missing plugin id' => [
        [
          'target_block' => [
            'config' => ['setting' => 'value'],
          ],
        ],
      ],
      'empty plugin id' => [
        [
          'target_block' => [
            'id' => '',
            'config' => ['setting' => 'value'],
          ],
        ],
      ],
    ];
  }

  /**
   * Tests createTargetBlock with PluginException.
   *
   * @covers ::getTargetBlock
   * @covers ::createTargetBlock
   */
  public function testCreateTargetBlockWithPluginException(): void {
    $configuration = [
      'target_block' => [
        'id' => 'invalid_block',
        'config' => [],
      ],
    ];

    $this->blockManager
      ->expects($this->once())
      ->method('createInstance')
      ->with('invalid_block', [])
      ->willThrowException(new PluginException('Plugin not found'));

    $result = $this->factory->getTargetBlock($configuration);

    $this->assertNull($result);
  }

  /**
   * Tests createTargetBlock with context-aware plugin.
   *
   * @covers ::getTargetBlock
   * @covers ::createTargetBlock
   */
  public function testCreateTargetBlockWithContextAwarePlugin(): void {
    $configuration = [
      'target_block' => [
        'id' => 'context_aware_block',
        'config' => ['context_setting' => 'context_value'],
      ],
    ];

    // Create a mock that implements both interfaces.
    $target_block = $this->createMock(TestContextAwareBlockInterface::class);

    $this->blockManager
      ->expects($this->once())
      ->method('createInstance')
      ->with('context_aware_block', ['context_setting' => 'context_value'])
      ->willReturn($target_block);

    $this->contextManager
      ->expects($this->once())
      ->method('applyContextsToTargetBlock')
      ->with($target_block);

    $result = $this->factory->getTargetBlock($configuration);

    $this->assertSame($target_block, $result);
  }

  /**
   * Tests createTargetBlock with non-context-aware plugin.
   *
   * @covers ::getTargetBlock
   * @covers ::createTargetBlock
   */
  public function testCreateTargetBlockWithNonContextAwarePlugin(): void {
    $configuration = [
      'target_block' => [
        'id' => 'simple_block',
        'config' => ['simple_setting' => 'simple_value'],
      ],
    ];

    $target_block = $this->createMock(BlockPluginInterface::class);

    $this->blockManager
      ->expects($this->once())
      ->method('createInstance')
      ->with('simple_block', ['simple_setting' => 'simple_value'])
      ->willReturn($target_block);

    // Should not apply contexts to non-context-aware blocks.
    $this->contextManager
      ->expects($this->never())
      ->method('applyContextsToTargetBlock');

    $result = $this->factory->getTargetBlock($configuration);

    $this->assertSame($target_block, $result);
  }

  /**
   * Tests generateCacheKey produces consistent keys.
   *
   * @covers ::generateCacheKey
   */
  public function testGenerateCacheKeyConsistency(): void {
    $configuration1 = [
      'target_block' => [
        'id' => 'test_block',
        'config' => ['setting' => 'value'],
      ],
    ];

    $configuration2 = [
      'target_block' => [
        'id' => 'test_block',
        'config' => ['setting' => 'value'],
      ],
    ];

    $key1 = $this->invokeGenerateCacheKey($configuration1);
    $key2 = $this->invokeGenerateCacheKey($configuration2);

    $this->assertEquals($key1, $key2);
    $this->assertIsString($key1);
    // SHA256 hash length.
    $this->assertEquals(64, strlen($key1));
  }

  /**
   * Tests generateCacheKey produces different keys for different configs.
   *
   * @covers ::generateCacheKey
   */
  public function testGenerateCacheKeyDifferentConfigurations(): void {
    $configuration1 = [
      'target_block' => [
        'id' => 'block_one',
        'config' => ['setting' => 'value1'],
      ],
    ];

    $configuration2 = [
      'target_block' => [
        'id' => 'block_two',
        'config' => ['setting' => 'value2'],
      ],
    ];

    $key1 = $this->invokeGenerateCacheKey($configuration1);
    $key2 = $this->invokeGenerateCacheKey($configuration2);

    $this->assertNotEquals($key1, $key2);
    $this->assertIsString($key1);
    $this->assertIsString($key2);
  }

  /**
   * Tests generateCacheKey with missing target_block configuration.
   *
   * @covers ::generateCacheKey
   */
  public function testGenerateCacheKeyWithMissingTargetBlock(): void {
    $configuration = [];

    $key = $this->invokeGenerateCacheKey($configuration);

    $this->assertIsString($key);
    // SHA256 hash length.
    $this->assertEquals(64, strlen($key));
  }

  /**
   * Tests different cache keys for different configurations don't collide.
   *
   * @covers ::getTargetBlock
   * @covers ::generateCacheKey
   */
  public function testDifferentConfigurationsCreateDifferentCacheEntries(): void {
    $configuration1 = [
      'target_block' => [
        'id' => 'block_one',
        'config' => [],
      ],
    ];

    $configuration2 = [
      'target_block' => [
        'id' => 'block_two',
        'config' => [],
      ],
    ];

    $block1 = $this->createMock(BlockPluginInterface::class);
    $block2 = $this->createMock(BlockPluginInterface::class);

    $this->blockManager
      ->expects($this->exactly(2))
      ->method('createInstance')
      ->willReturnCallback(function ($plugin_id, $config) use ($block1, $block2) {
        if ($plugin_id === 'block_one') {
          return $block1;
        }
        if ($plugin_id === 'block_two') {
          return $block2;
        }
        throw new PluginException('Unknown plugin');
      });

    $result1 = $this->factory->getTargetBlock($configuration1);
    $result2 = $this->factory->getTargetBlock($configuration2);

    $this->assertSame($block1, $result1);
    $this->assertSame($block2, $result2);
    $this->assertNotSame($result1, $result2);
  }

  /**
   * Tests createTargetBlock with missing config uses empty array.
   *
   * @covers ::createTargetBlock
   */
  public function testCreateTargetBlockWithMissingConfig(): void {
    $configuration = [
      'target_block' => [
        'id' => 'minimal_block',
      ],
    ];

    $target_block = $this->createMock(BlockPluginInterface::class);

    $this->blockManager
      ->expects($this->once())
      ->method('createInstance')
      ->with('minimal_block', [])
      ->willReturn($target_block);

    $result = $this->factory->getTargetBlock($configuration);

    $this->assertSame($target_block, $result);
  }

  /**
   * Tests factory instance caching across multiple different calls.
   *
   * @covers ::getTargetBlock
   */
  public function testFactoryInstanceCachingBehavior(): void {
    $config1 = [
      'target_block' => [
        'id' => 'cached_block_1',
        'config' => ['setting1' => 'value1'],
      ],
    ];

    $config2 = [
      'target_block' => [
        'id' => 'cached_block_2',
        'config' => ['setting2' => 'value2'],
      ],
    ];

    $block1 = $this->createMock(BlockPluginInterface::class);
    $block2 = $this->createMock(BlockPluginInterface::class);

    $this->blockManager
      ->expects($this->exactly(2))
      ->method('createInstance')
      ->willReturnCallback(function ($plugin_id, $config) use ($block1, $block2) {
        if ($plugin_id === 'cached_block_1') {
          return $block1;
        }
        if ($plugin_id === 'cached_block_2') {
          return $block2;
        }
        throw new PluginException('Unknown plugin');
      });

    // First calls - create instances.
    $result1a = $this->factory->getTargetBlock($config1);
    $result2a = $this->factory->getTargetBlock($config2);

    // Second calls - return cached instances.
    $result1b = $this->factory->getTargetBlock($config1);
    $result2b = $this->factory->getTargetBlock($config2);

    // Verify caching works for each configuration.
    $this->assertSame($block1, $result1a);
    $this->assertSame($block2, $result2a);
    $this->assertSame($result1a, $result1b);
    $this->assertSame($result2a, $result2b);
    $this->assertNotSame($result1a, $result2a);
  }

}

/**
 * Test interface for context-aware blocks.
 *
 * This interface combines BlockPluginInterface and ContextAwarePluginInterface
 * for testing purposes, since we can't create a mock that implements multiple
 * interfaces directly in some PHPUnit versions.
 */
interface TestContextAwareBlockInterface extends BlockPluginInterface, ContextAwarePluginInterface {
}
