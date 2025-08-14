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
    'user',
    'node',
    'field',
  ];

  /**
   * Tests proxy block AJAX form functionality.
   *
   * Verifies that the AJAX-powered target block selection updates the
   * configuration form dynamically without page reload.
   */
  public function testProxyBlockAjaxFormUpdate(): void {
    // Create and login admin user.
    $admin_user = $this->drupalCreateUser([
      'administer blocks',
      'access administration pages',
    ]);
    $this->drupalLogin($admin_user);

    // Navigate to the proxy block placement form.
    $this->drupalGet('admin/structure/block/add/proxy_block_proxy/stark');
    $this->assertSession()->statusCodeEquals(200);

    // Verify the form elements exist.
    $this->assertSession()->fieldExists('settings[target_block][id]');
    $this->assertSession()->elementExists('css', '#target-block-config-wrapper');

    // Verify core blocks are available as options.
    $this->assertSession()->optionExists('settings[target_block][id]', 'system_branding_block');
    $this->assertSession()->optionExists('settings[target_block][id]', 'system_main_block');

    // Test AJAX functionality by selecting a target block.
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('settings[target_block][id]', 'system_branding_block');

    // Wait for AJAX to complete and verify the wrapper still exists.
    $this->getSession()->wait(2000);
    $this->assertSession()->elementExists('css', '#target-block-config-wrapper');

    // Test form submission with valid configuration.
    $page->selectFieldOption('settings[target_block][id]', 'system_main_block');
    $this->getSession()->wait(1000);
    $page->fillField('info', 'Test Proxy Block with AJAX');
    $page->pressButton('Save block');

    // Verify successful block creation.
    $this->assertSession()->addressEquals('admin/structure/block');
    $this->assertSession()->pageTextContains('Test Proxy Block with AJAX');
  }

  /**
   * Tests rapid AJAX interactions don't cause issues.
   *
   * Verifies that rapidly changing target block selections doesn't
   * break the form or cause JavaScript errors.
   */
  public function testRapidAjaxInteractions(): void {
    $admin_user = $this->drupalCreateUser([
      'administer blocks',
      'access administration pages',
    ]);
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/structure/block/add/proxy_block_proxy/stark');
    $page = $this->getSession()->getPage();

    // Rapidly change selections between different core blocks.
    $page->selectFieldOption('settings[target_block][id]', 'system_branding_block');
    $this->getSession()->wait(1000);

    $page->selectFieldOption('settings[target_block][id]', 'system_main_block');
    $this->getSession()->wait(1000);

    $page->selectFieldOption('settings[target_block][id]', 'system_powered_by_block');
    $this->getSession()->wait(1000);

    // Clear selection and verify form still works.
    $page->selectFieldOption('settings[target_block][id]', '');
    $this->getSession()->wait(1000);
    $this->assertSession()->elementExists('css', '#target-block-config-wrapper');

    // Final selection and form submission to verify stability.
    $page->selectFieldOption('settings[target_block][id]', 'system_main_block');
    $this->getSession()->wait(1000);
    $page->fillField('info', 'Test Rapid Changes Block');
    $page->pressButton('Save block');

    $this->assertSession()->addressEquals('admin/structure/block');
    $this->assertSession()->pageTextContains('Test Rapid Changes Block');
  }

  /**
   * Tests proxy block form validation and error handling.
   *
   * Verifies that the form properly handles validation errors and
   * provides appropriate feedback through AJAX.
   */
  public function testProxyBlockFormValidation(): void {
    $admin_user = $this->drupalCreateUser([
      'administer blocks',
      'access administration pages',
    ]);
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/structure/block/add/proxy_block_proxy/stark');
    $page = $this->getSession()->getPage();

    // Test form validation - try to submit without required fields.
    $page->pressButton('Save block');
    $this->assertSession()->pageTextContains('required');

    // Fill in block description but no target block.
    $page->fillField('info', 'Test Validation Block');
    $page->pressButton('Save block');

    // Should still have validation errors for missing target block.
    $this->assertSession()->pageTextContains('required');

    // Now properly configure the block.
    $page->selectFieldOption('settings[target_block][id]', 'system_main_block');
    $this->getSession()->wait(1000);
    $page->pressButton('Save block');

    // Should succeed this time.
    $this->assertSession()->addressEquals('admin/structure/block');
    $this->assertSession()->pageTextContains('Test Validation Block');
  }

}
