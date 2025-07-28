<?php

namespace Drupal\Tests\proxy_block\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the trivial functional functionality.
 *
 * @group proxy_block
 */
class TrivialFunctionalTrivialTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests a trivial condition.
   *
   * @coversNothing
   */
  public function testSomething() {
    $this->assertTrue(TRUE);
  }

}
