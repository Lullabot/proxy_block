<?php

declare(strict_types=1);

namespace Drupal\proxy_block\Service;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * Manages context for the target block.
 */
final class TargetBlockContextManager {

  use StringTranslationTrait;
  use LoggerTrait;

  /**
   * The context repository.
   */
  protected ContextRepositoryInterface $contextRepository;

  /**
   * The context handler.
   */
  protected ContextHandlerInterface $contextHandler;

  /**
   * The logger.
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a new TargetBlockContextManager object.
   *
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $context_repository
   *   The context repository.
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $context_handler
   *   The context handler.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    ContextRepositoryInterface $context_repository,
    ContextHandlerInterface $context_handler,
    LoggerInterface $logger,
  ) {
    $this->contextRepository = $context_repository;
    $this->contextHandler = $context_handler;
    $this->logger = $logger;
  }

  /**
   * Gets all gathered contexts like Layout Builder does.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   Array of available contexts.
   */
  public function getGatheredContexts(): array {
    $available_context_ids = $this->contextRepository->getAvailableContexts();
    $contexts = $this->contextRepository->getRuntimeContexts(array_keys($available_context_ids));

    $this->log('debug', 'Raw available context IDs: @ids', [
      '@ids' => implode(', ', array_keys($available_context_ids)),
    ]);

    $this->log('debug', 'Runtime contexts keys: @keys', [
      '@keys' => implode(', ', array_keys($contexts)),
    ]);

    $populated_contexts = $contexts;

    $this->log('debug', 'Populated contexts keys: @keys', [
      '@keys' => implode(', ', array_keys($populated_contexts)),
    ]);

    if (!isset($populated_contexts['view_mode'])) {
      $populated_contexts['view_mode'] = $this->createDefaultViewModeContext();
    }

    return $populated_contexts;
  }

  /**
   * Creates a default view_mode context for FieldBlocks.
   *
   * @return \Drupal\Core\Plugin\Context\Context
   *   A view_mode context with \'default\' value.
   */
  protected function createDefaultViewModeContext() {
    $context_definition = new ContextDefinition('string', $this->t('View mode'));
    return new Context($context_definition, 'default');
  }

  /**
   * Applies available contexts to the target block using gathered contexts.
   *
   * @param \Drupal\Core\Plugin\ContextAwarePluginInterface $target_block
   *   The target block plugin.
   */
  public function applyContextsToTargetBlock(ContextAwarePluginInterface $target_block): void {
    try {
      $gathered_contexts = $this->getGatheredContexts();
      $context_mapping = $target_block->getContextMapping();

      $this->log('debug', 'Applying contexts. Target block: @plugin', [
        '@plugin' => $target_block->getPluginId(),
      ]);

      $this->log('debug', 'Available contexts: @contexts', [
        '@contexts' => implode(', ', array_keys($gathered_contexts)),
      ]);

      $this->log('debug', 'Current context mapping: @mapping', [
        '@mapping' => json_encode($context_mapping),
      ]);

      $target_context_definitions = $target_block->getContextDefinitions();
      $missing_required_contexts = [];

      foreach ($target_context_definitions as $context_name => $context_definition) {
        if ($context_definition->isRequired() && !isset($context_mapping[$context_name])) {
          $missing_required_contexts[] = $context_name;
        }
      }

      if (empty($context_mapping) || !empty($missing_required_contexts)) {
        $this->log('debug', 'Missing required contexts: @missing', [
          '@missing' => implode(', ', $missing_required_contexts),
        ]);

        $automatic_mapping = $this->generateAutomaticContextMapping($target_block, $gathered_contexts);
        if (!empty($automatic_mapping)) {
          $merged_mapping = $context_mapping + $automatic_mapping;
          $target_block->setContextMapping($merged_mapping);
          $context_mapping = $merged_mapping;

          $this->log('debug', 'Set merged context mapping: @mapping', [
            '@mapping' => json_encode($merged_mapping),
          ]);
        }
      }

      if (!empty($gathered_contexts) && !empty($context_mapping)) {
        $resolved_mapping = [];
        foreach ($context_mapping as $target_context => $source_context_id) {
          $clean_context_id = ltrim($source_context_id, '@');

          $this->log('debug', 'Resolving @target -> @source (clean: @clean)', [
            '@target' => $target_context,
            '@source' => $source_context_id,
            '@clean' => $clean_context_id,
          ]);

          if (isset($gathered_contexts[$clean_context_id])) {
            $resolved_mapping[$target_context] = $clean_context_id;
            $this->log('debug', 'Resolved using clean ID');
          }
          elseif (isset($gathered_contexts[$source_context_id])) {
            $resolved_mapping[$target_context] = $source_context_id;
            $this->log('debug', 'Resolved using original ID');
          }
          else {
            $this->log('warning', 'Cannot resolve context @context (available: @available)', [
              '@context' => $source_context_id,
              '@available' => implode(', ', array_keys($gathered_contexts)),
            ]);
          }
        }

        $this->log('debug', 'Resolved context mapping: @mapping', [
          '@mapping' => json_encode($resolved_mapping),
        ]);

        if (!empty($resolved_mapping)) {
          $this->log('debug', 'About to apply contexts - gathered_contexts keys: @keys, resolved_mapping: @mapping', [
            '@keys' => implode(', ', array_keys($gathered_contexts)),
            '@mapping' => json_encode($resolved_mapping),
          ]);

          try {
            $this->contextHandler->applyContextMapping($target_block, $gathered_contexts, $resolved_mapping);
            $this->log('debug', 'Applied context mapping successfully');
          }
          catch (\Exception $e) {
            $this->log('error', 'Context application failed: @message', [
              '@message' => $e->getMessage(),
            ]);
          }

          $applied_contexts = $target_block->getContexts();
          $this->log('debug', 'Target block now has contexts: @contexts', [
            '@contexts' => implode(', ', array_keys($applied_contexts)),
          ]);
        }
      }
      else {
        $this->log('warning', 'No contexts or mapping available. Contexts: @context_count, Mapping: @mapping_count', [
          '@context_count' => count($gathered_contexts),
          '@mapping_count' => count($context_mapping),
        ]);
      }
    }
    catch (\Exception $e) {
      $this->log('warning', 'Failed to apply contexts to target block: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Generates automatic context mapping for target blocks missing mappings.
   *
   * @param \Drupal\Core\Plugin\ContextAwarePluginInterface $target_block
   *   The target block plugin.
   * @param array $available_contexts
   *   Available contexts.
   *
   * @return array
   *   Context mapping array.
   */
  protected function generateAutomaticContextMapping(ContextAwarePluginInterface $target_block, array $available_contexts): array {
    $context_mapping = [];
    $target_context_definitions = $target_block->getContextDefinitions();

    foreach ($target_context_definitions as $context_name => $context_definition) {
      $matching_contexts = $this->contextHandler->getMatchingContexts($available_contexts, $context_definition);

      if (!empty($matching_contexts)) {
        $context_mapping[$context_name] = array_keys($matching_contexts)[0];
      }
    }

    return $context_mapping;
  }

  /**
   * Logs a message if the logger is available.
   *
   * @param string $level
   *   The log level.
   * @param string $message
   *   The log message.
   * @param array $context
   *   The log context.
   */
  public function log($level, $message, array $context = []): void {
    if ($this->logger) {
      $this->logger->log($level, $message, $context);
    }
  }

}
