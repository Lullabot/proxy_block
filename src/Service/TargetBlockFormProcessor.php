<?php

declare(strict_types=1);

namespace Drupal\proxy_block\Service;

use Drupal\Core\Form\FormState;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContextAwarePluginAssignmentTrait;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerInterface;

/**
 * Handles the processing of the proxy block form.
 */
final class TargetBlockFormProcessor {

  use ContextAwarePluginAssignmentTrait;
  use StringTranslationTrait;

  /**
   * The block manager service.
   */
  protected BlockManagerInterface $blockManager;

  /**
   * The logger service.
   */
  protected LoggerInterface $logger;

  /**
   * The target block context manager.
   */
  protected TargetBlockContextManager $contextManager;

  /**
   * Constructs a new TargetBlockFormProcessor object.
   *
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\proxy_block\Service\TargetBlockContextManager $context_manager
   *   The target block context manager.
   */
  public function __construct(
    BlockManagerInterface $block_manager,
    LoggerInterface $logger,
    TargetBlockContextManager $context_manager,
  ) {
    $this->blockManager = $block_manager;
    $this->logger = $logger;
    $this->contextManager = $context_manager;
  }

  /**
   * Builds the target block configuration form.
   *
   * @param string $plugin_id
   *   The selected block plugin ID.
   * @param array $configuration
   *   The proxy block configuration.
   *
   * @return array
   *   The configuration form elements.
   */
  public function buildTargetBlockConfigurationForm(string $plugin_id, array $configuration): array {
    $block_config = $configuration['target_block']['config'] ?? [];
    $form_elements = [];

    try {
      $target_block = $this->blockManager->createInstance($plugin_id, $block_config);

      if ($target_block instanceof PluginFormInterface) {
        $config_form = $target_block->buildConfigurationForm([], new FormState());

        $form_elements['block_config'] = [
          '#type' => 'details',
          '#title' => $this->t('Block Configuration'),
          '#open' => TRUE,
        ] + $config_form;
      }
      elseif (empty($form_elements)) {
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
        $gathered_contexts = $this->contextManager->getGatheredContexts();
        $form_elements['context_mapping'] = $this->addContextAssignmentElement($target_block, $gathered_contexts);
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
   * Validates the target block configuration.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $configuration
   *   The proxy block configuration.
   */
  public function validateTargetBlock(FormStateInterface $form_state, array $configuration): void {
    $target_block_plugin = $form_state->getValue(['target_block', 'id']);
    if (!empty($target_block_plugin)) {
      $block_config = $form_state->getValue(['target_block', 'config']) ?? $configuration['target_block']['config'] ?? [];

      try {
        $this->blockManager->createInstance($target_block_plugin, $block_config);
      }
      catch (PluginException $e) {
        $form_state->setErrorByName('target_block][id', $this->t('Invalid target block plugin: @message', [
          '@message' => $e->getMessage(),
        ]));
      }
    }
  }

  /**
   * Submits the target block configuration.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The updated proxy block configuration.
   */
  public function submitTargetBlock(FormStateInterface $form_state): array {
    $target_plugin_id = $form_state->getValue(['target_block', 'id']);
    $configuration['target_block']['id'] = $target_plugin_id;

    if (!empty($target_plugin_id)) {
      $block_config = $form_state->getValue(['target_block', 'config']) ?? [];

      try {
        $target_block = $this->blockManager->createInstance($target_plugin_id, $block_config);

        if ($target_block instanceof PluginFormInterface) {
          $target_block->setConfiguration($block_config + $target_block->getConfiguration());
          $configuration['target_block']['config'] = $target_block->getConfiguration();
        }
        else {
          $configuration['target_block']['config'] = $block_config;
        }

        if ($target_block instanceof ContextAwarePluginInterface) {
          $context_mapping = $form_state->getValue(['target_block', 'config', 'context_mapping']) ?? [];
          $this->logger->debug('ProxyBlock: Form submitted context mapping: @mapping', [
            '@mapping' => json_encode($context_mapping),
          ]);
          $target_block->setContextMapping($context_mapping);
          $final_config = $target_block->getConfiguration();
          $this->logger->debug('ProxyBlock: Target block final config after context mapping: @config', [
            '@config' => json_encode($final_config),
          ]);
        }

        $configuration['target_block']['config'] = $target_block->getConfiguration();
      }
      catch (PluginException $e) {
        $this->logger->warning('Failed to process target block configuration for @plugin: @message', [
          '@plugin' => $target_plugin_id,
          '@message' => $e->getMessage(),
        ]);
        $configuration['target_block']['config'] = [];
      }
    }
    else {
      $configuration['target_block']['config'] = [];
    }

    return $configuration;
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
