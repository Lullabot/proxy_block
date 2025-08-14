<?php

declare(strict_types=1);

namespace Drupal\proxy_block_test\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a simple test block with no configuration.
 */
#[Block(
  id: 'proxy_block_test_simple',
  admin_label: new TranslatableMarkup('Simple Test Block'),
  category: new TranslatableMarkup('Proxy Block Test'),
)]
final class SimpleTestBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#markup' => '<div class="simple-test-block">Simple Test Block Content</div>',
      '#cache' => [
        'max-age' => 3600,
        'contexts' => ['url.path'],
        'tags' => ['proxy_block_test:simple'],
      ],
    ];
  }

}
