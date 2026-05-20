<?php

declare(strict_types=1);

namespace Drupal\proxy_block\Plugin\Block;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\proxy_block\Service\TargetBlockCacheManager;
use Drupal\proxy_block\Service\TargetBlockContextManager;
use Drupal\proxy_block\Service\TargetBlockFactory;
use Drupal\proxy_block\Service\TargetBlockFormProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a Proxy Block.
 */
#[Block(
  id: 'proxy_block_proxy',
  admin_label: new TranslatableMarkup('Proxy Block'),
  category: new TranslatableMarkup('A/B Testing'),
)]
final class ProxyBlock extends BlockBase implements ContainerFactoryPluginInterface, ContextAwarePluginInterface {

  /**
   * The block manager service.
   */
  protected BlockManagerInterface $blockManager;

  /**
   * The current user service.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The request stack.
   */
  protected RequestStack $requestStack;

  /**
   * The target block factory.
   */
  protected TargetBlockFactory $targetBlockFactory;

  /**
   * The form processor.
   */
  protected TargetBlockFormProcessor $formProcessor;

  /**
   * The cache manager.
   */
  protected TargetBlockCacheManager $cacheManager;

  /**
   * The context manager.
   */
  protected TargetBlockContextManager $contextManager;

  /**
   * Memoized target plugin definitions, keyed by target block id.
   *
   * Value is the plugin definition array, or FALSE when the lookup returned
   * NULL / not-found (FALSE is distinguishable from "not yet looked up").
   */
  protected array $targetDefinitionCache = [];

  /**
   * The logger channel.
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a new AbTestProxyBlock.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\proxy_block\Service\TargetBlockFactory $target_block_factory
   *   The target block factory.
   * @param \Drupal\proxy_block\Service\TargetBlockFormProcessor $form_processor
   *   The form processor.
   * @param \Drupal\proxy_block\Service\TargetBlockCacheManager $cache_manager
   *   The cache manager.
   * @param \Drupal\proxy_block\Service\TargetBlockContextManager $context_manager
   *   The context manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    BlockManagerInterface $block_manager,
    AccountProxyInterface $current_user,
    RequestStack $request_stack,
    TargetBlockFactory $target_block_factory,
    TargetBlockFormProcessor $form_processor,
    TargetBlockCacheManager $cache_manager,
    TargetBlockContextManager $context_manager,
    LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->blockManager = $block_manager;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
    $this->targetBlockFactory = $target_block_factory;
    $this->formProcessor = $form_processor;
    $this->cacheManager = $cache_manager;
    $this->contextManager = $context_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.block'),
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get(TargetBlockFactory::class),
      $container->get(TargetBlockFormProcessor::class),
      $container->get(TargetBlockCacheManager::class),
      $container->get(TargetBlockContextManager::class),
      $container->get('logger.factory')->get('proxy_block'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $default_config = [
      'target_block' => ['id' => NULL, 'config' => []],
    ];
    return $default_config + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   *
   * Expose the target plugin's context definitions as our own. This lets
   * Layout Builder treat the proxy as context-aware: it adds a context
   * assignment element to the configure form and applies section-storage
   * contexts to the proxy at render time, which build() then forwards to
   * the target block.
   */
  public function getContextDefinitions() {
    $parent_definitions = parent::getContextDefinitions();
    $definition = $this->getTargetPluginDefinition();
    if ($definition === NULL) {
      return $parent_definitions;
    }
    return ($definition['context_definitions'] ?? []) + $parent_definitions;
  }

  /**
   * Returns the target block plugin definition, memoized.
   *
   * Used both by getContextDefinitions() and by build()'s admin-mode label
   * lookup so a single blockManager call serves both.
   *
   * @return array|null
   *   The plugin definition array, or NULL when no target is configured or
   *   the target plugin can no longer be resolved.
   */
  protected function getTargetPluginDefinition(): ?array {
    $target_id = $this->configuration['target_block']['id'] ?? '';
    if ($target_id === '') {
      return NULL;
    }
    if (!array_key_exists($target_id, $this->targetDefinitionCache)) {
      $definition = $this->blockManager->getDefinition($target_id, FALSE);
      $this->targetDefinitionCache[$target_id] = is_array($definition) ? $definition : FALSE;
    }
    $definition = $this->targetDefinitionCache[$target_id];
    return $definition === FALSE ? NULL : $definition;
  }

