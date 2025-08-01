<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\Unit;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Cache\Cache;
use Drupal\proxy_block\Service\TargetBlockCacheManager;

/**
 * Tests the TargetBlockCacheManager service.
 *
 * @group proxy_block
 *
 * @coversDefaultClass \Drupal\proxy_block\Service\TargetBlockCacheManager
 */
class TargetBlockCacheManagerTest extends ProxyBlockUnitTestBase {

  /**
   * The target block cache manager under test.
   */
  private TargetBlockCacheManager $testCacheManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->testCacheManager = new TargetBlockCacheManager();
  }

  /**
   * Tests getCacheContexts with null target block.
   *
   * @covers ::getCacheContexts
   */
  public function testGetCacheContextsWithNullTargetBlock(): void {
    $parent_contexts = ['theme', 'languages', 'user'];

    $result = $this->testCacheManager->getCacheContexts(NULL, $parent_contexts);

    $this->assertEquals($parent_contexts, $result);
  }

  /**
   * Tests getCacheTags with valid target block.
   *
   * @covers ::getCacheTags
   */
  public function testGetCacheTagsWithValidTargetBlock(): void {
    $target_block = $this->createMock(BlockPluginInterface::class);
    $target_block->method('getCacheTags')
      ->willReturn(['node:1', 'user:2']);

    $parent_tags = ['config:block.block.test', 'config:system.theme'];

    $result = $this->testCacheManager->getCacheTags($target_block, $parent_tags);

    $expected = ['config:block.block.test', 'config:system.theme', 'node:1', 'user:2'];
    sort($expected);
    sort($result);
    $this->assertEquals($expected, $result);
  }

  /**
   * Tests getCacheTags with null target block.
   *
   * @covers ::getCacheTags
   */
  public function testGetCacheTagsWithNullTargetBlock(): void {
    $parent_tags = ['config:block.block.test', 'config:system.theme'];

    $result = $this->testCacheManager->getCacheTags(NULL, $parent_tags);

    $this->assertEquals($parent_tags, $result);
  }

  /**
   * Tests getCacheTags with empty parent tags.
   *
   * @covers ::getCacheTags
   */
  public function testGetCacheTagsWithEmptyParentTags(): void {
    $target_block = $this->createMock(BlockPluginInterface::class);
    $target_block->method('getCacheTags')
      ->willReturn(['entity:node', 'entity:user']);

    $result = $this->testCacheManager->getCacheTags($target_block, []);

    $expected = ['entity:node', 'entity:user'];
    sort($expected);
    sort($result);
    $this->assertEquals($expected, $result);
  }

  /**
   * Tests getCacheTags with duplicate tags.
   *
   * @covers ::getCacheTags
   */
  public function testGetCacheTagsWithDuplicateTags(): void {
    $target_block = $this->createMock(BlockPluginInterface::class);
    $target_block->method('getCacheTags')
      ->willReturn(['node:1', 'config:block.block.test']);

    $parent_tags = ['config:block.block.test', 'user:current'];

    $result = $this->testCacheManager->getCacheTags($target_block, $parent_tags);

    // Cache::mergeTags should handle deduplication.
    $expected = ['config:block.block.test', 'user:current', 'node:1'];
    sort($expected);
    sort($result);
    $this->assertEquals($expected, $result);
  }

  /**
   * Tests getCacheMaxAge with the valid target block.
   *
   * @covers ::getCacheMaxAge
   */
  public function testGetCacheMaxAgeWithValidTargetBlock(): void {
    $target_block = $this->createMock(BlockPluginInterface::class);
    $target_block->method('getCacheMaxAge')
      ->willReturn(1800);

    $parent_max_age = 3600;

    $result = $this->testCacheManager->getCacheMaxAge($target_block, $parent_max_age);

    // Should return the minimum value.
    $this->assertEquals(1800, $result);
  }

  /**
   * Tests getCacheMaxAge with the null target block.
   *
   * @covers ::getCacheMaxAge
   */
  public function testGetCacheMaxAgeWithNullTargetBlock(): void {
    $parent_max_age = 3600;

    $result = $this->testCacheManager->getCacheMaxAge(NULL, $parent_max_age);

    $this->assertEquals($parent_max_age, $result);
  }

  /**
   * Tests getCacheMaxAge with the permanent cache.
   *
   * @covers ::getCacheMaxAge
   */
  public function testGetCacheMaxAgeWithPermanentCache(): void {
    $target_block = $this->createMock(BlockPluginInterface::class);
    $target_block->method('getCacheMaxAge')
      ->willReturn(Cache::PERMANENT);

    $parent_max_age = 1200;

    $result = $this->testCacheManager->getCacheMaxAge($target_block, $parent_max_age);

    // When one is permanent, should return the other's value.
    $this->assertEquals($parent_max_age, $result);
  }

  /**
   * Tests getCacheMaxAge when parent has permanent cache.
   *
   * @covers ::getCacheMaxAge
   */
  public function testGetCacheMaxAgeWithPermanentParent(): void {
    $target_block = $this->createMock(BlockPluginInterface::class);
    $target_block->method('getCacheMaxAge')
      ->willReturn(600);

    $parent_max_age = Cache::PERMANENT;

    $result = $this->testCacheManager->getCacheMaxAge($target_block, $parent_max_age);

    // When parent is permanent, should return target's value.
    $this->assertEquals(600, $result);
  }

  /**
   * Tests getCacheMaxAge when both are permanent.
   *
   * @covers ::getCacheMaxAge
   */
  public function testGetCacheMaxAgeWithBothPermanent(): void {
    $target_block = $this->createMock(BlockPluginInterface::class);
    $target_block->method('getCacheMaxAge')
      ->willReturn(Cache::PERMANENT);

    $parent_max_age = Cache::PERMANENT;

    $result = $this->testCacheManager->getCacheMaxAge($target_block, $parent_max_age);

    $this->assertEquals(Cache::PERMANENT, $result);
  }

  /**
   * Tests getCacheMaxAge with zero cache (no cache).
   *
   * @covers ::getCacheMaxAge
   */
  public function testGetCacheMaxAgeWithZeroCache(): void {
    $target_block = $this->createMock(BlockPluginInterface::class);
    $target_block->method('getCacheMaxAge')
      ->willReturn(0);

    $parent_max_age = 3600;

    $result = $this->testCacheManager->getCacheMaxAge($target_block, $parent_max_age);

    // Should return 0 (no cache) when the target block has no cache.
    $this->assertEquals(0, $result);
  }

}
