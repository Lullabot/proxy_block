<?php

declare(strict_types=1);

namespace Drupal\proxy_block_test\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a restricted test block with access control.
 */
#[Block(
  id: 'proxy_block_test_restricted',
  admin_label: new TranslatableMarkup('Restricted Test Block'),
  category: new TranslatableMarkup('Proxy Block Test'),
)]
final class RestrictedTestBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    // Only allow access for users with 'administer blocks' permission.
    return AccessResult::allowedIfHasPermission($account, 'administer blocks');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#markup' => '<div class="restricted-test-block">This block requires special permissions to view.</div>',
      '#cache' => [
        'max-age' => 3600,
        'contexts' => ['user.permissions'],
        'tags' => ['proxy_block_test:restricted'],
      ],
    ];
  }

}
