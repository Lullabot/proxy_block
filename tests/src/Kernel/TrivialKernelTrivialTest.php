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
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'block',
    'proxy_block',
  ];

  /**
   * Tests a trivial condition.
   *
   * @coversNothing
   */
  public function testSomething(): void {
    // @phpstan-ignore-next-line method.alreadyNarrowedType
    $this->assertTrue(TRUE);
  }

}
