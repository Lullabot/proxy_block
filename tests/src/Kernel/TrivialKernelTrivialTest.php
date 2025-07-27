<?php

namespace Drupal\Tests\proxy_block\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the trivial kernel functionality.
 *
 * @group proxy_block
 */
class TrivialKernelTrivialTest extends KernelTestBase {

  /**
   * Tests a trivial condition.
   *
   * @coversNothing
   */
  public function testSomething() {
    $this->assertTrue(TRUE);
  }

}
