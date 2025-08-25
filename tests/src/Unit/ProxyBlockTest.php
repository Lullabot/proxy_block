<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\proxy_block\Plugin\Block\ProxyBlock;

/**
 * Unit tests for ProxyBlock plugin.
 *
 * @coversDefaultClass \Drupal\proxy_block\Plugin\Block\ProxyBlock
 * @group proxy_block
 */
final class ProxyBlockTest extends ProxyBlockUnitTestBase {

  /**
   * The proxy block instance.
   */
  private ProxyBlock $proxyBlock;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->proxyBlock = new ProxyBlock(
      [],
      'proxy_block_proxy',
      ['admin_label' => 'Proxy Block', 'provider' => 'proxy_block'],
      $this->blockManager,
      $this->currentUser,
      $this->requestStack,
      $this->targetBlockFactory,
      $this->formProcessor,
      $this->cacheManager
    );
  }

  /**
   * @covers ::defaultConfiguration
   */
  public function testDefaultConfiguration(): void {
    $result = $this->proxyBlock->defaultConfiguration();

    // Test the specific proxy_block additions.
    $this->assertArrayHasKey('target_block', $result);
    $this->assertEquals([
      'id' => NULL,
      'config' => [],
    ], $result['target_block']);
  }

  /**
   * @covers ::blockForm
   */
  public function testBlockFormBasicStructure(): void {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);

    $block_definitions = $this->createCommonBlockDefinitions(FALSE);

    $this->blockManager
      ->expects($this->once())
      ->method('getDefinitions')
      ->willReturn($block_definitions);

    $this->formProcessor
      ->expects($this->once())
      ->method('getSelectedTargetFromFormState')
      ->with($form_state)
      ->willReturn(NULL);

    $result = $this->proxyBlock->blockForm($form, $form_state);

    $this->assertArrayHasKey('target_block', $result);
    $this->assertArrayHasKey('id', $result['target_block']);
    $this->assertArrayHasKey('config', $result['target_block']);

    $this->assertEquals('select', $result['target_block']['id']['#type']);
    $this->assertInstanceOf('Drupal\Core\StringTranslation\TranslatableMarkup', $result['target_block']['id']['#title']);
    $this->assertTranslatableMarkup($result['target_block']['id']['#title'], 'Target Block');

    $options = $result['target_block']['id']['#options'];
    $this->assertArrayHasKey('', $options);
    $this->assertArrayHasKey('system_branding_block', $options);
    $this->assertArrayHasKey('user_login_block', $options);
    $this->assertArrayNotHasKey('proxy_block_proxy', $options);
  }

  /**
   * @covers ::blockForm
   */
  public function testBlockFormExcludesSelf(): void {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);

    $block_definitions = $this->createCommonBlockDefinitions(FALSE);

    $this->blockManager
      ->expects($this->once())
      ->method('getDefinitions')
      ->willReturn($block_definitions);

    $this->formProcessor
      ->expects($this->once())
      ->method('getSelectedTargetFromFormState')
      ->with($form_state)
      ->willReturn(NULL);

    $result = $this->proxyBlock->blockForm($form, $form_state);

    $options = $result['target_block']['id']['#options'];
    $this->assertArrayNotHasKey('proxy_block_proxy', $options);
    $this->assertArrayHasKey('system_branding_block', $options);
  }

  /**
   * @covers ::blockForm
   */
  public function testBlockFormWithSelectedTarget(): void {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $config = [
      'target_block' => [
        'id' => 'system_branding_block',
        'config' => [],
      ],
    ];

    $this->blockManager
      ->expects($this->once())
      ->method('getDefinitions')
      ->willReturn($this->createCommonBlockDefinitions());

    $proxy_block_with_config = new ProxyBlock(
      $config,
      'proxy_block_proxy',
      ['admin_label' => 'Proxy Block', 'provider' => 'proxy_block'],
      $this->blockManager,
      $this->currentUser,
      $this->requestStack,
      $this->targetBlockFactory,
      $this->formProcessor,
      $this->cacheManager
    );

    $expected_config = $proxy_block_with_config->getConfiguration();

    $this->formProcessor
      ->expects($this->once())
      ->method('getSelectedTargetFromFormState')
      ->with($form_state)
      ->willReturn(['id' => 'system_branding_block']);

    $this->formProcessor
      ->expects($this->once())
      ->method('buildTargetBlockConfigurationForm')
      ->with('system_branding_block', $expected_config)
      ->willReturn(['#markup' => 'Config form']);

    $result = $proxy_block_with_config->blockForm($form, $form_state);

    $this->assertArrayHasKey('#markup', $result['target_block']['config']);
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

    $triggering_element = [
      '#array_parents' => ['settings', 'target_block', 'id'],
    ];

    $form_state
      ->expects($this->once())
      ->method('getTriggeringElement')
      ->willReturn($triggering_element);

    $result = $this->proxyBlock->targetBlockAjaxCallback($form, $form_state);

    $this->assertEquals(['#markup' => 'target config'], $result);
  }

  /**
   * @covers ::blockValidate
   */
  public function testBlockValidate(): void {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);

    $expected_config = $this->proxyBlock->getConfiguration();

    $this->formProcessor
      ->expects($this->once())
      ->method('validateTargetBlock')
      ->with($form_state, $expected_config);

    $this->proxyBlock->blockValidate($form, $form_state);
  }

  /**
   * @covers ::blockSubmit
   */
  public function testBlockSubmit(): void {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $expected_config = [
      'target_block' => [
        'id' => 'test_block',
        'config' => [],
      ],
      'id' => 'proxy_block_proxy',
      'label' => 'Proxy Block',
      'label_display' => 'visible',
      'provider' => 'proxy_block',
    ];

    $this->formProcessor
      ->expects($this->once())
      ->method('submitTargetBlock')
      ->with($form_state)
      ->willReturn($expected_config);

    $this->proxyBlock->blockSubmit($form, $form_state);

    $this->assertEquals($expected_config, $this->proxyBlock->getConfiguration());
  }

  /**
   * @covers ::build
   */
  public function testBuildWithNoTargetBlock(): void {
    $expected_config = $this->proxyBlock->getConfiguration();

    $this->targetBlockFactory
      ->expects($this->once())
      ->method('getTargetBlock')
      ->with($expected_config)
      ->willReturn(NULL);

    $result = $this->proxyBlock->build();

    $this->assertEquals([], $result);
  }

  /**
   * @covers ::build
   */
  public function testBuildInLayoutBuilderAdminMode(): void {
    $config = ['target_block' => ['id' => 'system_branding_block']];

    $target_block = $this->createMock(BlockBase::class);
    $request = $this->createLayoutBuilderAdminRequest();

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $this->blockManager
      ->expects($this->once())
      ->method('getDefinition')
      ->with('system_branding_block')
      ->willReturn($this->createSystemBrandingDefinition());

    $proxy_block = new ProxyBlock(
      $config,
      'proxy_block_proxy',
      ['admin_label' => 'Proxy Block', 'provider' => 'proxy_block'],
      $this->blockManager,
      $this->currentUser,
      $this->requestStack,
      $this->targetBlockFactory,
      $this->formProcessor,
      $this->cacheManager
    );

    $expected_config = $proxy_block->getConfiguration();

    $this->targetBlockFactory
      ->expects($this->exactly(4))
      ->method('getTargetBlock')
      ->with($expected_config)
      ->willReturn($target_block);

    $result = $proxy_block->build();

    $this->assertArrayHasKey('#markup', $result);
    $this->assertStringContainsString('Proxy Block:', $result['#markup']);
    $this->assertStringContainsString('layout-builder-block', $result['#markup']);
    $this->assertArrayHasKey('#cache', $result);
  }

  /**
   * @covers ::build
   */
  public function testBuildWithAccessDenied(): void {
    $target_block = $this->createMock(BlockBase::class);
    $access_result = AccessResult::forbidden();
    $expected_config = $this->proxyBlock->getConfiguration();

    $this->targetBlockFactory
      ->expects($this->exactly(4))
      ->method('getTargetBlock')
      ->with($expected_config)
      ->willReturn($target_block);

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn(NULL);

    $target_block
      ->expects($this->once())
      ->method('access')
      ->with($this->currentUser, TRUE)
      ->willReturn($access_result);

    $result = $this->proxyBlock->build();

    $this->assertArrayHasKey('#markup', $result);
    $this->assertEquals('', $result['#markup']);
    $this->assertArrayHasKey('#cache', $result);
  }

  /**
   * @covers ::build
   */
  public function testBuildWithAccessAllowed(): void {
    $target_block = $this->createMock(BlockBase::class);
    $access_result = AccessResult::allowed();
    $expected_build = ['#markup' => 'Target block content'];
    $expected_config = $this->proxyBlock->getConfiguration();

    $this->targetBlockFactory
      ->expects($this->once())
      ->method('getTargetBlock')
      ->with($expected_config)
      ->willReturn($target_block);

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn(NULL);

    $target_block
      ->expects($this->once())
      ->method('access')
      ->with($this->currentUser, TRUE)
      ->willReturn($access_result);

    $target_block
      ->expects($this->once())
      ->method('build')
      ->willReturn($expected_build);

    $this->cacheManager
      ->expects($this->once())
      ->method('bubbleTargetBlockCacheMetadata')
      ->with($expected_build, $target_block, $this->proxyBlock);

    $result = $this->proxyBlock->build();

    $this->assertEquals($expected_build, $result);
  }

  /**
   * @covers ::getCacheContexts
   */
  public function testGetCacheContexts(): void {
    $target_block = $this->createMock(BlockBase::class);
    $expected_contexts = ['user.permissions', 'route'];

    $this->targetBlockFactory
      ->expects($this->once())
      ->method('getTargetBlock')
      ->with($this->proxyBlock->getConfiguration())
      ->willReturn($target_block);

    $this->cacheManager
      ->expects($this->once())
      ->method('getCacheContexts')
      ->with($target_block, $this->anything())
      ->willReturn($expected_contexts);

    $result = $this->proxyBlock->getCacheContexts();

    $this->assertEquals($expected_contexts, $result);
  }

  /**
   * @covers ::getCacheTags
   */
  public function testGetCacheTags(): void {
    $target_block = $this->createMock(BlockBase::class);
    $expected_tags = [
      'config:block.block.test',
      'block_plugin:system_branding_block',
    ];

    $this->targetBlockFactory
      ->expects($this->once())
      ->method('getTargetBlock')
      ->with($this->proxyBlock->getConfiguration())
      ->willReturn($target_block);

    $this->cacheManager
      ->expects($this->once())
      ->method('getCacheTags')
      ->with($target_block, $this->anything())
      ->willReturn($expected_tags);

    $result = $this->proxyBlock->getCacheTags();

    $expected_tags[] = 'proxy_block_proxy:proxy_block_proxy';
    $this->assertEquals($expected_tags, $result);
  }

  /**
   * @covers ::getCacheMaxAge
   */
  public function testGetCacheMaxAge(): void {
    $target_block = $this->createMock(BlockBase::class);
    $expected_max_age = 3600;

    $this->targetBlockFactory
      ->expects($this->once())
      ->method('getTargetBlock')
      ->with($this->proxyBlock->getConfiguration())
      ->willReturn($target_block);

    $this->cacheManager
      ->expects($this->once())
      ->method('getCacheMaxAge')
      ->with($target_block, $this->anything())
      ->willReturn($expected_max_age);

    $result = $this->proxyBlock->getCacheMaxAge();

    $this->assertEquals($expected_max_age, $result);
  }

}
