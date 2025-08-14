<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Trivial kernel test to ensure kernel test infrastructure works.
 *
 * @group proxy_block
 */
class TrivialKernelTrivialTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['proxy_block'];

  /**
   * Tests that kernel tests can run.
   */
  public function testTrivial(): void {
    $this->assertEquals('trivial', strtolower('TRIVIAL'));
  }

}
