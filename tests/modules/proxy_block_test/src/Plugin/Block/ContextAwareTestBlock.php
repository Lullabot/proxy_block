<?php

declare(strict_types=1);

namespace Drupal\proxy_block_test\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Provides a context-aware test block that requires node and user contexts.
 */
#[Block(
  id: 'proxy_block_test_context_aware',
  admin_label: new TranslatableMarkup('Context-Aware Test Block'),
  category: new TranslatableMarkup('Proxy Block Test'),
)]
final class ContextAwareTestBlock extends BlockBase implements ContextAwarePluginInterface {

  use ContextAwarePluginTrait;

  /**
   * {@inheritdoc}
   */
  public function getContextDefinitions(): array {
    return [
      'node' => new ContextDefinition('entity:node', $this->t('Node'), TRUE),
      'user' => new ContextDefinition('entity:user', $this->t('User'), FALSE),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'show_node_info' => TRUE,
      'show_user_info' => TRUE,
      'custom_message' => 'Context-aware block is working!',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['show_node_info'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Node Information'),
      '#description' => $this->t('Display information about the current node.'),
      '#default_value' => $this->configuration['show_node_info'],
    ];

    $form['show_user_info'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show User Information'),
      '#description' => $this->t('Display information about the current user.'),
      '#default_value' => $this->configuration['show_user_info'],
    ];

    $form['custom_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom Message'),
      '#description' => $this->t('A custom message to display.'),
      '#default_value' => $this->configuration['custom_message'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['show_node_info'] = $form_state->getValue('show_node_info');
    $this->configuration['show_user_info'] = $form_state->getValue('show_user_info');
    $this->configuration['custom_message'] = $form_state->getValue('custom_message');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $config = $this->getConfiguration();
    $items = [];

    // Add custom message.
    if (!empty($config['custom_message'])) {
      $items[] = $this->t('Message: @message', ['@message' => $config['custom_message']]);
    }

    // Add node information if available and enabled.
    if ($config['show_node_info'] && $this->getContextValue('node')) {
      $node = $this->getContextValue('node');
      if ($node instanceof NodeInterface) {
        $items[] = $this->t('Node: @title (ID: @id)', [
          '@title' => $node->getTitle(),
          '@id' => $node->id(),
        ]);
      }
    }

    // Add user information if available and enabled.
    if ($config['show_user_info'] && $this->getContextValue('user')) {
      $user = $this->getContextValue('user');
      if ($user instanceof UserInterface) {
        $items[] = $this->t('User: @name (ID: @id)', [
          '@name' => $user->getAccountName(),
          '@id' => $user->id(),
        ]);
      }
    }

    return [
      '#theme' => 'item_list',
      '#title' => $this->t('Context-Aware Test Block'),
      '#items' => $items ?: [$this->t('No context information available.')],
      '#cache' => [
    // No caching to ensure context changes are reflected.
        'max-age' => 0,
        'contexts' => ['route', 'user'],
        'tags' => ['proxy_block_test:context_aware'],
      ],
    ];
  }

}
