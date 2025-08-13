<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Basic functional JavaScript test for Proxy Block to verify setup.
 *
 * @group proxy_block
 */
class ProxyBlockBasicJavascriptTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'proxy_block',
    'proxy_block_test',
    'block',
    'system',
  ];

  /**
   * Tests that the module loads and basic JavaScript environment works.
   */
  public function testBasicSetup(): void {
    // Create admin user.
    $admin_user = $this->drupalCreateUser([
      'administer blocks',
      'access administration pages',
    ]);

    $this->drupalLogin($admin_user);

    // Navigate to block admin page.
    $this->drupalGet('admin/structure/block');

    // Verify we can access the page.
    $this->assertSession()->statusCodeEquals(200);

    // Verify proxy block appears in available blocks.
    $this->assertSession()->pageTextContains('Proxy Block');
  }

}