  /**
   * {@inheritdoc}
   *
   * Pair with getContextDefinitions(): the trait's singular form reads from
   * the plugin definition directly, which doesn't know about target-mirrored
   * contexts. Resolve through the merged map so getContexts() / cache
   * metadata collection don't blow up with "context is not a valid context".
   */
  public function getContextDefinition($name) {
    $definitions = $this->getContextDefinitions();
    if (isset($definitions[$name])) {
      return $definitions[$name];
    }
    return parent::getContextDefinition($name);
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    $block_definitions = $this->blockManager->getDefinitions();
    $block_options = array_map(
      static fn ($definition) => $definition['admin_label'] ?? $definition['id'],
      array_filter(
        $block_definitions,
        fn ($definition, $plugin_id) => $plugin_id !== $this->getPluginId(),
        ARRAY_FILTER_USE_BOTH
      )
    );
    $block_options = array_unique($block_options);
    asort($block_options);

    $wrapper_id = 'target-block-config-wrapper';

    $form['target_block'] = [
      '#tree' => TRUE,
      '#type' => 'fieldset',
      '#title' => $this->t('Target Block'),
      'id' => [
        '#type' => 'select',
        '#title' => $this->t('Target Block'),
        '#description' => $this->t('<p>Select the block plugin to proxy. Leave empty to hide the block completely.</p><p><strong>Note:</strong> Only plugin blocks are supported. Content blocks (custom blocks created through the Block Library) are not available.</p>'),
        '#options' => ['' => $this->t('- Do not render any block -')] + $block_options,
        '#default_value' => $config['target_block']['id'] ?? '',
        '#ajax' => [
          'callback' => [$this, 'targetBlockAjaxCallback'],
          'wrapper' => $wrapper_id,
          'effect' => 'fade',
        ],
      ],
      'config' => [
        '#type' => 'container',
        '#attributes' => ['id' => $wrapper_id],
      ],
    ];

    $selected_target_block = $this->formProcessor->getSelectedTargetFromFormState($form_state) ?? $config['target_block'] ?? '';
    if (!empty($selected_target_block['id'])) {
      $form['target_block']['config'] += $this->formProcessor->buildTargetBlockConfigurationForm(
        $selected_target_block['id'],
        $config,
        $form_state,
      );
    }

    return $form;
  }

