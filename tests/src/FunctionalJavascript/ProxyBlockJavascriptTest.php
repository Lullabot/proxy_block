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
    'proxy_block_test',
    'block',
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

    // Verify the test module blocks are available as options.
    $this->assertSession()->optionExists('settings[target_block][id]', 'proxy_block_test_simple');
    $this->assertSession()->optionExists('settings[target_block][id]', 'proxy_block_test_configurable');

    // Select a simple test block and wait for AJAX to complete.
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_simple');

    // Use assertJsCondition to wait for AJAX completion.
    $this->assertJsCondition('document.querySelector("#target-block-config-wrapper").textContent.length > 0', 10000);

    // Now select a configurable block and verify configuration fields appear.
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_configurable');

    // Wait for the configuration form to appear.
    $this->assertJsCondition('document.querySelector("#edit-settings-target-block-config-test-text") !== null', 10000);

    // Verify the configuration fields are present.
    $this->assertSession()->fieldExists('settings[target_block][config][test_text]');
    $this->assertSession()->fieldExists('settings[target_block][config][test_checkbox]');
    $this->assertSession()->fieldExists('settings[target_block][config][test_select]');

    // Fill in the configuration and save.
    $page->fillField('settings[target_block][config][test_text]', 'Test Configuration');
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

    // Rapidly change selections.
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_simple');
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_configurable');
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_context_aware');

    // Wait for the last AJAX call to complete.
    $this->assertJsCondition('document.querySelector("#edit-settings-target-block-config-custom-message") !== null', 10000);

    // Verify the form is still functional.
    $this->assertSession()->fieldExists('settings[target_block][config][custom_message]');

    // Clear selection and verify form updates.
    $page->selectFieldOption('settings[target_block][id]', '');
    $this->assertJsCondition('document.querySelector("#edit-settings-target-block-config-custom-message") === null', 10000);
  }

}
