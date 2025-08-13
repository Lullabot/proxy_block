<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Basic form test for Proxy Block without test dependencies.
 *
 * This test verifies only core proxy block functionality without requiring
 * the proxy_block_test module, which may be causing CI issues.
 *
 * @group proxy_block
 */
class ProxyBlockBasicFormTest extends WebDriverTestBase {

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
  ];

  /**
   * Tests basic proxy block form functionality.
   */
  public function testBasicProxyBlockForm(): void {
    $admin_user = $this->drupalCreateUser(['administer blocks']);
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/structure/block/add/proxy_block_proxy/stark');
    $this->assertSession()->statusCodeEquals(200);

    // Verify core form elements exist.
    $this->assertSession()->fieldExists('settings[target_block][id]');
    $this->assertSession()->elementExists('css', '#target-block-config-wrapper');

    // Verify empty option exists.
    $this->assertSession()->optionExists('settings[target_block][id]', '');

    // Test form can be submitted with empty target (valid case).
    $this->submitForm([
      'settings[target_block][id]' => '',
      'info' => 'Test Empty Proxy Block',
    ], 'Save block');

    $this->assertSession()->addressMatches('/admin\/structure\/block$/');
    $this->assertSession()->pageTextContains('Test Empty Proxy Block');
  }

}