  /**
   * Ajax callback for target block selection.
   */
  public function targetBlockAjaxCallback(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    return NestedArray::getValue(
      $form,
      [
        ...array_slice($triggering_element['#array_parents'], 0, -1),
        'config',
      ],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    parent::blockValidate($form, $form_state);
    $this->formProcessor->validateTargetBlock($form, $form_state, $this->getConfiguration());
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $this->configuration = $this->formProcessor->submitTargetBlock($form, $form_state, $this->getConfiguration());
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $target_block = $this->targetBlockFactory->getTargetBlock($this->getConfiguration());
    if (!$target_block) {
      return [];
    }

    // Forward contexts that Layout Builder applied to this proxy onto the
    // target. The target's own context_mapping references identifiers that
    // only exist in section storage (e.g. 'layout_builder.entity'), which the
    // render-time repository-only fallback in TargetBlockContextManager
    // cannot resolve. Each setContext() is guarded so a single mismatched
    // type cannot tear down the whole render.
    if ($target_block instanceof ContextAwarePluginInterface) {
      $proxy_contexts = $this->getContexts();
      foreach ($proxy_contexts as $name => $context) {
        try {
          $target_block->setContext($name, $context);
        }
        catch (ContextException $e) {
          $this->logger->notice('Proxy block could not forward context "@name" to target @plugin: @message', [
            '@name' => $name,
            '@plugin' => $target_block->getPluginId(),
            '@message' => $e->getMessage(),
          ]);
        }
      }

      // Last-ditch fallback: when the target still wants a content-entity
      // context (entity / node) and Layout Builder provided its
      // section-storage root entity under a different name (e.g.
      // layout_builder.entity), bind it explicitly so plugins that look up
      // by the conventional name still resolve.
      $root_entity_context = $this->contextManager->resolveRootEntityContext($proxy_contexts);
      if ($root_entity_context !== NULL) {
        foreach (['entity', 'node'] as $fallback_name) {
          if (!isset($target_block->getContextDefinitions()[$fallback_name])) {
            continue;
          }
          try {
            $existing = $target_block->getContext($fallback_name);
            if ($existing->hasContextValue()) {
              continue;
            }
          }
          catch (ContextException) {
            // No context bound under this name yet; fall through to set it.
          }
          try {
            $target_block->setContext($fallback_name, $root_entity_context);
          }
          catch (ContextException $e) {
            $this->logger->notice('Proxy block root-entity fallback could not bind "@name" on target @plugin: @message', [
              '@name' => $fallback_name,
              '@plugin' => $target_block->getPluginId(),
              '@message' => $e->getMessage(),
            ]);
          }
        }
      }
    }

    $request = $this->requestStack->getCurrentRequest();
    $is_layout_builder_admin = $request && (
      (str_contains($request->getPathInfo(), '/admin/structure/types/') && str_contains($request->getPathInfo(), '/display/') && str_contains($request->getPathInfo(), '/layout'))
      || str_contains($request->getPathInfo(), '/layout_builder/update/block/')
      || str_contains($request->getPathInfo(), '/layout_builder/add/block/')
      || ($request->query->get('destination') && str_contains($request->query->get('destination'), '/layout'))
    );

    if ($is_layout_builder_admin) {
      $config = $this->getConfiguration();
      $target_plugin_id = $config['target_block']['id'] ?? '';
      if ($target_plugin_id) {
        $block_definition = $this->getTargetPluginDefinition() ?? [];
        $admin_label = $block_definition['admin_label'] ?? NULL;
        $block_label = $admin_label ?: $target_plugin_id ?: 'Unknown Block';
        // Extra safety: ensure the $block_label is always a non-empty string.
        $block_label = (string) $block_label;
        return [
          '#markup' => '<div class="layout-builder-block"><strong>Proxy Block:</strong> ' . $this->t('Configured to render "@block"', ['@block' => $block_label]) . '</div>',
          '#cache' => [
            'contexts' => $this->getCacheContexts(),
            'tags' => $this->getCacheTags(),
            'max-age' => $this->getCacheMaxAge(),
          ],
        ];
      }
    }

    $access_result = $target_block->access($this->currentUser, TRUE);
    if (!$access_result->isAllowed()) {
      $build = [
        '#markup' => '',
        '#cache' => [
          'contexts' => $this->getCacheContexts(),
          'tags' => $this->getCacheTags(),
          'max-age' => $this->getCacheMaxAge(),
        ],
      ];
      CacheableMetadata::createFromObject($access_result)->applyTo($build);
      return $build;
    }

    $build = $target_block->build();
    $this->cacheManager->bubbleTargetBlockCacheMetadata($build, $target_block, $this);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    $target_block = $this->targetBlockFactory->getTargetBlock($this->getConfiguration());
    return $this->cacheManager->getCacheContexts($target_block, parent::getCacheContexts());
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $target_block = $this->targetBlockFactory->getTargetBlock($this->getConfiguration());
    $cache_tags = $this->cacheManager->getCacheTags($target_block, parent::getCacheTags());
    $cache_tags[] = 'proxy_block_proxy:' . $this->getPluginId();
    return $cache_tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    $target_block = $this->targetBlockFactory->getTargetBlock($this->getConfiguration());
    return $this->cacheManager->getCacheMaxAge($target_block, parent::getCacheMaxAge());
  }

}
