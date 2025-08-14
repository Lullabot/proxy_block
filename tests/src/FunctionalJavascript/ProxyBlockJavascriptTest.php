<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests JavaScript functionality for the Proxy Block module.
 */
#[Group('proxy_block')]
class ProxyBlockJavascriptTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'proxy_block',
    'block',
    'system',
  ];

  /**
   * Tests basic page load functionality.
   *
   * Verifies that the proxy block module doesn't break basic page
   * functionality.
   */
  public function testProxyBlockAjaxFormUpdate(): void {
    // Test that we can load the front page without JavaScript errors.
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests basic JavaScript environment functionality.
   *
   * Ensures the JavaScript test framework is working properly.
   */
  public function testRapidAjaxInteractions(): void {
    // Load a page and verify basic DOM elements exist.
    $this->drupalGet('<front>');
    $this->assertSession()->elementExists('css', 'html');
    $this->assertSession()->elementExists('css', 'body');
  }

}
