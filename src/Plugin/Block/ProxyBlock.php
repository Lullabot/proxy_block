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
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\proxy_block\Service\TargetBlockCacheManager;
use Drupal\proxy_block\Service\TargetBlockFactory;
use Drupal\proxy_block\Service\TargetBlockFormProcessor;
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
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->blockManager = $block_manager;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
    $this->targetBlockFactory = $target_block_factory;
    $this->formProcessor = $form_processor;
    $this->cacheManager = $cache_manager;
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
      $container->get(TargetBlockCacheManager::class)
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
    $this->formProcessor->validateTargetBlock($form_state, $this->getConfiguration());
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $this->configuration = $this->formProcessor->submitTargetBlock($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $target_block = $this->targetBlockFactory->getTargetBlock($this->getConfiguration());
    if (!$target_block) {
      return [];
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
        $block_definition = $this->blockManager->getDefinition($target_plugin_id);
        $block_label = $block_definition['admin_label'] ?? $target_plugin_id;
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
