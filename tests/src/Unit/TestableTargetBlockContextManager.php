<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\Unit;

use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\proxy_block\Service\TargetBlockContextManager;

/**
 * Testable version of TargetBlockContextManager.
 *
 * This class mocks createDefaultViewModeContext for unit testing.
 */
class TestableTargetBlockContextManager extends TargetBlockContextManager {

  /**
   * Mock view mode context for testing.
   */
  private ContextInterface $mockViewModeContext;

  /**
   * Sets the mock view mode context.
   *
   * @param \Drupal\Core\Plugin\Context\ContextInterface $context
   *   The mock context to use.
   */
  public function setMockViewModeContext(ContextInterface $context): void {
    $this->mockViewModeContext = $context;
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface
   *   The mock view mode context for testing.
   */
  protected function createDefaultViewModeContext() {
    return $this->mockViewModeContext;
  }

}
