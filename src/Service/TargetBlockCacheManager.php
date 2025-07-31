<?php

declare(strict_types=1);

namespace Drupal\proxy_block\Service;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Manages cache metadata for the proxy block and its target block.
 */
class TargetBlockCacheManager {

  /**
   * Bubbles cache metadata from the target block to the render array.
   *
   * @param array &$build
   *   The render array to apply metadata to.
   * @param \Drupal\Core\Cache\CacheableDependencyInterface $target_block
   *   The target block plugin.
   * @param \Drupal\Core\Cache\CacheableDependencyInterface $proxy_block
   *   The proxy block plugin.
   */
  public function bubbleTargetBlockCacheMetadata(
    array &$build,
    CacheableDependencyInterface $target_block,
    CacheableDependencyInterface $proxy_block,
  ): void {
    $cache_metadata = CacheableMetadata::createFromRenderArray($build);

    // Add target block's cache contexts, tags, and max-age.
    $cache_metadata->addCacheContexts($target_block->getCacheContexts());
    $cache_metadata->addCacheTags($target_block->getCacheTags());
    $cache_metadata->setCacheMaxAge(
      Cache::mergeMaxAges($cache_metadata->getCacheMaxAge(), $target_block->getCacheMaxAge())
    );

    // Add proxy block's own cache metadata.
    $cache_metadata->addCacheContexts($proxy_block->getCacheContexts());
    $cache_metadata->addCacheTags($proxy_block->getCacheTags());
    $cache_metadata->setCacheMaxAge(
      Cache::mergeMaxAges($cache_metadata->getCacheMaxAge(), $proxy_block->getCacheMaxAge())
    );

    $cache_metadata->applyTo($build);
  }

  /**
   * Merges the cache contexts of the proxy and target blocks.
   *
   * @param \Drupal\Core\Block\BlockPluginInterface|null $target_block
   *   The target block plugin.
   * @param string[] $parent_contexts
   *   The parent cache contexts.
   *
   * @return string[]
   *   The merged cache contexts.
   */
  public function getCacheContexts(?BlockPluginInterface $target_block, array $parent_contexts): array {
    if (!$target_block) {
      return $parent_contexts;
    }
    return Cache::mergeContexts($parent_contexts, $target_block->getCacheContexts());
  }

  /**
   * Merges the cache tags of the proxy and target blocks.
   *
   * @param \Drupal\Core\Block\BlockPluginInterface|null $target_block
   *   The target block plugin.
   * @param string[] $parent_tags
   *   The parent cache tags.
   *
   * @return string[]
   *   The merged cache tags.
   */
  public function getCacheTags(?BlockPluginInterface $target_block, array $parent_tags): array {
    if (!$target_block) {
      return $parent_tags;
    }
    return Cache::mergeTags($parent_tags, $target_block->getCacheTags());
  }

  /**
   * Merges the cache max-age of the proxy and target blocks.
   *
   * @param \Drupal\Core\Block\BlockPluginInterface|null $target_block
   *   The target block plugin.
   * @param int $parent_max_age
   *   The parent cache max-age.
   *
   * @return int
   *   The merged cache max-age.
   */
  public function getCacheMaxAge(?BlockPluginInterface $target_block, int $parent_max_age): int {
    if (!$target_block) {
      return $parent_max_age;
    }
    return Cache::mergeMaxAges($parent_max_age, $target_block->getCacheMaxAge());
  }

}
