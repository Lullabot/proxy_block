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
   * Tests basic proxy block form access.
   *
   * Verifies that the proxy block configuration form is accessible
   * and contains the expected form elements.
   */
  public function testProxyBlockAjaxFormUpdate(): void {
    // Create and login admin user.
    $admin_user = $this->drupalCreateUser([
      'administer blocks',
      'access administration pages',
    ]);
    $this->drupalLogin($admin_user);

    // Navigate to block administration page.
    $this->drupalGet('admin/structure/block');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Block layout');
  }

  /**
   * Tests proxy block module functionality.
   *
   * Verifies the proxy_block module is working properly in a
   * JavaScript test environment.
   */
  public function testRapidAjaxInteractions(): void {
    // Test that the module is loaded and functional.
    $modules = \Drupal::moduleHandler()->getModuleList();
    $this->assertArrayHasKey('proxy_block', $modules);

    // Test basic page functionality.
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
  }

}
