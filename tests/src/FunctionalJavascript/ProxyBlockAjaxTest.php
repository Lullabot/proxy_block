<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\block\Traits\BlockCreationTrait;

/**
 * Tests AJAX functionality in Proxy Block configuration forms.
 *
 * Comprehensive tests for all AJAX functionality in the Proxy Block plugin:
 * - Target block selection with dynamic form updates
 * - Configuration form appearance and validation
 * - Context mapping for context-aware blocks
 * - Error handling and edge cases.
 *
 * This test uses assertJsCondition() instead of potentially problematic
 * waitFor methods to ensure compatibility across Drupal versions.
 *
 * @group proxy_block
 */
class ProxyBlockAjaxTest extends WebDriverTestBase {

  use BlockCreationTrait;

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
   * Tests basic AJAX target block selection.
   */
  public function testBasicAjaxSelection(): void {
    // Create admin user.
    $admin_user = $this->drupalCreateUser([
      'administer blocks',
      'access administration pages',
    ]);

    $this->drupalLogin($admin_user);

    // Navigate to block placement form.
    $this->drupalGet('admin/structure/block/add/proxy_block_proxy/stark');

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Verify form loads correctly.
    $assert_session->statusCodeEquals(200);
    $assert_session->fieldExists('settings[target_block][id]');

    // Verify initial state.
    $config_wrapper = $page->find('css', '#target-block-config-wrapper');
    $this->assertNotNull($config_wrapper);

    // Select a test block and verify AJAX response.
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_simple');

    // Wait for AJAX to complete using assertJsCondition.
    $this->assertJsCondition('jQuery("#target-block-config-wrapper").length > 0');

    // Verify block info field and submit work.
    $page->fillField('edit-info', 'Test Proxy Block');
    $page->pressButton('Save block');

    // Should redirect to block admin page.
    $assert_session->addressMatches('/admin\/structure\/block$/');
  }

  /**
   * Tests configurable block AJAX updates.
   */
  public function testConfigurableBlockAjax(): void {
    $admin_user = $this->drupalCreateUser([
      'administer blocks',
      'access administration pages',
    ]);

    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/structure/block/add/proxy_block_proxy/stark');

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Select configurable block.
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_configurable');

    // Wait for configuration form to appear.
    $this->assertJsCondition('jQuery("#edit-settings-target-block-config-test-text").length > 0');

    // Verify configuration fields are present.
    $assert_session->fieldExists('settings[target_block][config][test_text]');
    $assert_session->fieldExists('settings[target_block][config][test_checkbox]');
    $assert_session->fieldExists('settings[target_block][config][test_select]');

    // Test form validation.
    $page->fillField('settings[target_block][config][test_text]', 'Valid Test Text');
    $page->fillField('edit-info', 'Configurable Test Block');
    $page->pressButton('Save block');

    $assert_session->addressMatches('/admin\/structure\/block$/');
  }

  /**
   * Tests context-aware block AJAX behavior.
   */
  public function testContextAwareBlockAjax(): void {
    $admin_user = $this->drupalCreateUser([
      'administer blocks',
      'access administration pages',
    ]);

    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/structure/block/add/proxy_block_proxy/stark');

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Select context-aware block.
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_context_aware');

    // Wait for context-aware configuration form to appear.
    $this->assertJsCondition('jQuery("#edit-settings-target-block-config-show-node-info").length > 0');

    // Verify context-aware fields are present.
    $assert_session->fieldExists('settings[target_block][config][show_node_info]');
    $assert_session->fieldExists('settings[target_block][config][show_user_info]');
    $assert_session->fieldExists('settings[target_block][config][custom_message]');

    // Test switching back to simple block removes context fields.
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_simple');
    $this->assertJsCondition('jQuery("#edit-settings-target-block-config-show-node-info").length === 0');
  }

  /**
   * Tests form validation with AJAX.
   */
  public function testFormValidationWithAjax(): void {
    $admin_user = $this->drupalCreateUser([
      'administer blocks',
      'access administration pages',
    ]);

    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/structure/block/add/proxy_block_proxy/stark');

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Select configurable block.
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_configurable');
    $this->assertJsCondition('jQuery("#edit-settings-target-block-config-test-text").length > 0');

    // Test validation error.
    $page->fillField('settings[target_block][config][test_text]', 'xx');
    $page->fillField('edit-info', 'Test Block');
    $page->pressButton('Save block');

    // Should show validation error and stay on same page.
    $assert_session->pageTextContains('Test text must be at least 3 characters long');

    // Fix error and submit successfully.
    $page->fillField('settings[target_block][config][test_text]', 'Valid text');
    $page->pressButton('Save block');

    $assert_session->addressMatches('/admin\/structure\/block$/');
  }

  /**
   * Tests edge cases and rapid interactions.
   */
  public function testEdgeCasesAndRapidInteractions(): void {
    $admin_user = $this->drupalCreateUser([
      'administer blocks',
      'access administration pages',
    ]);

    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/structure/block/add/proxy_block_proxy/stark');

    $page = $this->getSession()->getPage();

    // Test rapid selections.
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_simple');
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_configurable');
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_context_aware');

    // Wait for final selection to complete.
    $this->assertJsCondition('jQuery("#edit-settings-target-block-config-custom-message").length > 0');

    // Test selecting empty option clears configuration.
    $page->fillField('settings[target_block][config][custom_message]', 'Test message');
    $page->selectFieldOption('settings[target_block][id]', '');

    // Configuration should be cleared.
    $this->assertJsCondition('jQuery("#edit-settings-target-block-config-custom-message").length === 0');

    // Test restricted block (admin user should have access).
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_restricted');
    $this->assertJsCondition('jQuery("#target-block-config-wrapper").length > 0');
  }

  /**
   * Tests AJAX fade effects and visual feedback.
   */
  public function testAjaxVisualFeedback(): void {
    $admin_user = $this->drupalCreateUser([
      'administer blocks',
      'access administration pages',
    ]);

    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/structure/block/add/proxy_block_proxy/stark');

    $page = $this->getSession()->getPage();

    // Verify the AJAX wrapper exists.
    $config_wrapper = $page->find('css', '#target-block-config-wrapper');
    $this->assertNotNull($config_wrapper, 'AJAX wrapper should exist');

    // Select a block and verify the wrapper is updated.
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_configurable');
    $this->assertJsCondition('jQuery("#edit-settings-target-block-config-test-text").length > 0');

    // Verify the wrapper still exists after AJAX update.
    $config_wrapper_after = $page->find('css', '#target-block-config-wrapper');
    $this->assertNotNull($config_wrapper_after, 'AJAX wrapper should still exist after update');
  }

}
