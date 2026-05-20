<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\Unit;

use Drupal\Core\Form\FormStateInterface;
use Drupal\proxy_block\Plugin\Block\ProxyBlock;
use Drupal\proxy_block\Service\ProxyBlockRenderer;
use Drupal\proxy_block\Service\TargetBlockFormProcessor;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for ProxyBlock plugin.
 *
 * The plugin itself is now a thin shell that delegates form, render, and
 * cache responsibilities to two services. These tests verify the
 * delegations; the actual render/cache pipelines are covered by
 * ProxyBlockRendererTest and the per-service tests.
 *
 * @coversDefaultClass \Drupal\proxy_block\Plugin\Block\ProxyBlock
 * @group proxy_block
 */
final class ProxyBlockTest extends ProxyBlockUnitTestBase {

  /**
   * Mock form processor.
   */
  private TargetBlockFormProcessor|MockObject $formProcessorMock;

  /**
   * Mock renderer.
   */
  private ProxyBlockRenderer|MockObject $rendererMock;

  /**
   * Builds a ProxyBlock with the given configuration and the shared mocks.
   */
  private function makeProxyBlock(array $configuration = []): ProxyBlock {
    return new ProxyBlock(
      $configuration,
      'proxy_block_proxy',
      ['admin_label' => 'Proxy Block', 'provider' => 'proxy_block'],
      $this->formProcessorMock,
      $this->rendererMock,
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->formProcessorMock = $this->createMock(TargetBlockFormProcessor::class);
    $this->rendererMock = $this->createMock(ProxyBlockRenderer::class);
  }

  /**
   * @covers ::defaultConfiguration
   */
  public function testDefaultConfiguration(): void {
    $proxy = $this->makeProxyBlock();
    $result = $proxy->defaultConfiguration();

    $this->assertArrayHasKey('target_block', $result);
    $this->assertEquals(['id' => NULL, 'config' => []], $result['target_block']);
  }

  /**
   * @covers ::blockForm
   */
  public function testBlockFormBasicStructure(): void {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $proxy = $this->makeProxyBlock();

    $this->formProcessorMock
      ->expects($this->once())
      ->method('getAvailableBlockOptions')
      ->with('proxy_block_proxy')
      ->willReturn([
        'system_branding_block' => 'Site branding',
        'user_login_block' => 'User login',
      ]);

    $this->formProcessorMock
      ->expects($this->once())
      ->method('getSelectedTargetFromFormState')
      ->with($form_state)
      ->willReturn(NULL);

    $result = $proxy->blockForm($form, $form_state);

    $this->assertArrayHasKey('target_block', $result);
    $this->assertEquals('select', $result['target_block']['id']['#type']);
    $options = $result['target_block']['id']['#options'];
    $this->assertArrayHasKey('', $options);
    $this->assertArrayHasKey('system_branding_block', $options);
    $this->assertArrayNotHasKey('proxy_block_proxy', $options);
  }

  /**
   * @covers ::blockForm
   */
  public function testBlockFormWithSelectedTarget(): void {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $config = ['target_block' => ['id' => 'system_branding_block', 'config' => []]];
    $proxy = $this->makeProxyBlock($config);
    $expected_config = $proxy->getConfiguration();

    $this->formProcessorMock
      ->expects($this->once())
      ->method('getAvailableBlockOptions')
      ->willReturn(['system_branding_block' => 'Site branding']);

    $this->formProcessorMock
      ->expects($this->once())
      ->method('getSelectedTargetFromFormState')
      ->with($form_state)
      ->willReturn(['id' => 'system_branding_block']);

    $this->formProcessorMock
      ->expects($this->once())
      ->method('buildTargetBlockConfigurationForm')
      ->with('system_branding_block', $expected_config, $form_state)
      ->willReturn(['#markup' => 'Config form']);

    $result = $proxy->blockForm($form, $form_state);

    $this->assertEquals('Config form', $result['target_block']['config']['#markup']);
  }

  /**
   * @covers ::targetBlockAjaxCallback
   */
  public function testTargetBlockAjaxCallback(): void {
    $form = [
      'settings' => [
        'target_block' => [
          'config' => ['#markup' => 'target config'],
        ],
      ],
    ];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state
      ->expects($this->once())
      ->method('getTriggeringElement')
      ->willReturn(['#array_parents' => ['settings', 'target_block', 'id']]);

    $result = $this->makeProxyBlock()->targetBlockAjaxCallback($form, $form_state);

    $this->assertEquals(['#markup' => 'target config'], $result);
  }

  /**
   * @covers ::blockValidate
   */
  public function testBlockValidateDelegates(): void {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $proxy = $this->makeProxyBlock();

    $this->formProcessorMock
      ->expects($this->once())
      ->method('validateTargetBlock')
      ->with($form, $form_state, $proxy->getConfiguration());

    $proxy->blockValidate($form, $form_state);
  }

  /**
   * @covers ::blockSubmit
   */
  public function testBlockSubmitDelegates(): void {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $proxy = $this->makeProxyBlock();
    $existing_config = $proxy->getConfiguration();
    $new_config = ['target_block' => ['id' => 'test_block', 'config' => []]];

    $this->formProcessorMock
      ->expects($this->once())
      ->method('submitTargetBlock')
      ->with($form, $form_state, $existing_config)
      ->willReturn($new_config);

    $proxy->blockSubmit($form, $form_state);

    $this->assertEquals($new_config, $proxy->getConfiguration());
  }

  /**
   * @covers ::build
   */
  public function testBuildDelegatesToRenderer(): void {
    $proxy = $this->makeProxyBlock();
    $rendered = ['#markup' => 'rendered'];

    $this->rendererMock
      ->expects($this->once())
      ->method('render')
      ->with(
        $proxy->getConfiguration(),
        $this->isType('array'),
        $this->isType('array'),
        $this->isType('array'),
        $this->isType('int'),
        'proxy_block_proxy',
        $proxy,
      )
      ->willReturn($rendered);

    $this->assertEquals($rendered, $proxy->build());
  }

  /**
   * @covers ::getCacheContexts
   */
  public function testGetCacheContextsDelegatesToRenderer(): void {
    $proxy = $this->makeProxyBlock();
    $expected = ['user.permissions', 'route'];

    $this->rendererMock
      ->expects($this->once())
      ->method('collectCacheContexts')
      ->with($proxy->getConfiguration(), $this->isType('array'))
      ->willReturn($expected);

    $this->assertEquals($expected, $proxy->getCacheContexts());
  }

  /**
   * @covers ::getCacheTags
   */
  public function testGetCacheTagsDelegatesToRenderer(): void {
    $proxy = $this->makeProxyBlock();
    $expected = ['config:foo', 'proxy_block_proxy:proxy_block_proxy'];

    $this->rendererMock
      ->expects($this->once())
      ->method('collectCacheTags')
      ->with($proxy->getConfiguration(), $this->isType('array'), 'proxy_block_proxy')
      ->willReturn($expected);

    $this->assertEquals($expected, $proxy->getCacheTags());
  }

  /**
   * @covers ::getCacheMaxAge
   */
  public function testGetCacheMaxAgeDelegatesToRenderer(): void {
    $proxy = $this->makeProxyBlock();

    $this->rendererMock
      ->expects($this->once())
      ->method('collectCacheMaxAge')
      ->with($proxy->getConfiguration(), $this->isType('int'))
      ->willReturn(3600);

    $this->assertEquals(3600, $proxy->getCacheMaxAge());
  }

  /**
   * @covers ::getContextDefinitions
   */
  public function testGetContextDefinitionsMirrorsResolvedTarget(): void {
    $config = ['target_block' => ['id' => 'target_x', 'config' => []]];
    $proxy = $this->makeProxyBlock($config);

    $node_def = $this->createMockContextDefinition(TRUE, 'entity:node', 'Node');
    $user_def = $this->createMockContextDefinition(FALSE, 'entity:user', 'User');

    $this->rendererMock
      ->expects($this->once())
      ->method('resolveTargetPluginDefinition')
      ->with($this->callback(static fn ($c) => ($c['target_block']['id'] ?? NULL) === 'target_x'))
      ->willReturn([
        'context_definitions' => [
          'node' => $node_def,
          'user' => $user_def,
        ],
      ]);

    $definitions = $proxy->getContextDefinitions();

    $this->assertArrayHasKey('node', $definitions);
    $this->assertArrayHasKey('user', $definitions);
  }

  /**
   * @covers ::getContextDefinitions
   */
  public function testGetContextDefinitionsWithoutResolvedTarget(): void {
    $proxy = $this->makeProxyBlock();

    $this->rendererMock
      ->expects($this->once())
      ->method('resolveTargetPluginDefinition')
      ->willReturn(NULL);

    $definitions = $proxy->getContextDefinitions();

    $this->assertArrayNotHasKey('node', $definitions);
  }

}
