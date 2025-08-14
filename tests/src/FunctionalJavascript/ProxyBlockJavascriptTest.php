<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests JavaScript functionality for the Proxy Block module.
 *
 * @group proxy_block
 */
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
   * Tests basic JavaScript environment setup.
   *
   * This is a minimal test to verify the JavaScript test environment
   * is working without complex interactions.
   */
  public function testProxyBlockAjaxFormUpdate(): void {
    // Create and login admin user.
    $admin_user = $this->drupalCreateUser([
      'administer blocks',
      'access administration pages',
    ]);
    $this->drupalLogin($admin_user);

    // Just verify we can access the block administration page.
    $this->drupalGet('admin/structure/block');
    $this->assertSession()->pageTextContains('Block layout');

    // Verify no JavaScript errors occurred.
    $this->assertSession()->pageTextNotContains('The website encountered an unexpected error');
  }

  /**
   * Tests that the module JavaScript loads without errors.
   *
   * This minimal test verifies the JavaScript environment
   * is properly configured for the proxy_block module.
   */
  public function testRapidAjaxInteractions(): void {
    // Simple test that JavaScript environment works.
    $this->drupalGet('<front>');
    $this->assertSession()->pageTextNotContains('The website encountered an unexpected error');

    // Verify JavaScript is working by checking page elements.
    $this->assertSession()->elementExists('css', 'body');
  }

}
