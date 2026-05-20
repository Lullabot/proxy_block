<?php

declare(strict_types=1);

namespace Drupal\proxy_block\Service;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\ContextAwarePluginAssignmentTrait;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Handles the processing of the proxy block form.
 */
class TargetBlockFormProcessor {

  use ContextAwarePluginAssignmentTrait;
  use StringTranslationTrait;

  /**
   * Constructs a new TargetBlockFormProcessor object.
   *
   * @param \Drupal\Core\Block\BlockManagerInterface $blockManager
   *   The block manager service.
   * @param \Drupal\proxy_block\Service\TargetBlockContextManager $contextManager
   *   The target block context manager.
   */
  public function __construct(
    protected BlockManagerInterface $blockManager,
    protected TargetBlockContextManager $contextManager,
  ) {}

  /**
   * Builds the target block configuration form.
   *
   * @param string $plugin_id
   *   The selected block plugin ID.
   * @param array $configuration
   *   The proxy block configuration.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the calling form. Forwarded to the context manager so
   *   Layout Builder's section-storage contexts (exposed via the
   *   'gathered_contexts' temporary value) drive the context-mapping element.
   *
   * @return array
   *   The configuration form elements.
   */
  public function buildTargetBlockConfigurationForm(string $plugin_id, array $configuration, FormStateInterface $form_state): array {
    $block_config = $configuration['target_block']['config'] ?? [];
    $form_elements = [];

    try {
      $target_block = $this->blockManager->createInstance($plugin_id, $block_config);

      if ($target_block instanceof PluginFormInterface) {
        $config_form = $target_block->buildConfigurationForm([], new FormState());

        // If the config form is empty, show the no_config message.
        if (empty($config_form)) {
          $form_elements['no_config'] = [
            '#type' => 'details',
            '#title' => $this->t('Block Configuration'),
            '#open' => TRUE,
            'message' => [
              '#markup' => $this->t('This block does not have any configuration options.'),
            ],
          ];
        }
        else {
          $form_elements['block_config'] = [
            '#type' => 'details',
            '#title' => $this->t('Block Configuration'),
            '#open' => TRUE,
          ] + $config_form;
        }
      }
      else {
        $form_elements['no_config'] = [
          '#type' => 'details',
          '#title' => $this->t('Block Configuration'),
          '#open' => TRUE,
          'message' => [
            '#markup' => $this->t('This block does not have any configuration options.'),
          ],
        ];
      }

      if ($target_block instanceof ContextAwarePluginInterface) {
        // When the proxy is configured inside Layout Builder, LB already
        // renders a context_mapping element at the form root against the
        // proxy block's own (target-mirrored) context definitions. Adding
        // a second one here would duplicate the UI and split the saved
        // mapping across two places. Detect LB by the presence of its
        // 'gathered_contexts' temporary value and skip the inner element
        // in that case.
        $lb_gathered = $form_state->getTemporaryValue('gathered_contexts');
        $inside_layout_builder = is_array($lb_gathered) && !empty($lb_gathered);
        if (!$inside_layout_builder) {
          $gathered_contexts = $this->contextManager->getGatheredContexts($form_state);
          $form_elements['context_mapping'] = $this->addContextAssignmentElement($target_block, $gathered_contexts);
        }
      }

      return $form_elements;
    }
    catch (PluginException $e) {
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
   * Validates the target block configuration.
   *
   * @param array $form
   *   The parent form structure, used to scope a SubformState to the nested
   *   block-config sub-form so target plugins read the correct values.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $configuration
   *   The proxy block configuration.
   */
  public function validateTargetBlock(array $form, FormStateInterface $form_state, array $configuration): void {
    $target_block_plugin = $form_state->getValue(['target_block', 'id']);
    if (empty($target_block_plugin)) {
      return;
    }

    // The AJAX-built target form nests block-config values under
    // target_block.config.block_config; read from that exact path so the
    // values match what the sub-form actually rendered.
    $block_config = $form_state->getValue(['target_block', 'config', 'block_config'])
      ?? $configuration['target_block']['config']
      ?? [];

    try {
      $target_block = $this->blockManager->createInstance($target_block_plugin, $block_config);

      if ($target_block instanceof PluginFormInterface) {
        $sub_form = $form['target_block']['config']['block_config'] ?? [];
        if (!empty($sub_form)) {
          $subform_state = SubformState::createForSubform($sub_form, $form, $form_state);
          $target_block->validateConfigurationForm($sub_form, $subform_state);
        }
      }
    }
    catch (PluginException $e) {
      $form_state->setErrorByName('target_block][id', $this->t('Invalid target block plugin: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * Submits the target block configuration.
   *
   * @param array $form
   *   The parent form structure, used to scope a SubformState to the nested
   *   block-config sub-form so the target plugin's own submit lifecycle sees
   *   the correct values.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $configuration
   *   The current proxy block configuration. Any keys outside of
   *   `target_block` are preserved in the returned array.
   *
   * @return array
   *   The full proxy block configuration with the submitted target block
   *   values merged in (submitted values win over the existing ones).
   */
  public function submitTargetBlock(array $form, FormStateInterface $form_state, array $configuration): array {
    $target_plugin_id = $form_state->getValue(['target_block', 'id']);
    $submitted = [];
    $submitted['target_block']['id'] = $target_plugin_id;

    if (empty($target_plugin_id)) {
      $submitted['target_block']['config'] = [];
      return $submitted + $configuration;
    }

    // Read the unwrapped block-config values (the AJAX-built sub-form lives
    // at target_block.config.block_config). Reading from target_block.config
    // would return the wrapper array and pollute the plugin's configuration
    // with leftover 'block_config' / 'context_mapping' keys.
    $block_config = $form_state->getValue(['target_block', 'config', 'block_config']) ?? [];

    try {
      $target_block = $this->blockManager->createInstance($target_plugin_id, $block_config);

      // Route block-config values through the target plugin's own submit
      // lifecycle so plugins reading $form_state->getValues() see the
      // sub-form scope rather than the parent scope.
      if ($target_block instanceof PluginFormInterface && $target_block instanceof BlockPluginInterface) {
        $sub_form = $form['target_block']['config']['block_config'] ?? [];
        if (!empty($sub_form)) {
          $subform_state = SubformState::createForSubform($sub_form, $form, $form_state);
          $target_block->submitConfigurationForm($sub_form, $subform_state);
        }
        else {
          // Plugin built no config form; fall back to a direct merge so any
          // values present still reach the configuration array.
          $target_block->setConfiguration($block_config + $target_block->getConfiguration());
        }
      }

      if ($target_block instanceof ContextAwarePluginInterface) {
        $context_mapping = $form_state->getValue(['target_block', 'config', 'context_mapping']);
        // When the proxy is configured inside Layout Builder, the inner
        // context_mapping element is intentionally not rendered (LB owns it
        // at the root). In that case the submitted value is NULL and we
        // must NOT clobber an existing saved mapping with an empty array.
        if ($context_mapping !== NULL) {
          $target_block->setContextMapping($context_mapping);
        }
      }

      // Persist the unwrapped target plugin configuration.
      $submitted['target_block']['config'] = $target_block instanceof BlockPluginInterface
        ? $target_block->getConfiguration()
        : $block_config;
    }
    catch (PluginException $e) {
      $submitted['target_block']['config'] = [];
    }

    return $submitted + $configuration;
  }

  /**
   * Builds the list of block plugins available as proxy targets.
   *
   * @param string $excluded_plugin_id
   *   The plugin id of the proxy itself, excluded to prevent self-reference.
   *
   * @return array
   *   Map of plugin id => admin label, sorted alphabetically by label.
   */
  public function getAvailableBlockOptions(string $excluded_plugin_id): array {
    $definitions = $this->blockManager->getDefinitions();
    $options = array_map(
      static fn ($definition) => $definition['admin_label'] ?? $definition['id'],
      array_filter(
        $definitions,
        static fn ($definition, $plugin_id) => $plugin_id !== $excluded_plugin_id,
        ARRAY_FILTER_USE_BOTH,
      ),
    );
    $options = array_unique($options);
    asort($options);
    return $options;
  }

  /**
   * Gets the selected target from the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array|null
   *   The selected target, or null if not found.
   */
  public function getSelectedTargetFromFormState(FormStateInterface $form_state): ?array {
    $triggering_element = $form_state->getTriggeringElement();
    if (
      $triggering_element
      && isset($triggering_element['#parents'])
      && count($triggering_element['#parents']) >= 2
      && array_slice($triggering_element['#parents'], -2) === [
        'target_block',
        'id',
      ]
    ) {
      $user_input = $form_state->getUserInput();
      return NestedArray::getValue($user_input, array_slice($triggering_element['#parents'], 0, -1));
    }
    return NULL;
  }

}
