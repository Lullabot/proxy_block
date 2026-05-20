<?php

declare(strict_types=1);

namespace Drupal\proxy_block\Service;

use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

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
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   */
  public function __construct(
    protected ContextRepositoryInterface $contextRepository,
    protected ContextHandlerInterface $contextHandler,
    #[Autowire(service: 'logger.channel.proxy_block')]
    protected LoggerInterface $logger,
  ) {}

  /**
   * Gets all gathered contexts like Layout Builder does.
   *
   * @param \Drupal\Core\Form\FormStateInterface|null $form_state
   *   The form state from the calling configure form, when available. Layout
   *   Builder's configure form populates a 'gathered_contexts' temporary value
   *   that merges section storage contexts with repository contexts; the
   *   directly-placed block path consumes that same value, so honoring it here
   *   keeps proxied placements in parity with native placements.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   Array of available contexts.
   */
  public function getGatheredContexts(?FormStateInterface $form_state = NULL): array {
    if ($form_state !== NULL) {
      $contexts = $form_state->getTemporaryValue('gathered_contexts');
      if (is_array($contexts) && !empty($contexts)) {
        return $contexts;
      }
    }

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
    $gathered_contexts = $this->getGatheredContexts();
    $context_mapping = $target_block->getContextMapping();

    $target_context_definitions = $target_block->getContextDefinitions();
    $missing_required_contexts = array_keys(array_filter(
      $target_context_definitions,
      static fn ($definition, $name) => $definition->isRequired() && !isset($context_mapping[$name]),
      ARRAY_FILTER_USE_BOTH,
    ));

    if (empty($context_mapping) || !empty($missing_required_contexts)) {
      $automatic_mapping = $this->generateAutomaticContextMapping($target_block, $gathered_contexts);
      if (!empty($automatic_mapping)) {
        $context_mapping = $context_mapping + $automatic_mapping;
        $target_block->setContextMapping($context_mapping);
      }
    }

    if (empty($gathered_contexts) || empty($context_mapping)) {
      return;
    }

    $resolved_mapping = [];
    foreach ($context_mapping as $target_context => $source_context_id) {
      $clean_context_id = ltrim($source_context_id, '@');
      if (isset($gathered_contexts[$clean_context_id])) {
        $resolved_mapping[$target_context] = $clean_context_id;
      }
      elseif (isset($gathered_contexts[$source_context_id])) {
        $resolved_mapping[$target_context] = $source_context_id;
      }
    }

    if (empty($resolved_mapping)) {
      return;
    }

    try {
      $this->contextHandler->applyContextMapping($target_block, $gathered_contexts, $resolved_mapping);
    }
    catch (ContextException $e) {
      $this->logger->notice('Proxy block could not apply context mapping for target @plugin: @message', [
        '@plugin' => $target_block->getPluginId(),
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Probes a context bag for the Layout Builder section-storage root entity.
   *
   * Mirrors the lookup ab_blocks' AjaxBlockRender uses: a small whitelist of
   * conventional context names, in priority order. Returns the first context
   * whose value is a content entity.
   *
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $contexts
   *   The context bag to probe (typically the proxy's own
   *   getContexts() output, or the gathered contexts).
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface|null
   *   The first matching context, or NULL when none is found.
   */
  public function resolveRootEntityContext(array $contexts): ?ContextInterface {
    foreach (['layout_builder.entity', 'entity', 'node'] as $key) {
      if (!isset($contexts[$key])) {
        continue;
      }
      $context = $contexts[$key];
      $data = $context->getContextData();
      if ($data instanceof EntityAdapter && $data->getValue() !== NULL) {
        return $context;
      }
    }
    return NULL;
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
