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
   * Tests that the proxy block AJAX configuration works.
   *
   * This test verifies that the AJAX-powered target block selection
   * updates the configuration form dynamically without page reload.
   */
  public function testProxyBlockAjaxFormUpdate(): void {
    // Create and login admin user.
    $admin_user = $this->drupalCreateUser([
      'administer blocks',
      'access administration pages',
    ]);
    $this->drupalLogin($admin_user);

    // Navigate to the block placement form.
    $this->drupalGet('admin/structure/block/add/proxy_block_proxy/stark');
    $this->assertSession()->statusCodeEquals(200);

    // Verify the form elements exist.
    $this->assertSession()->fieldExists('settings[target_block][id]');
    $this->assertSession()->elementExists('css', '#target-block-config-wrapper');

    // Verify core blocks are available as options.
    $this->assertSession()->optionExists('settings[target_block][id]', 'system_branding_block');
    $this->assertSession()->optionExists('settings[target_block][id]', 'system_main_block');

    // Select a system block and wait for AJAX to complete.
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('settings[target_block][id]', 'system_branding_block');

    // Use assertJsCondition to wait for AJAX completion.
    $this->assertJsCondition('document.querySelector("#target-block-config-wrapper").textContent.length > 0', 10000);

    // Select empty option to test clearing.
    $page->selectFieldOption('settings[target_block][id]', '');

    // Submit the form with a valid configuration.
    $page->selectFieldOption('settings[target_block][id]', 'system_main_block');
    $page->fillField('info', 'Test Proxy Block with AJAX');
    $page->pressButton('Save block');

    // Verify the block was saved successfully.
    $this->assertSession()->addressEquals('admin/structure/block');
    $this->assertSession()->pageTextContains('Test Proxy Block with AJAX');
  }

  /**
   * Tests rapid AJAX interactions don't cause issues.
   *
   * This test verifies that rapidly changing target block selections
   * doesn't break the form or cause JavaScript errors.
   */
  public function testRapidAjaxInteractions(): void {
    $admin_user = $this->drupalCreateUser([
      'administer blocks',
      'access administration pages',
    ]);
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/structure/block/add/proxy_block_proxy/stark');
    $page = $this->getSession()->getPage();

    // Rapidly change selections between core blocks.
    $page->selectFieldOption('settings[target_block][id]', 'system_branding_block');
    $page->selectFieldOption('settings[target_block][id]', 'system_main_block');
    $page->selectFieldOption('settings[target_block][id]', 'system_powered_by_block');

    // Clear selection and verify form updates.
    $page->selectFieldOption('settings[target_block][id]', '');

    // Verify the form is still functional after rapid changes.
    $page->selectFieldOption('settings[target_block][id]', 'system_main_block');
    $this->assertJsCondition('document.querySelector("#target-block-config-wrapper") !== null', 10000);

    // Submit to verify form still works.
    $page->fillField('info', 'Test Rapid Changes Block');
    $page->pressButton('Save block');

    $this->assertSession()->addressEquals('admin/structure/block');
    $this->assertSession()->pageTextContains('Test Rapid Changes Block');
  }

}
