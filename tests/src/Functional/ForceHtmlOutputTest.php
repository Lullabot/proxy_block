<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test to force HTML output generation for debugging CI issues.
 *
 * @group proxy_block
 */
class ForceHtmlOutputTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['proxy_block'];

  /**
   * Force HTML output generation by visiting a page and failing.
   */
  public function testForceHtmlOutput(): void {
    // Visit a page to generate browser state.
    $this->drupalGet('<front>');

    // Check page loaded.
    $this->assertSession()->statusCodeEquals(200);

    // Force HTML output by explicitly calling htmlOutput.
    if (method_exists($this, 'htmlOutput')) {
      $this->htmlOutput();
    }

    // Now intentionally fail to trigger artifact upload.
    $this->fail('Intentionally failing to test HTML artifact generation in CI');
  }

}
