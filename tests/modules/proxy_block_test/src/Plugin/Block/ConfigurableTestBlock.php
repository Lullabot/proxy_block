<?php

declare(strict_types=1);

namespace Drupal\proxy_block_test\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a configurable test block with form elements.
 */
#[Block(
  id: 'proxy_block_test_configurable',
  admin_label: new TranslatableMarkup('Configurable Test Block'),
  category: new TranslatableMarkup('Proxy Block Test'),
)]
final class ConfigurableTestBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'test_text' => 'Default test text',
      'test_checkbox' => FALSE,
      'test_select' => 'option1',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['test_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Test Text'),
      '#description' => $this->t('Enter some test text.'),
      '#default_value' => $this->configuration['test_text'],
      '#required' => TRUE,
    ];

    $form['test_checkbox'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Test Checkbox'),
      '#description' => $this->t('Check this box for testing.'),
      '#default_value' => $this->configuration['test_checkbox'],
    ];

    $form['test_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Test Select'),
      '#description' => $this->t('Select an option for testing.'),
      '#options' => [
        'option1' => $this->t('Option 1'),
        'option2' => $this->t('Option 2'),
        'option3' => $this->t('Option 3'),
      ],
      '#default_value' => $this->configuration['test_select'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state): void {
    $test_text = $form_state->getValue('test_text');
    if (strlen($test_text) < 3) {
      $form_state->setErrorByName('test_text', $this->t('Test text must be at least 3 characters long.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['test_text'] = $form_state->getValue('test_text');
    $this->configuration['test_checkbox'] = $form_state->getValue('test_checkbox');
    $this->configuration['test_select'] = $form_state->getValue('test_select');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $config = $this->getConfiguration();

    return [
      '#theme' => 'item_list',
      '#title' => $this->t('Configurable Test Block'),
      '#items' => [
        $this->t('Text: @text', ['@text' => $config['test_text']]),
        $this->t('Checkbox: @checkbox', ['@checkbox' => $config['test_checkbox'] ? 'Checked' : 'Unchecked']),
        $this->t('Select: @select', ['@select' => $config['test_select']]),
      ],
      '#cache' => [
        'max-age' => 3600,
        'contexts' => ['url.path'],
        'tags' => ['proxy_block_test:configurable'],
      ],
    ];
  }

}
