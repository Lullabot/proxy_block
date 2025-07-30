<?php

namespace Drupal\Tests\proxy_block\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Tests the trivial unit functionality.
 *
 * @group proxy_block
 */
class TrivialUnitTrivialTest extends UnitTestCase {

  /**
   * Tests a trivial condition.
   *
   * @coversNothing
   */
  public function testSomething(): void {
    $this->assertTrue(TRUE);
  }

}
