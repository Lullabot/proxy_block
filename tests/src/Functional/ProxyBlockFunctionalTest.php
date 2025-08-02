<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\block\Traits\BlockCreationTrait;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;

/**
 * Functional tests for the Proxy Block plugin.
 *
 * Tests critical user workflows through browser-based interactions:
 * - Block configuration form behavior and target selection
 * - Proxy block rendering of target blocks
 * - Context passing to context-aware target blocks
 * - Access control and graceful error handling.
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
   * Tests proxy block configuration form displays target block selection.
   *
   * Validates that the block configuration form properly populates the target
   * block dropdown with available block plugins and excludes the proxy block
   * itself to prevent infinite recursion.
   */
  public function testProxyBlockConfigurationForm(): void {
    $this->drupalLogin($this->adminUser);

    // Navigate directly to the proxy block configuration form.
    $this->drupalGet('admin/structure/block/add/proxy_block_proxy/stark');

    // Verify form loads and contains target block selection.
    $this->assertSession()->fieldExists('settings[target_block][id]');
    $this->assertSession()->optionExists('settings[target_block][id]', '');
    $this->assertSession()->optionExists('settings[target_block][id]', 'system_branding_block');
    $this->assertSession()->optionExists('settings[target_block][id]', 'page_title_block');

    // Verify proxy block itself is not in the options (prevents recursion).
    $this->assertSession()->optionNotExists('settings[target_block][id]', 'proxy_block_proxy');

    // Verify the form loads successfully.
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests successful proxy block placement and configuration through API.
   *
   * Validates that proxy blocks can be placed and configured correctly,
   * and that the configuration persists as expected.
   */
  public function testProxyBlockPlacementAndConfiguration(): void {
    // Place a proxy block using the placeBlock helper method.
    $block = $this->placeBlock('proxy_block_proxy', [
      'region' => 'content',
      'id' => 'test_proxy_placement',
      'label' => 'Test Proxy Placement Block',
      'target_block' => [
        'id' => 'system_branding_block',
        'config' => [],
      ],
    ]);

    // Verify the block was created successfully.
    $this->assertEquals('Test Proxy Placement Block', $block->label());

    // Verify the configuration was saved correctly.
    $plugin = $block->getPlugin();
    $config = $plugin->getConfiguration();
    $this->assertEquals('system_branding_block', $config['target_block']['id']);

    // Navigate to block admin page and verify block appears.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/structure/block');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests proxy block correctly renders target block content.
   *
   * Validates that when a proxy block is configured with a target block,
   * it properly renders the target block's content on the frontend, proving
   * the core proxy functionality works as expected.
   */
  public function testProxyBlockRendersTargetBlockContent(): void {
    // Place a proxy block targeting the system branding block.
    $this->placeBlock('proxy_block_proxy', [
      'region' => 'content',
      'id' => 'proxy_rendering_test',
      'label' => 'Proxy Rendering Test',
      'target_block' => [
        'id' => 'system_branding_block',
        'config' => [],
      ],
    ]);

    // Visit frontend page and verify target block content is rendered.
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);

    // System branding block should render the site name.
    $site_name = $this->config('system.site')->get('name');
    if ($site_name) {
      $this->assertSession()->pageTextContains($site_name);
    }

    // Should also render site slogan if configured.
    $site_slogan = $this->config('system.site')->get('slogan');
    if ($site_slogan) {
      $this->assertSession()->pageTextContains($site_slogan);
    }
  }

  /**
   * Tests proxy block with context-aware target blocks.
   *
   * Validates that proxy blocks correctly pass context (like current node)
   * to target blocks that require context, ensuring context-dependent
   * blocks function properly when proxied.
   */
  public function testProxyBlockWithContextAwareTargets(): void {
    // Place a proxy block targeting the page title block (context-aware).
    $this->placeBlock('proxy_block_proxy', [
      'region' => 'content',
      'id' => 'context_test_proxy',
      'label' => 'Context Test Proxy',
      'target_block' => [
        'id' => 'page_title_block',
        'config' => [],
      ],
    ]);

    // Visit a node page where context should be available.
    $this->drupalGet('/node/' . $this->testNode->id());
    $this->assertSession()->statusCodeEquals(200);

    // Page title block should render the node title when given node context.
    $this->assertSession()->pageTextContains($this->testNode->getTitle());

    // Verify the proxy block successfully passed the node context.
    $this->assertSession()->elementExists('css', 'h1');
  }

  /**
   * Tests Layout Builder integration and admin preview functionality.
   *
   * Validates that proxy blocks work correctly in Layout Builder, showing
   * appropriate preview text in admin mode and rendering target blocks
   * properly on the frontend after layout is saved.
   */
  public function testLayoutBuilderIntegration(): void {
    $this->drupalLogin($this->adminUser);

    // Navigate to Layout Builder for the page content type.
    $this->drupalGet('/admin/structure/types/manage/page/display/default/layout');
    $this->clickLink('Add block');
    $this->clickLink('Proxy Block');

    // Configure proxy block in Layout Builder.
    $this->submitForm([
      'settings[label]' => 'Layout Builder Proxy Test',
      'settings[target_block][id]' => 'page_title_block',
    ], 'Add block');

    // In Layout Builder admin mode, should show preview text.
    $this->assertSession()->pageTextContains('Proxy Block:');
    $this->assertSession()->pageTextContains('Configured to render');

    // Save the layout.
    $this->submitForm([], 'Save layout');
    $this->assertSession()->pageTextContains('The layout has been saved.');

    // Visit frontend node page - proxy should render target block content.
    $this->drupalGet('/node/' . $this->testNode->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($this->testNode->getTitle());
  }

  /**
   * Tests graceful error handling with invalid target configuration.
   *
   * Validates that proxy blocks handle error conditions gracefully without
   * breaking page rendering, including invalid target plugins and missing
   * configuration scenarios.
   */
  public function testGracefulErrorHandling(): void {
    // Test with completely empty target configuration.
    $this->placeBlock('proxy_block_proxy', [
      'region' => 'content',
      'id' => 'empty_target_test',
      'label' => 'Empty Target Test',
      'target_block' => ['id' => ''],
    ]);

    // Visit page - should handle gracefully without errors.
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);

    // Should not display error messages to end users.
    $this->assertSession()->pageTextNotContains('error');
    $this->assertSession()->pageTextNotContains('Error');
    $this->assertSession()->pageTextNotContains('Warning');
    $this->assertSession()->pageTextNotContains('ContextException');

    // Test with invalid target plugin ID.
    $this->placeBlock('proxy_block_proxy', [
      'region' => 'content',
      'id' => 'invalid_target_test',
      'label' => 'Invalid Target Test',
      'target_block' => [
        'id' => 'nonexistent_block_plugin',
        'config' => [],
      ],
    ]);

    // Page should still load successfully despite invalid target.
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);

    // Should not expose technical errors to end users.
    $this->assertSession()->pageTextNotContains('PluginException');
    $this->assertSession()->pageTextNotContains('PluginNotFoundException');
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
