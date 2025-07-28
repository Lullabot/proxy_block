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
  protected static $modules = [
    'system',
    'user',
    'block',
    'proxy_block',
  ];

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
