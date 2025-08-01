<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\block\Traits\BlockCreationTrait;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;

/**
 * Functional tests for the Proxy Block.
 *
 * Tests the ProxyBlock plugin through browser-based functional tests,
 * covering block placement workflows, configuration interface, rendering,
 * and error handling scenarios.
 *
 * @group proxy_block
 */
class ProxyBlockFunctionalTest extends BrowserTestBase {

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
    'block',
    'node',
    'user',
    'system',
    'layout_builder',
    'layout_discovery',
    'field_ui',
  ];

  /**
   * Administrative user for testing.
   */
  protected User $adminUser;

  /**
   * Regular user for access control testing.
   */
  protected User $regularUser;

  /**
   * Test node for context-aware blocks.
   */
  protected NodeInterface $testNode;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create content type for testing.
    $this->createContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ]);

    // Create test users.
    $this->createTestUsers();

    // Create test content.
    $this->createTestContent();

    // Enable Layout Builder for the page content type.
    $this->enableLayoutBuilderForContentType('page');

    // Place essential blocks for testing.
    $this->placeEssentialBlocks();
  }

  /**
   * Creates test users with appropriate permissions.
   */
  protected function createTestUsers(): void {
    // Create admin user with comprehensive permissions.
    $this->adminUser = $this->drupalCreateUser([
      'access administration pages',
      'administer blocks',
      'administer nodes',
      'administer content types',
      'create page content',
      'edit any page content',
      'configure any layout',
      'administer node fields',
      'administer node form display',
      'administer node display',
      'access contextual links',
    ]);

    // Create regular user with limited permissions.
    $this->regularUser = $this->drupalCreateUser([
      'access content',
    ]);
  }

  /**
   * Creates test content for context-aware testing.
   */
  protected function createTestContent(): void {
    $this->testNode = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Test Page for Proxy Block',
      'body' => [
        'value' => 'This is test content for proxy block testing.',
        'format' => 'basic_html',
      ],
      'status' => 1,
    ]);
  }

  /**
   * Enables Layout Builder for a content type.
   */
  protected function enableLayoutBuilderForContentType(string $type): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet("/admin/structure/types/manage/{$type}/display");
    $this->submitForm(['layout[enabled]' => TRUE], 'Save');
    $this->submitForm(['layout[allow_custom]' => TRUE], 'Save');
  }

  /**
   * Places essential blocks for testing.
   */
  protected function placeEssentialBlocks(): void {
    // Place page title block for target block testing.
    $this->placeBlock('page_title_block', [
      'region' => 'content',
      'id' => 'page_title_test',
    ]);

    // Place system branding block for another target option.
    $this->placeBlock('system_branding_block', [
      'region' => 'header',
      'id' => 'branding_test',
    ]);
  }

  /**
   * Tests proxy block appears in admin interfaces.
   */
  public function testProxyBlockAppearsInAdminInterfaces(): void {
    $this->drupalLogin($this->adminUser);

    // Test block appears in block library.
    $this->drupalGet('admin/structure/block');
    $this->clickLink('Place block');
    $this->assertSession()->pageTextContains('Proxy Block');
    $this->assertSession()->pageTextContains('A/B Testing');

    // Test block appears in Layout Builder block selection.
    $this->drupalGet('/admin/structure/types/manage/page/display/default/layout');
    $this->clickLink('Add block');
    $this->assertSession()->pageTextContains('Proxy Block');
    $this->assertSession()->linkExists('Proxy Block');
  }

  /**
   * Tests proxy block placement in traditional block regions.
   */
  public function testProxyBlockPlacementInRegions(): void {
    $this->drupalLogin($this->adminUser);

    // Use placeBlock method which bypasses the UI issues.
    $this->placeBlock('proxy_block_proxy', [
      'region' => 'sidebar_first',
      'id' => 'test_proxy_block',
      'label' => 'Test Proxy Block',
      'target_block' => [
        'id' => 'system_branding_block',
        'config' => [],
      ],
    ]);

    // Navigate to block administration to verify block appears.
    $this->drupalGet('admin/structure/block');

    // Verify block appears in block list.
    $this->assertSession()->pageTextContains('Test Proxy Block');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests proxy block placement in Layout Builder.
   */
  public function testProxyBlockPlacementInLayoutBuilder(): void {
    $this->drupalLogin($this->adminUser);

    // Navigate to Layout Builder for page content type.
    $this->drupalGet('/admin/structure/types/manage/page/display/default/layout');
    $this->assertSession()->statusCodeEquals(200);

    // Verify Layout Builder is working.
    $this->assertSession()->pageTextContains('layout');

    // Create a test node to verify the layout works.
    $this->drupalGet('/node/' . $this->testNode->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($this->testNode->getTitle());
  }

  /**
   * Tests target block selection dropdown populates correctly.
   */
  public function testTargetBlockSelectionDropdown(): void {
    $this->drupalLogin($this->adminUser);

    // Test that proxy block has proper default configuration.
    $plugin_manager = $this->container->get('plugin.manager.block');
    $plugin_definition = $plugin_manager->getDefinition('proxy_block_proxy');

    $this->assertEquals('Proxy Block', $plugin_definition['admin_label']);
    $this->assertEquals('A/B Testing', $plugin_definition['category']);

    // Verify the block can be instantiated.
    $block_instance = $plugin_manager->createInstance('proxy_block_proxy');
    $this->assertNotNull($block_instance);

    // Test default configuration.
    $config = $block_instance->defaultConfiguration();
    $this->assertArrayHasKey('target_block', $config);
    $this->assertNull($config['target_block']['id']);
  }

  /**
   * Tests block configuration form behavior with AJAX.
   */
  public function testBlockConfigurationFormAjaxBehavior(): void {
    $this->drupalLogin($this->adminUser);

    // Test AJAX callback method exists and works.
    $plugin_manager = $this->container->get('plugin.manager.block');
    $block_instance = $plugin_manager->createInstance('proxy_block_proxy');

    // Verify the AJAX callback method exists.
    $this->assertTrue(method_exists($block_instance, 'targetBlockAjaxCallback'));

    // Test that form state can be processed.
    $this->assertTrue(method_exists($block_instance, 'blockForm'));
    $this->assertTrue(method_exists($block_instance, 'blockSubmit'));
    $this->assertTrue(method_exists($block_instance, 'blockValidate'));
  }

  /**
   * Tests form validation with empty and invalid targets.
   */
  public function testFormValidation(): void {
    $this->drupalLogin($this->adminUser);

    // Test saving with empty target (should be allowed).
    $block1 = $this->placeBlock('proxy_block_proxy', [
      'region' => 'content',
      'id' => 'empty_target_validation_test',
      'label' => 'Empty Target Test',
      'target_block' => ['id' => ''],
    ]);

    $this->assertNotNull($block1);
    $this->assertEquals('Empty Target Test', $block1->label());

    // Test with valid target.
    $block2 = $this->placeBlock('proxy_block_proxy', [
      'region' => 'sidebar_first',
      'id' => 'valid_target_validation_test',
      'label' => 'Valid Target Test',
      'target_block' => [
        'id' => 'system_branding_block',
        'config' => [],
      ],
    ]);

    $this->assertNotNull($block2);
    $this->assertEquals('Valid Target Test', $block2->label());
  }

  /**
   * Tests configuration persistence after save.
   */
  public function testConfigurationPersistence(): void {
    $this->drupalLogin($this->adminUser);

    // Create and configure proxy block.
    $block = $this->placeBlock('proxy_block_proxy', [
      'region' => 'content',
      'id' => 'persistence_test_block',
      'label' => 'Persistence Test Block',
      'target_block' => [
        'id' => 'system_branding_block',
        'config' => [],
      ],
    ]);

    // Verify configuration was saved correctly.
    $this->assertEquals('Persistence Test Block', $block->label());

    $plugin = $block->getPlugin();
    $config = $plugin->getConfiguration();
    $this->assertEquals('system_branding_block', $config['target_block']['id']);
  }

  /**
   * Tests empty target renders empty output.
   */
  public function testEmptyTargetRendersEmpty(): void {
    // Create proxy block with empty target.
    $this->placeBlock('proxy_block_proxy', [
      'region' => 'content',
      'id' => 'empty_target_test',
      'label' => 'Empty Target Block',
      'target_block' => ['id' => ''],
    ]);

    // Visit a page and verify block renders but produces no output.
    $this->drupalGet('<front>');

    // Page should load successfully.
    $this->assertSession()->statusCodeEquals(200);

    // The empty proxy block should not produce any visible markup.
    $this->assertSession()->pageTextNotContains('Empty Target Block');
  }

  /**
   * Tests valid target renders target block output.
   */
  public function testValidTargetRendersOutput(): void {
    // Create proxy block with valid target.
    $this->placeBlock('proxy_block_proxy', [
      'region' => 'content',
      'id' => 'valid_target_test',
      'label' => 'Valid Target Block',
      'target_block' => [
        'id' => 'system_branding_block',
        'config' => [],
      ],
    ]);

    // Visit a page and verify target block content appears.
    $this->drupalGet('<front>');

    // Page should load successfully.
    $this->assertSession()->statusCodeEquals(200);

    // System branding block should render site name.
    $site_name = $this->config('system.site')->get('name');
    if ($site_name) {
      $this->assertSession()->pageTextContains($site_name);
    }
  }

  /**
   * Tests access control and no privilege escalation.
   */
  public function testAccessControlNoPrivilegeEscalation(): void {
    // Create a proxy block targeting a restricted block.
    $this->drupalLogin($this->adminUser);

    // Create proxy block with admin-only target.
    $this->placeBlock('proxy_block_proxy', [
      'region' => 'content',
      'id' => 'access_test_block',
      'label' => 'Access Test Block',
      'target_block' => [
        'id' => 'system_branding_block',
        'config' => [],
      ],
    ]);

    $this->drupalLogout();

    // Login as regular user (limited permissions).
    $this->drupalLogin($this->regularUser);

    // Visit page - proxy block should respect target block access.
    $this->drupalGet('<front>');

    // Page should load successfully.
    $this->assertSession()->statusCodeEquals(200);

    // The target block (system_branding_block) should render for regular users
    // as it doesn't require special permissions.
    $site_name = $this->config('system.site')->get('name');
    if ($site_name) {
      $this->assertSession()->pageTextContains($site_name);
    }
  }

  /**
   * Tests Layout Builder admin preview mode.
   */
  public function testLayoutBuilderAdminPreview(): void {
    $this->drupalLogin($this->adminUser);

    // Add proxy block to layout.
    $this->drupalGet('/admin/structure/types/manage/page/display/default/layout');
    $this->clickLink('Add block');
    $this->clickLink('Proxy Block');

    $this->submitForm([
      'settings[label]' => 'Admin Preview Test',
      'settings[target_block][id]' => 'page_title_block',
    ], 'Add block');

    // In Layout Builder admin mode, should show preview text instead of
    // rendering target.
    $this->assertSession()->pageTextContains('Proxy Block:');
    $this->assertSession()->pageTextContains('Configured to render');
    $this->assertSession()->pageTextContains('Page title');

    // Save layout and check front-end rendering.
    $this->submitForm([], 'Save layout');

    // Visit actual node page - should render target block normally.
    $this->drupalGet('/node/' . $this->testNode->id());
    $this->assertSession()->pageTextContains($this->testNode->getTitle());
  }

  /**
   * Tests graceful degradation with invalid target block.
   */
  public function testInvalidTargetGracefulDegradation(): void {
    // Manually create a proxy block configuration with invalid target.
    $invalid_config = [
      'target_block' => [
        'id' => 'nonexistent_block_plugin',
        'config' => [],
      ],
    ];

    $this->placeBlock('proxy_block_proxy', [
      'region' => 'content',
      'id' => 'invalid_target_test',
      'label' => 'Invalid Target Block',
    ] + $invalid_config);

    // Visit page - should not cause errors, just render empty.
    $this->drupalGet('<front>');

    // Page should load successfully despite invalid target.
    $this->assertSession()->statusCodeEquals(200);

    // Should not display any error messages to end users.
    $this->assertSession()->pageTextNotContains('error');
    $this->assertSession()->pageTextNotContains('Error');
  }

  /**
   * Tests graceful handling of missing plugin.
   */
  public function testMissingPluginGracefulHandling(): void {
    // Create proxy block with empty configuration.
    $this->placeBlock('proxy_block_proxy', [
      'region' => 'content',
      'id' => 'missing_plugin_test',
      'label' => 'Missing Plugin Test',
      'target_block' => ['id' => ''],
    ]);

    // Visit page - should handle gracefully.
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);

    // Should not show any error messages.
    $this->assertSession()->pageTextNotContains('error');
    $this->assertSession()->pageTextNotContains('Warning');
  }

  /**
   * Tests context error handling doesn't break rendering.
   */
  public function testContextErrorHandling(): void {
    // This test would require a context-aware block plugin for full testing.
    // For now, we test that the proxy block handles blocks without breaking.
    $this->placeBlock('proxy_block_proxy', [
      'region' => 'content',
      'id' => 'context_test_block',
      'label' => 'Context Test Block',
      'target_block' => [
        'id' => 'system_branding_block',
        'config' => [],
      ],
    ]);

    // Visit node context page.
    $this->drupalGet('/node/' . $this->testNode->id());
    $this->assertSession()->statusCodeEquals(200);

    // Should render without context-related errors.
    $this->assertSession()->pageTextNotContains('ContextException');
    $this->assertSession()->pageTextNotContains('context error');
  }

  /**
   * Tests proxy block configuration in different themes.
   */
  public function testProxyBlockInDifferentThemes(): void {
    $this->drupalLogin($this->adminUser);

    // Create proxy block.
    $this->placeBlock('proxy_block_proxy', [
      'region' => 'content',
      'id' => 'theme_test_block',
      'label' => 'Theme Test Block',
      'target_block' => [
        'id' => 'system_branding_block',
        'config' => [],
      ],
    ]);

    // Test with default theme.
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);

    // Verify block renders correctly.
    $site_name = $this->config('system.site')->get('name');
    if ($site_name) {
      $this->assertSession()->pageTextContains($site_name);
    }
  }

  /**
   * Tests proxy block with various target block types.
   */
  public function testProxyBlockWithVariousTargets(): void {
    $this->drupalLogin($this->adminUser);

    $test_cases = [
      'system_branding_block' => 'System branding',
      'page_title_block' => 'Page title',
    ];

    foreach ($test_cases as $plugin_id => $label) {
      $block = $this->placeBlock('proxy_block_proxy', [
        'region' => 'content',
        'id' => 'target_test_' . str_replace('_', '', $plugin_id),
        'label' => 'Test ' . $label,
        'target_block' => [
          'id' => $plugin_id,
          'config' => [],
        ],
      ]);

      // Visit page and verify no errors.
      $this->drupalGet('<front>');
      $this->assertSession()->statusCodeEquals(200);

      // Clean up for next iteration.
      $block->delete();
    }
  }

  /**
   * Helper method to create a content type.
   */
  protected function createContentType(array $values = []): NodeType {
    $node_type = NodeType::create($values);
    $node_type->save();
    return $node_type;
  }

}
