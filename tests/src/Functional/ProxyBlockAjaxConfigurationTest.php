<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests AJAX configuration in Proxy Block forms.
 *
 * Verifies that the AJAX configuration is properly set up in the block
 * configuration forms, even if we can't test the actual AJAX behavior
 * in a stable way across different CI environments.
 *
 * @group proxy_block
 */
class ProxyBlockAjaxConfigurationTest extends BrowserTestBase {

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
   * Tests that AJAX configuration is properly set up in the block form.
   */
  public function testAjaxConfigurationExists(): void {
    // Create admin user.
    $admin_user = $this->drupalCreateUser([
      'administer blocks',
      'access administration pages',
    ]);

    $this->drupalLogin($admin_user);

    // Navigate to block placement form.
    $this->drupalGet('admin/structure/block/add/proxy_block_proxy/stark');
    $this->assertSession()->statusCodeEquals(200);

    // Verify the form structure for AJAX is correct.
    $this->assertSession()->fieldExists('settings[target_block][id]');
    $this->assertSession()->elementExists('css', '#target-block-config-wrapper');

    // Verify that the target block selection has the necessary options.
    $this->assertSession()->optionExists('settings[target_block][id]', '');
    $this->assertSession()->optionExists('settings[target_block][id]', 'proxy_block_test_simple');
    $this->assertSession()->optionExists('settings[target_block][id]', 'proxy_block_test_configurable');
    $this->assertSession()->optionExists('settings[target_block][id]', 'proxy_block_test_context_aware');
    $this->assertSession()->optionExists('settings[target_block][id]', 'proxy_block_test_restricted');

    // Verify AJAX attributes are present on the target block selection field.
    $target_select = $this->getSession()->getPage()->findField('settings[target_block][id]');
    $this->assertNotNull($target_select);

    // Check the page source contains AJAX-related JavaScript/attributes.
    $page_content = $this->getSession()->getPage()->getContent();
    $this->assertStringContainsString('#target-block-config-wrapper', $page_content);
    $this->assertStringContainsString('targetBlockAjaxCallback', $page_content);
  }

  /**
   * Tests form submission with different target blocks.
   */
  public function testFormSubmissionWithTargetBlocks(): void {
    $admin_user = $this->drupalCreateUser([
      'administer blocks',
      'access administration pages',
    ]);

    $this->drupalLogin($admin_user);

    // Test with simple block.
    $this->drupalGet('admin/structure/block/add/proxy_block_proxy/stark');
    $this->submitForm([
      'settings[target_block][id]' => 'proxy_block_test_simple',
      'info' => 'Test Simple Proxy Block',
    ], 'Save block');

    $this->assertSession()->addressMatches('/admin\/structure\/block$/');
    $this->assertSession()->pageTextContains('Test Simple Proxy Block');

    // Test with configurable block.
    $this->drupalGet('admin/structure/block/add/proxy_block_proxy/stark');
    $this->submitForm([
      'settings[target_block][id]' => 'proxy_block_test_configurable',
      'info' => 'Test Configurable Proxy Block',
      // Note: We can't test the actual AJAX form loading, but we can
      // test that the form accepts the configuration structure.
    ], 'Save block');

    $this->assertSession()->addressMatches('/admin\/structure\/block$/');
    $this->assertSession()->pageTextContains('Test Configurable Proxy Block');
  }

}
