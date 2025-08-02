<?php

declare(strict_types=1);

namespace Drupal\proxy_block\Service;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Manages context for the target block.
 */
class TargetBlockContextManager {

  use StringTranslationTrait;

  /**
   * Constructs a new TargetBlockContextManager object.
   *
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $contextRepository
   *   The context repository.
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $contextHandler
   *   The context handler.
   */
  public function __construct(
    protected ContextRepositoryInterface $contextRepository,
    protected ContextHandlerInterface $contextHandler,
  ) {}

  /**
   * Gets all gathered contexts like Layout Builder does.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   Array of available contexts.
   */
  public function getGatheredContexts(): array {
    $available_context_ids = $this->contextRepository->getAvailableContexts();
    $contexts = $this->contextRepository->getRuntimeContexts(array_keys($available_context_ids));

    $populated_contexts = $contexts;

    if (!isset($populated_contexts['view_mode'])) {
      $populated_contexts['view_mode'] = $this->createDefaultViewModeContext();
    }

    return $populated_contexts;
  }

  /**
   * Creates a default view_mode context for FieldBlocks.
   *
   * @return \Drupal\Core\Plugin\Context\Context
   *   A view_mode context with the 'default' value.
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

      $target_context_definitions = $target_block->getContextDefinitions();
      $missing_required_contexts = [];

      foreach ($target_context_definitions as $context_name => $context_definition) {
        if ($context_definition->isRequired() && !isset($context_mapping[$context_name])) {
          $missing_required_contexts[] = $context_name;
        }
      }

      if (empty($context_mapping) || !empty($missing_required_contexts)) {

        $automatic_mapping = $this->generateAutomaticContextMapping($target_block, $gathered_contexts);
        if (!empty($automatic_mapping)) {
          $merged_mapping = $context_mapping + $automatic_mapping;
          $target_block->setContextMapping($merged_mapping);
          $context_mapping = $merged_mapping;

        }
      }

      if (!empty($gathered_contexts) && !empty($context_mapping)) {
        $resolved_mapping = [];
        foreach ($context_mapping as $target_context => $source_context_id) {
          $clean_context_id = ltrim($source_context_id, '@');

          if (isset($gathered_contexts[$clean_context_id])) {
            $resolved_mapping[$target_context] = $clean_context_id;
          }
          elseif (isset($gathered_contexts[$source_context_id])) {
            $resolved_mapping[$target_context] = $source_context_id;
          }
          else {
          }
        }

        if (!empty($resolved_mapping)) {

          try {
            $this->contextHandler->applyContextMapping($target_block, $gathered_contexts, $resolved_mapping);
          }
          catch (\Exception $e) {
            // The context application failed, continue without contexts.
          }
        }
      }
    }
    catch (\Exception $e) {
      // Failed to apply contexts to the target block, continue without them.
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

}
