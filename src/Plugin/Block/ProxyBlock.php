<?php

declare(strict_types=1);

namespace Drupal\proxy_block\Plugin\Block;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The logger service.
   */
  protected LoggerInterface $logger;

  /**
   * The current user service.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Cached target block instance.
   */
  protected ?BlockPluginInterface $targetBlockInstance = NULL;

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
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    BlockManagerInterface $block_manager,
    LoggerInterface $logger,
    AccountProxyInterface $current_user,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->blockManager = $block_manager;
    $this->logger = $logger;
    $this->currentUser = $current_user;
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
      $container->get('logger.factory')->get('ab_blocks'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $default_config = [
      'target_block' => ['id' => NULL, 'config' => []],
      'context_mapping' => [],
    ];
    return $default_config + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    // Get all block plugin definitions and filter out this proxy block.
    $block_definitions = $this->blockManager->getDefinitions();

    $block_options = array_map(
      static fn($definition) => $definition['admin_label'] ?? $definition['id'],
      array_filter(
        $block_definitions,
        fn($definition, $plugin_id) => $plugin_id !== $this->getPluginId(),
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
      // Configuration wrapper that will be replaced via Ajax.
      'config' => [
        '#type' => 'container',
        '#attributes' => ['id' => $wrapper_id],
      ],
    ];

    // Get the selected target block. This can be: because we are building the
    // form initially with the saved settings, or because we're rebuilding it
    // after Ajax selection. Form API works the same in both cases.
    $selected_target_block = $this->getSelectedTargetFromFormState($form, $form_state) ?? $config['target_block'] ?? '';
    if (!empty($selected_target_block['id'])) {
      $form['target_block']['config'] += $this->buildTargetBlockConfigurationForm(
        $selected_target_block['id'],
        $form_state,
      );
    }

    return $form;
  }

  private function getSelectedTargetFromFormState(array $form, FormStateInterface $form_state): ?array {
    $triggering_element = $form_state->getTriggeringElement();
    if (
      $triggering_element
      && isset($triggering_element['#parents'])
      && count($triggering_element['#parents']) >= 2
      && array_slice($triggering_element['#parents'], -2) === ['target_block', 'id']
    ) {
      $user_input = $form_state->getUserInput();
      return NestedArray::getValue($user_input, array_slice($triggering_element['#parents'], 0, -1));
    }
    return NULL;
  }
  /**
   * Ajax callback for target block selection.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element to replace.
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
   * Builds the target block configuration form.
   *
   * @param string $plugin_id
   *   The selected block plugin ID.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The configuration form elements.
   */
  private function buildTargetBlockConfigurationForm(string $plugin_id, FormStateInterface $form_state): array {
    $config = $this->getConfiguration();
    $block_config = $config['target_block']['config'] ?? [];
    $form_elements = [];

    try {
      // Create the target block instance.
      $target_block = $this->blockManager->createInstance($plugin_id, $block_config);

      // Build the context mapping form if the block requires contexts.
      if ($target_block instanceof ContextAwarePluginInterface) {
        $context_form = $this->buildContextMappingForm($target_block, $config);
        if (!empty($context_form)) {
          $form_elements['context_mapping'] = $context_form;
        }
      }

      // If the block implements PluginFormInterface, build its configuration form.
      if ($target_block instanceof PluginFormInterface) {
        $config_form = $target_block->buildConfigurationForm([], $form_state);

        $form_elements = [
            '#type' => 'details',
            '#title' => $this->t('Block Configuration'),
            '#open' => TRUE,
          ] + $config_form;
      }
      elseif (empty($form_elements)) {
        // If no configuration form and no contexts, show informational message.
        $form_elements['no_config'] = [
          '#type' => 'details',
          '#title' => $this->t('Block Configuration'),
          '#open' => TRUE,
          'message' => [
            '#markup' => $this->t('This block does not have any configuration options.'),
          ],
        ];
      }

      return $form_elements;
    }
    catch (PluginException $e) {
      $this->logger->warning('Failed to create target block @plugin for configuration form: @message', [
        '@plugin' => $plugin_id,
        '@message' => $e->getMessage(),
      ]);

      return [
        'error' => [
          '#type' => 'details',
          '#title' => $this->t('Block Configuration'),
          '#open' => TRUE,
          'message' => [
            '#markup' => $this->t('Error loading block configuration: @message', [
              '@message' => $e->getMessage(),
            ]),
          ],
        ],
      ];
    }
  }

  /**
   * Builds the context mapping form for a context-aware target block.
   *
   * @param \Drupal\Core\Plugin\ContextAwarePluginInterface $target_block
   *   The target block plugin.
   * @param array $config
   *   The current proxy block configuration.
   *
   * @return array
   *   The context mapping form elements.
   */
  private function buildContextMappingForm(ContextAwarePluginInterface $target_block, array $config): array {
    $context_definitions = $target_block->getContextDefinitions();

    // Guard clause: return early if no context definitions.
    if (empty($context_definitions)) {
      return [];
    }

    // Get available contexts from the proxy block for dropdown options.
    $available_contexts = $this->getContexts();
    $context_options = ['' => $this->t('- Select context -')] + array_map(
        static fn($context) => $context->getContextDefinition()
          ->getLabel() ?: $context->getContextDefinition()->getDataType(),
        $available_contexts
      );

    $form = [
      '#type' => 'details',
      '#title' => $this->t('Context Mapping'),
      '#description' => $this->t('Map contexts required by this block to available contexts.'),
      '#open' => TRUE,
    ];

    $current_mapping = $config['context_mapping'] ?? [];

    $context_fields = array_map(
      function($definition, $context_name) use ($context_options, $current_mapping) {
        $field = [
          '#type' => 'select',
          '#title' => $definition->getLabel() ?: $context_name,
          '#description' => $definition->getDescription(),
          '#options' => $context_options,
          '#default_value' => $current_mapping[$context_name] ?? '',
        ];

        if ($definition->isRequired()) {
          $field['#required'] = TRUE;
        }

        return $field;
      },
      $context_definitions,
      array_keys($context_definitions)
    );

    return $form + $context_fields;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    parent::blockValidate($form, $form_state);

    $target_block_plugin = $form_state->getValue(['target_block', 'id']);
    if (!empty($target_block_plugin)) {
      $config = $this->getConfiguration();
      $block_config = $form_state->getValue(['target_block', 'config']) ?? $config['target_block']['config'] ?? [];

      try {
        // Create the target block instance and validate its configuration.
        $target_block = $this->blockManager->createInstance($target_block_plugin, $block_config);

        if ($target_block instanceof PluginFormInterface) {
          // Skip subform validation for now to avoid SubformState issues
          // The target block configuration will be validated during submission
        }

        // Validate context mapping if the block requires contexts.
        if ($target_block instanceof ContextAwarePluginInterface) {
          $context_mapping = $form_state->getValue('context_mapping') ?? [];
          $context_definitions = $target_block->getContextDefinitions();

          $required_contexts = array_filter($context_definitions, static fn($definition) => $definition->isRequired());
          $missing_contexts = array_filter(
            $required_contexts,
            static fn($definition, $context_name) => empty($context_mapping[$context_name]),
            ARRAY_FILTER_USE_BOTH
          );

          array_walk(
            $missing_contexts,
            function($definition, $context_name) use ($form_state) {
              $form_state->setErrorByName("context_mapping][$context_name]", $this->t('Context mapping for @context is required.', [
                '@context' => $definition->getLabel() ?: $context_name,
              ]));
            }
          );
        }
      }
      catch (PluginException $e) {
        $form_state->setErrorByName('target_block][id', $this->t('Invalid target block plugin: @message', [
          '@message' => $e->getMessage(),
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);

    $target_plugin_id = $form_state->getValue(['target_block', 'id']);
    $this->configuration['target_block']['id'] = $target_plugin_id;

    // Process target block configuration if a plugin is selected.
    if (!empty($target_plugin_id)) {
      $block_config = $form_state->getValue(['target_block', 'config']) ?? [];

      try {
        // Create the target block instance and process its configuration.
        $target_block = $this->blockManager->createInstance($target_plugin_id, $block_config);

        if ($target_block instanceof PluginFormInterface) {
          // Get target block configuration from form values
          $target_block->setConfiguration($block_config + $target_block->getConfiguration());
          $this->configuration['target_block']['config'] = $target_block->getConfiguration();
        }
        else {
          $this->configuration['target_block']['config'] = $block_config;
        }

        // Process context mapping for context-aware blocks.
        if ($target_block instanceof ContextAwarePluginInterface) {
          $context_mapping = $form_state->getValue('context_mapping') ?? [];
          $this->configuration['context_mapping'] = array_filter($context_mapping);
        }
        else {
          $this->configuration['context_mapping'] = [];
        }
      }
      catch (PluginException $e) {
        $this->logger->warning('Failed to process target block configuration for @plugin: @message', [
          '@plugin' => $target_plugin_id,
          '@message' => $e->getMessage(),
        ]);
        $this->configuration['target_block']['config'] = [];
        $this->configuration['context_mapping'] = [];
      }
    }
    else {
      $this->configuration['target_block']['config'] = [];
      $this->configuration['context_mapping'] = [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    // Get the target block instance and render it.
    $target_block = $this->getTargetBlock();
    if (!$target_block) {
      return [];
    }

    // Verify the user has access to the target block.
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

    // Render the target block content.
    $build = $target_block->build();

    // Apply the target block's cache metadata to the render array.
    if ($target_block instanceof CacheableDependencyInterface) {
      $this->bubbleTargetBlockCacheMetadata($build, $target_block);
    }

    return $build;
  }

  /**
   * Gets or creates the target block plugin instance.
   *
   * @return \Drupal\Core\Block\BlockPluginInterface|null
   *   The target block plugin or NULL if creation failed.
   */
  protected function getTargetBlock(): ?BlockPluginInterface {
    if ($this->targetBlockInstance === NULL) {
      $this->targetBlockInstance = $this->createTargetBlock();
    }
    return $this->targetBlockInstance;
  }

  /**
   * Creates the target block plugin instance.
   *
   * @return \Drupal\Core\Block\BlockPluginInterface|null
   *   The target block plugin or NULL if creation failed.
   */
  protected function createTargetBlock(): ?BlockPluginInterface {
    $config = $this->getConfiguration();
    $plugin_id = $config['target_block']['id'] ?? '';
    $block_config = $config['target_block']['config'] ?? [];

    // Guard clause: return early if no plugin ID.
    if (empty($plugin_id)) {
      return NULL;
    }

    try {
      $target_block = $this->blockManager->createInstance($plugin_id, $block_config);

      // Pass contexts to the target block if it's context-aware.
      if ($target_block instanceof ContextAwarePluginInterface) {
        $this->passContextsToTargetBlock($target_block);
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

  /**
   * Passes contexts from the proxy block to the target block.
   *
   * @param \Drupal\Core\Plugin\ContextAwarePluginInterface $target_block
   *   The target block plugin.
   */
  protected function passContextsToTargetBlock(ContextAwarePluginInterface $target_block): void {
    try {
      $proxy_contexts = $this->getContexts();
      $context_mapping = $this->getConfiguration()['context_mapping'] ?? [];
      $target_context_definitions = $target_block->getContextDefinitions();

      $mapped_contexts = array_filter(
        array_map(
          function($target_context_name) use ($context_mapping, $proxy_contexts) {
            $source_context_name = $context_mapping[$target_context_name] ?? $target_context_name;
            return isset($proxy_contexts[$source_context_name])
              ? [$target_context_name, $proxy_contexts[$source_context_name]]
              : NULL;
          },
          array_keys($target_context_definitions)
        )
      );

      // Apply contexts to target block.
      array_walk(
        $mapped_contexts,
        static fn($context_pair) => $target_block->setContext($context_pair[0], $context_pair[1])
      );
    }
    catch (ContextException $e) {
      $this->logger->warning('Failed to pass contexts to target block: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Bubbles cache metadata from the target block to the render array.
   *
   * @param array $build
   *   The render array to apply metadata to.
   * @param \Drupal\Core\Cache\CacheableDependencyInterface $target_block
   *   The target block plugin.
   */
  protected function bubbleTargetBlockCacheMetadata(array &$build, CacheableDependencyInterface $target_block): void {
    $cache_metadata = CacheableMetadata::createFromRenderArray($build);

    // Add target block's cache contexts, tags, and max-age.
    $cache_metadata->addCacheContexts($target_block->getCacheContexts());
    $cache_metadata->addCacheTags($target_block->getCacheTags());
    $cache_metadata->setCacheMaxAge(
      Cache::mergeMaxAges($cache_metadata->getCacheMaxAge(), $target_block->getCacheMaxAge())
    );

    // Add proxy block's own cache metadata.
    $cache_metadata->addCacheContexts($this->getCacheContexts());
    $cache_metadata->addCacheTags($this->getCacheTags());
    $cache_metadata->setCacheMaxAge(
      Cache::mergeMaxAges($cache_metadata->getCacheMaxAge(), $this->getCacheMaxAge())
    );

    $cache_metadata->applyTo($build);
  }

  /**
   * Helper method to get target block cache metadata.
   *
   * @param string $type
   *   The cache metadata type ('contexts', 'tags', or 'max-age').
   * @param mixed $parent_value
   *   The parent cache metadata value.
   *
   * @return mixed
   *   The merged cache metadata.
   */
  protected function getTargetBlockCacheMetadata(string $type, $parent_value) {
    $config = $this->getConfiguration();

    // Guard clause: return early if no target block plugin.
    if (empty($config['target_block']['id'])) {
      return $parent_value;
    }

    $target_block = $this->getTargetBlock();

    // Guard clause: return early if target block creation failed.
    if (!$target_block) {
      return $parent_value;
    }

    return match ($type) {
      'contexts' => Cache::mergeContexts($parent_value, $target_block->getCacheContexts()),
      'tags' => Cache::mergeTags($parent_value, $target_block->getCacheTags()),
      'max-age' => Cache::mergeMaxAges($parent_value, $target_block->getCacheMaxAge()),
      default => $parent_value,
    };
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return $this->getTargetBlockCacheMetadata('contexts', parent::getCacheContexts());
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $cache_tags = $this->getTargetBlockCacheMetadata('tags', parent::getCacheTags());

    // Add config-based cache tag.
    $cache_tags[] = 'proxy_block_proxy:' . $this->getPluginId();

    return $cache_tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return $this->getTargetBlockCacheMetadata('max-age', parent::getCacheMaxAge());
  }

}
