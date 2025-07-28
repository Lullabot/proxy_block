<?php

namespace Drupal\Tests\proxy_block\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the trivial functional javascript functionality.
 *
 * @group proxy_block
 */
class TrivialFunctionalJavascriptTrivialTest extends WebDriverTestBase {

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
