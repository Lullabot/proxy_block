<?php

declare(strict_types=1);

namespace Drupal\proxy_block\Plugin\Block;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\proxy_block\Service\ProxyBlockRenderer;
use Drupal\proxy_block\Service\TargetBlockFormProcessor;
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
   * Constructs a new ProxyBlock.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\proxy_block\Service\TargetBlockFormProcessor $formProcessor
   *   The form processor (handles target selection, validation, submit).
   * @param \Drupal\proxy_block\Service\ProxyBlockRenderer $renderer
   *   The render-pipeline service (handles build, cache metadata, target
   *   definition lookup).
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected TargetBlockFormProcessor $formProcessor,
    protected ProxyBlockRenderer $renderer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(TargetBlockFormProcessor::class),
      $container->get(ProxyBlockRenderer::class),
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
    $definition = $this->renderer->resolveTargetPluginDefinition($this->configuration);
    if ($definition === NULL) {
      return $parent_definitions;
    }
    return ($definition['context_definitions'] ?? []) + $parent_definitions;
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
   *
   * A/B variant swaps can change the target plugin id (and therefore the
   * mirrored context definitions) between save and render. Drop mapping
   * entries whose target-context name is no longer defined, otherwise
   * ContextHandler::applyContextMapping() rejects the render with
   * "Assigned contexts were not satisfied".
   */
  public function getContextMapping() {
    $mapping = parent::getContextMapping();
    if (empty($mapping)) {
      return $mapping;
    }
    return array_intersect_key($mapping, $this->getContextDefinitions());
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    $block_options = $this->formProcessor->getAvailableBlockOptions($this->getPluginId());
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
    return $this->renderer->render(
      $this->getConfiguration(),
      $this->getContexts(),
      parent::getCacheContexts(),
      parent::getCacheTags(),
      parent::getCacheMaxAge(),
      $this->getPluginId(),
      $this,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return $this->renderer->collectCacheContexts($this->getConfiguration(), parent::getCacheContexts());
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return $this->renderer->collectCacheTags($this->getConfiguration(), parent::getCacheTags(), $this->getPluginId());
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return $this->renderer->collectCacheMaxAge($this->getConfiguration(), parent::getCacheMaxAge());
  }

}
