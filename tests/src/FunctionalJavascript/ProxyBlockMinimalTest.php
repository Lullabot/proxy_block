<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Minimal JavaScript test for Proxy Block.
 *
 * This test only verifies that the basic form loads without errors.
 * 
 * @group proxy_block
 */
class ProxyBlockMinimalTest extends WebDriverTestBase {

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
   * Tests that the proxy block form loads with AJAX configuration.
   */
  public function testProxyBlockFormLoads(): void {
    $admin_user = $this->drupalCreateUser(['administer blocks']);
    $this->drupalLogin($admin_user);
    
    $this->drupalGet('admin/structure/block/add/proxy_block_proxy/stark');
    $this->assertSession()->statusCodeEquals(200);
    
    // Verify basic form elements exist.
    $this->assertSession()->fieldExists('settings[target_block][id]');
    $this->assertSession()->elementExists('css', '#target-block-config-wrapper');
    
    // Verify the form contains AJAX configuration.
    $page_content = $this->getSession()->getPage()->getContent();
    $this->assertStringContainsString('targetBlockAjaxCallback', $page_content);
  }

}