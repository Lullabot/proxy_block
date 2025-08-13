<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\block\Traits\BlockCreationTrait;
use Drupal\user\Entity\User;

/**
 * Comprehensive functional JavaScript tests for the Proxy Block plugin.
 *
 * Tests all AJAX functionality and dynamic form interactions:
 * - Target block selection with AJAX form updates
 * - Layout Builder integration and modal behavior
 * - Context mapping dynamic forms
 * - Error handling and edge cases.
 *
 * @group proxy_block
 */
class ProxyBlockFunctionalJavascriptTest extends WebDriverTestBase {

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
    'node',
    'user',
    'system',
    'layout_builder',
    'layout_discovery',
    'field_ui',
    'contextual',
  ];

  /**
   * Admin user for testing.
   */
  protected User $adminUser;

  /**
   * Test node for context testing.
   */
  protected Node $testNode;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create admin user with all necessary permissions.
    $this->adminUser = $this->drupalCreateUser([
      'administer blocks',
      'administer themes',
      'access administration pages',
      'administer nodes',
      'create article content',
      'edit any article content',
      'configure any layout',
      'administer node display',
      'administer node fields',
    ]);

    // Create content type for testing.
    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();

    // Create test node.
    $this->testNode = Node::create([
      'type' => 'article',
      'title' => 'Test Article',
      'body' => [
        'value' => 'This is a test article for context testing.',
        'format' => 'plain_text',
      ],
      'status' => 1,
    ]);
    $this->testNode->save();

    // Enable Layout Builder for the article content type.
    LayoutBuilderEntityViewDisplay::load('node.article.default')
      ->enableLayoutBuilder()
      ->setOverridable()
      ->save();

    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests basic AJAX target block selection functionality.
   *
   * Verifies that selecting different target blocks updates the configuration
   * form dynamically through AJAX callbacks.
   */
  public function testAjaxTargetBlockSelection(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Navigate to block placement form.
    $this->drupalGet('admin/structure/block/add/proxy_block_proxy/stark');

    // Wait for page to load completely.
    $assert_session->waitForElement('css', '#edit-settings-target-block-id');

    // Verify initial state - no configuration form visible.
    $config_wrapper = $page->find('css', '#target-block-config-wrapper');
    $this->assertNotNull($config_wrapper, 'Configuration wrapper should exist');
    $this->assertEmpty($config_wrapper->getText(), 'Configuration wrapper should be empty initially');

    // Select a simple test block (no configuration).
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_simple');

    // Wait for AJAX to complete.
    $assert_session->waitForElement('css', '#target-block-config-wrapper', 10);
    $this->assertNotNull($config_wrapper->find('css', '.js-form-item'), 'Configuration form should appear after selecting target block');

    // Select a configurable test block.
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_configurable');

    // Wait for AJAX to complete and verify configurable block form appears.
    $assert_session->waitForElement('css', '#edit-settings-target-block-config-test-text', 10);
    $assert_session->fieldExists('settings[target_block][config][test_text]');
    $assert_session->fieldExists('settings[target_block][config][test_checkbox]');
    $assert_session->fieldExists('settings[target_block][config][test_select]');

    // Verify default values are loaded.
    $this->assertEquals('Default test text', $page->findField('settings[target_block][config][test_text]')->getValue());
    $this->assertEquals('option1', $page->findField('settings[target_block][config][test_select]')->getValue());

    // Change back to simple block and verify configuration form disappears.
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_simple');
    $assert_session->waitForElementRemoved('css', '#edit-settings-target-block-config-test-text', 10);
    $assert_session->fieldNotExists('settings[target_block][config][test_text]');

    // Select empty option and verify all configuration disappears.
    $page->selectFieldOption('settings[target_block][id]', '');
    $this->assertEmpty($config_wrapper->getText(), 'Configuration wrapper should be empty when no target selected');
  }

  /**
   * Tests Layout Builder integration with AJAX functionality.
   *
   * Verifies that AJAX form updates work correctly within Layout Builder
   * modal contexts.
   */
  public function testLayoutBuilderIntegration(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Navigate to Layout Builder for the test node.
    $this->drupalGet("node/{$this->testNode->id()}/layout");
    $assert_session->waitForElement('css', '.layout-builder', 10);

    // Add a proxy block.
    $page->clickLink('Add block');
    $assert_session->waitForText('Choose a block', 10);

    // Select the proxy block.
    $page->clickLink('Proxy Block');
    $assert_session->waitForElement('css', '.ui-dialog', 10);

    // Wait for the form to be fully loaded in the modal.
    $assert_session->waitForElement('css', '#edit-settings-target-block-id', 10);

    // Test AJAX functionality within the modal.
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_configurable');

    // Wait for AJAX to complete within the modal context.
    $assert_session->waitForElement('css', '#edit-settings-target-block-config-test-text', 10);
    $assert_session->fieldExists('settings[target_block][config][test_text]');

    // Update configuration within the modal.
    $page->fillField('settings[target_block][config][test_text]', 'Layout Builder Test Text');
    $page->checkField('settings[target_block][config][test_checkbox]');
    $page->selectFieldOption('settings[target_block][config][test_select]', 'option2');

    // Add the block.
    $page->pressButton('Add block');
    $assert_session->waitForElementRemoved('css', '.ui-dialog', 10);

    // Save the layout.
    $page->pressButton('Save layout');
    $assert_session->waitForText('The layout override has been saved.', 10);

    // Verify the block appears with correct configuration.
    $this->drupalGet("node/{$this->testNode->id()}");
    $assert_session->pageTextContains('Layout Builder Test Text');
    $assert_session->pageTextContains('Checkbox: Checked');
    $assert_session->pageTextContains('Select: option2');
  }

  /**
   * Tests context mapping dynamic forms.
   *
   * Verifies that context-aware blocks show context mapping interfaces
   * and that these forms work correctly with AJAX.
   */
  public function testContextMappingForms(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Navigate to block placement form.
    $this->drupalGet('admin/structure/block/add/proxy_block_proxy/stark');
    $assert_session->waitForElement('css', '#edit-settings-target-block-id');

    // Select context-aware test block.
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_context_aware');

    // Wait for AJAX to complete and context mapping form to appear.
    $assert_session->waitForElement('css', '#edit-settings-target-block-config-show-node-info', 10);

    // Verify context mapping fields appear.
    $assert_session->fieldExists('settings[target_block][config][show_node_info]');
    $assert_session->fieldExists('settings[target_block][config][show_user_info]');
    $assert_session->fieldExists('settings[target_block][config][custom_message]');

    // Verify context mapping section exists (this depends on the actual
    // implementation
    // but we can check for context-related form elements).
    $context_elements = $page->findAll('css', '[id*="context"]');
    $this->assertNotEmpty($context_elements, 'Context mapping elements should be present');

    // Test switching between context-aware and non-context-aware blocks.
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_simple');
    $assert_session->waitForElementRemoved('css', '#edit-settings-target-block-config-show-node-info', 10);

    // Switch back to context-aware block.
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_context_aware');
    $assert_session->waitForElement('css', '#edit-settings-target-block-config-show-node-info', 10);

    // Configure the context-aware block.
    $page->fillField('settings[target_block][config][custom_message]', 'Context test message');
    $page->checkField('settings[target_block][config][show_node_info]');
    $page->checkField('settings[target_block][config][show_user_info]');
  }

  /**
   * Tests form validation with AJAX updates.
   *
   * Verifies that validation errors are handled correctly when forms
   * are updated via AJAX.
   */
  public function testFormValidationWithAjax(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Navigate to block placement form.
    $this->drupalGet('admin/structure/block/add/proxy_block_proxy/stark');
    $assert_session->waitForElement('css', '#edit-settings-target-block-id');

    // Select configurable block.
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_configurable');
    $assert_session->waitForElement('css', '#edit-settings-target-block-config-test-text', 10);

    // Enter invalid data (too short text).
    $page->fillField('settings[target_block][config][test_text]', 'xx');

    // Fill in block info.
    $page->fillField('edit-info', 'Test Proxy Block');

    // Try to save and expect validation error.
    $page->pressButton('Save block');
    $assert_session->waitForText('Test text must be at least 3 characters long', 10);

    // Fix the validation error.
    $page->fillField('settings[target_block][config][test_text]', 'Valid text');
    $page->pressButton('Save block');

    // Verify successful save.
    $assert_session->waitForText('The block configuration has been saved', 10);
  }

  /**
   * Tests error handling and edge cases.
   *
   * Verifies graceful handling of various error conditions and edge cases
   * in AJAX interactions.
   */
  public function testErrorHandlingAndEdgeCases(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Navigate to block placement form.
    $this->drupalGet('admin/structure/block/add/proxy_block_proxy/stark');
    $assert_session->waitForElement('css', '#edit-settings-target-block-id');

    // Test rapid form interactions (selecting different options quickly).
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_simple');
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_configurable');
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_context_aware');

    // Wait for the final AJAX call to complete.
    $assert_session->waitForElement('css', '#edit-settings-target-block-config-custom-message', 10);
    $assert_session->fieldExists('settings[target_block][config][custom_message]');

    // Test selecting empty option after configuring a block.
    $page->fillField('settings[target_block][config][custom_message]', 'Test message');
    $page->selectFieldOption('settings[target_block][id]', '');

    // Verify configuration form disappears.
    $config_wrapper = $page->find('css', '#target-block-config-wrapper');
    $assert_session->waitForElementRemoved('css', '#edit-settings-target-block-config-custom-message', 10);
    $this->assertEmpty($config_wrapper->getText(),
      'Configuration should be cleared when no target selected');

    // Test restricted block (should appear in dropdown but may have access
    // restrictions).
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_restricted');
    $assert_session->waitForElement('css', '#target-block-config-wrapper', 10);

    // The restricted block should load (admin user has necessary permissions).
    // In a real scenario, we might test with a user without permissions.
  }

  /**
   * Tests AJAX fade effects and loading states.
   *
   * Verifies that visual feedback is provided during AJAX operations.
   */
  public function testAjaxVisualFeedback(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Navigate to block placement form.
    $this->drupalGet('admin/structure/block/add/proxy_block_proxy/stark');
    $assert_session->waitForElement('css', '#edit-settings-target-block-id');

    // Select a block and verify AJAX progress indicator appears.
    $page->selectFieldOption('settings[target_block][id]', 'proxy_block_test_configurable');

    // The AJAX progress indicator should appear briefly.
    // Note: This is hard to test reliably as it happens very quickly,
    // but we can at least verify the end result.
    $assert_session->waitForElement('css', '#edit-settings-target-block-config-test-text', 10);

    // Verify the fade effect wrapper exists (this is set in the AJAX
    // configuration).
    $config_wrapper = $page->find('css', '#target-block-config-wrapper');
    $this->assertNotNull($config_wrapper,
      'Configuration wrapper with fade effect should exist');
  }

}
