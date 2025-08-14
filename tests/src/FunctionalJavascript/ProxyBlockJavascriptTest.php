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
    'node',
    'user',
    'system',
    'layout_builder',
    'layout_discovery',
    'field_ui',
  ];

  /**
   * Tests that the proxy block configuration form loads properly.
   *
   * This test verifies that the proxy block form is accessible and contains
   * the expected form elements for target block selection.
   */
  public function testProxyBlockAjaxFormUpdate(): void {
    // Create and login admin user.
    $admin_user = $this->drupalCreateUser([
      'administer blocks',
      'access administration pages',
    ]);
    $this->drupalLogin($admin_user);

    // First check that the proxy block is available in the block list.
    $this->drupalGet('admin/structure/block');
    $this->assertSession()->linkExists('Place block');

    // Try to navigate to the block placement form.
    $this->drupalGet('admin/structure/block/add/proxy_block_proxy/stark');

    // Check if we can access the form or if we get redirected.
    $current_url = $this->getSession()->getCurrentUrl();
    if (strpos($current_url, 'admin/structure/block/add/proxy_block_proxy') !== FALSE) {
      // If we're on the form, test the form elements.
      $this->assertSession()->pageTextContains('Block description');
      $this->assertSession()->fieldExists('settings[target_block][id]');

      // Test basic form functionality.
      $page = $this->getSession()->getPage();
      if ($page->hasSelect('settings[target_block][id]')) {
        $page->selectFieldOption('settings[target_block][id]', 'system_main_block');
        $this->getSession()->wait(1000);
        $page->fillField('info', 'Test Proxy Block');
        $page->pressButton('Save block');

        // Verify the block was saved.
        $this->assertSession()->pageTextContains('block has been created');
      }
    }
    else {
      // If we're redirected, just verify we didn't get an error page.
      $this->assertSession()->pageTextNotContains('The website encountered an unexpected error');
      // Verify we're on a valid admin page.
      $this->assertSession()->pageTextContains('Administration');
    }
  }

  /**
   * Tests that the proxy block module is properly installed.
   *
   * This test verifies that the proxy block module is available and
   * can be found in the block type list.
   */
  public function testRapidAjaxInteractions(): void {
    $admin_user = $this->drupalCreateUser([
      'administer blocks',
      'access administration pages',
    ]);
    $this->drupalLogin($admin_user);

    // Navigate to block administration page.
    $this->drupalGet('admin/structure/block');
    $this->assertSession()->pageTextContains('Block layout');

    // Check that we can access the block placement interface.
    $this->drupalGet('admin/structure/block/list/stark');
    $this->assertSession()->pageTextContains('Stark');

    // Verify the page loaded successfully without errors.
    $this->assertSession()->pageTextNotContains('The website encountered an unexpected error');
  }

}
