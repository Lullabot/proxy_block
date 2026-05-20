<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\Kernel;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Core\Plugin\Context\ContextDefinitionInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel test for ProxyBlock context-definition mirroring.
 *
 * @group proxy_block
 * @coversDefaultClass \Drupal\proxy_block\Plugin\Block\ProxyBlock
 */
final class ProxyBlockContextDefinitionsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'block',
    'proxy_block',
    'proxy_block_test',
  ];

  /**
   * Creates a ProxyBlock instance configured for a specific target.
   */
  private function createProxyBlock(string $target_id): object {
    /** @var \Drupal\Core\Block\BlockManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.block');
    return $manager->createInstance('proxy_block_proxy', [
      'target_block' => [
        'id' => $target_id,
        'config' => [],
      ],
    ]);
  }

  /**
   * Target-mirrored names are returned by getContextDefinitions().
   *
   * @covers ::getContextDefinitions
   */
  public function testGetContextDefinitionsMirrorsTarget(): void {
    $proxy = $this->createProxyBlock('proxy_block_test_context_aware');
    $definitions = $proxy->getContextDefinitions();

    $this->assertArrayHasKey('node', $definitions, 'Target node context is mirrored.');
    $this->assertArrayHasKey('user', $definitions, 'Target user context is mirrored.');
    $this->assertInstanceOf(ContextDefinitionInterface::class, $definitions['node']);
    $this->assertInstanceOf(ContextDefinitionInterface::class, $definitions['user']);
    $this->assertSame('entity:node', $definitions['node']->getDataType());
    $this->assertSame('entity:user', $definitions['user']->getDataType());
  }

  /**
   * With no target configured, only parent definitions are returned.
   *
   * @covers ::getContextDefinitions
   */
  public function testGetContextDefinitionsWithoutTarget(): void {
    $proxy = $this->createProxyBlock('');
    $definitions = $proxy->getContextDefinitions();

    $this->assertArrayNotHasKey('node', $definitions);
    $this->assertArrayNotHasKey('user', $definitions);
  }

  /**
   * Tests that getContextDefinition() resolves through the merged map.
   *
   * @covers ::getContextDefinition
   */
  public function testGetContextDefinitionResolvesTargetMirrored(): void {
    $proxy = $this->createProxyBlock('proxy_block_test_context_aware');

    $node_def = $proxy->getContextDefinition('node');
    $user_def = $proxy->getContextDefinition('user');

    $this->assertInstanceOf(ContextDefinitionInterface::class, $node_def);
    $this->assertInstanceOf(ContextDefinitionInterface::class, $user_def);
    $this->assertSame('entity:node', $node_def->getDataType());
    $this->assertSame('entity:user', $user_def->getDataType());
  }

  /**
   * Unknown names fall through to parent and throw ContextException.
   *
   * @covers ::getContextDefinition
   */
  public function testGetContextDefinitionUnknownThrows(): void {
    $proxy = $this->createProxyBlock('proxy_block_test_context_aware');

    $this->expectException(ContextException::class);
    $proxy->getContextDefinition('definitely_not_a_real_context');
  }

  /**
   * Stale target id (deleted plugin) degrades to parent definitions.
   *
   * @covers ::getContextDefinitions
   */
  public function testGetContextDefinitionsWithStaleTarget(): void {
    $proxy = $this->createProxyBlock('this_plugin_does_not_exist');
    $definitions = $proxy->getContextDefinitions();

    $this->assertIsArray($definitions);
    $this->assertArrayNotHasKey('node', $definitions);
  }

}
