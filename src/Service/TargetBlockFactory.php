<?php

declare(strict_types=1);

namespace Drupal\proxy_block\Service;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;

/**
 * Factory service for creating and managing the target block instance.
 */
final class TargetBlockFactory {

  /**
   * Cached target block instance.
   */
  protected ?BlockPluginInterface $targetBlockInstance = NULL;

  /**
   * Constructs a new TargetBlockFactory object.
   *
   * @param \Drupal\Core\Block\BlockManagerInterface $blockManager
   *   The block manager.
   * @param \Drupal\proxy_block\Service\TargetBlockContextManager $contextManager
   *   The context manager.
   */
  public function __construct(
    protected BlockManagerInterface $blockManager,
    protected TargetBlockContextManager $contextManager,
  ) {
  }

  /**
   * Gets or creates the target block plugin instance.
   *
   * @param array $configuration
   *   The proxy block configuration.
   *
   * @return \Drupal\Core\Block\BlockPluginInterface|null
   *   The target block plugin or NULL if creation failed.
   */
  public function getTargetBlock(array $configuration): ?BlockPluginInterface {
    if ($this->targetBlockInstance === NULL) {
      $this->targetBlockInstance = $this->createTargetBlock($configuration);
    }
    return $this->targetBlockInstance;
  }

  /**
   * Creates the target block plugin instance.
   *
   * @param array $configuration
   *   The proxy block configuration.
   *
   * @return \Drupal\Core\Block\BlockPluginInterface|null
   *   The target block plugin or NULL if creation failed.
   */
  protected function createTargetBlock(array $configuration): ?BlockPluginInterface {
    $plugin_id = $configuration['target_block']['id'] ?? '';
    $block_config = $configuration['target_block']['config'] ?? [];

    if (empty($plugin_id)) {
      return NULL;
    }

    try {
      $target_block = $this->blockManager->createInstance($plugin_id, $block_config);

      if ($target_block instanceof ContextAwarePluginInterface) {
        $this->contextManager->applyContextsToTargetBlock($target_block);
      }

      return $target_block;
    }
    catch (PluginException $e) {
      return NULL;
    }
  }

}
