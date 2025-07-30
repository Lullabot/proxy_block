<?php

declare(strict_types=1);

namespace Drupal\proxy_block\Service;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Psr\Log\LoggerInterface;

/**
 * Factory service for creating and managing the target block instance.
 */
final class TargetBlockFactory {

  /**
   * The block manager service.
   */
  protected BlockManagerInterface $blockManager;

  /**
   * The target block context manager.
   */
  protected TargetBlockContextManager $contextManager;

  /**
   * The logger service.
   */
  protected LoggerInterface $logger;

  /**
   * Cached target block instance.
   */
  protected ?BlockPluginInterface $targetBlockInstance = NULL;

  /**
   * Constructs a new TargetBlockFactory object.
   *
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager service.
   * @param \Drupal\proxy_block\Service\TargetBlockContextManager $context_manager
   *   The target block context manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(
    BlockManagerInterface $block_manager,
    TargetBlockContextManager $context_manager,
    LoggerInterface $logger,
  ) {
    $this->blockManager = $block_manager;
    $this->contextManager = $context_manager;
    $this->logger = $logger;
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

    $this->logger->debug('Creating target block @plugin with config: @config', [
      '@plugin' => $plugin_id,
      '@config' => json_encode($block_config),
    ]);

    try {
      $target_block = $this->blockManager->createInstance($plugin_id, $block_config);

      if ($target_block instanceof ContextAwarePluginInterface) {
        $saved_context_mapping = $target_block->getContextMapping();
        $this->logger->debug('Target block loaded with context mapping: @mapping', [
          '@mapping' => json_encode($saved_context_mapping),
        ]);

        $this->contextManager->applyContextsToTargetBlock($target_block);
      }

      return $target_block;
    }
    catch (PluginException $e) {
      $this->logger->warning('Failed to create target block @plugin: @message', [
        '@plugin' => $plugin_id,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

}
