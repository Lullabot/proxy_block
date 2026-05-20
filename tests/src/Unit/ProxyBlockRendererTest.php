<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\proxy_block\Service\ProxyBlockRenderer;
use Drupal\proxy_block\Service\TargetBlockCacheManager;
use Drupal\proxy_block\Service\TargetBlockContextManager;
use Drupal\proxy_block\Service\TargetBlockFactory;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit tests for the ProxyBlockRenderer service.
 *
 * @coversDefaultClass \Drupal\proxy_block\Service\ProxyBlockRenderer
 * @group proxy_block
 */
final class ProxyBlockRendererTest extends UnitTestCase {

  /**
   * Mock target block factory.
   */
  private TargetBlockFactory|MockObject $targetBlockFactory;

  /**
   * Mock context manager.
   */
  private TargetBlockContextManager|MockObject $contextManager;

  /**
   * Mock cache manager.
   */
  private TargetBlockCacheManager|MockObject $cacheManager;

  /**
   * Mock block manager.
   */
  private BlockManagerInterface|MockObject $blockManager;

  /**
   * Mock current user.
   */
  private AccountProxyInterface|MockObject $currentUser;

  /**
   * Mock request stack.
   */
  private RequestStack|MockObject $requestStack;

  /**
   * Mock logger.
   */
  private LoggerInterface|MockObject $logger;

  /**
   * The renderer under test.
   */
  private ProxyBlockRenderer $renderer;

  /**
   * Stand-in for the proxy plugin's cache-bubbling identity.
   */
  private CacheableDependencyInterface|MockObject $proxy;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->targetBlockFactory = $this->createMock(TargetBlockFactory::class);
    $this->contextManager = $this->createMock(TargetBlockContextManager::class);
    $this->cacheManager = $this->createMock(TargetBlockCacheManager::class);
    $this->blockManager = $this->createMock(BlockManagerInterface::class);
    $this->currentUser = $this->createMock(AccountProxyInterface::class);
    $this->requestStack = $this->createMock(RequestStack::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    $this->renderer = new ProxyBlockRenderer(
      $this->targetBlockFactory,
      $this->contextManager,
      $this->cacheManager,
      $this->blockManager,
      $this->currentUser,
      $this->requestStack,
      $this->logger,
    );

    $this->renderer->setStringTranslation($this->getStringTranslationStub());

    $this->proxy = $this->createMock(CacheableDependencyInterface::class);
  }

  /**
   * Shortcut: invokes render() with sensible defaults.
   */
  private function callRender(array $configuration): array {
    return $this->renderer->render(
      $configuration,
      [],
      [],
      [],
      0,
      'proxy_block_proxy',
      $this->proxy,
    );
  }

  /**
   * @covers ::render
   */
  public function testRenderWithNoTargetBlockReturnsEmpty(): void {
    $config = ['target_block' => ['id' => '']];

    $this->targetBlockFactory
      ->expects($this->once())
      ->method('getTargetBlock')
      ->with($config)
      ->willReturn(NULL);

    $this->assertEquals([], $this->callRender($config));
  }

  /**
   * @covers ::render
   */
  public function testRenderWithAccessDeniedReturnsEmptyMarkup(): void {
    $config = ['target_block' => ['id' => 'system_branding_block']];
    $target_block = $this->createMock(BlockBase::class);

    $this->targetBlockFactory->method('getTargetBlock')->willReturn($target_block);
    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);
    $this->cacheManager->method('getCacheContexts')->willReturn([]);
    $this->cacheManager->method('getCacheTags')->willReturn([]);
    $this->cacheManager->method('getCacheMaxAge')->willReturn(0);

    $target_block
      ->expects($this->once())
      ->method('access')
      ->with($this->currentUser, TRUE)
      ->willReturn(AccessResult::forbidden());

    $result = $this->callRender($config);

    $this->assertSame('', $result['#markup']);
    $this->assertArrayHasKey('#cache', $result);
  }

  /**
   * @covers ::render
   */
  public function testRenderWithAccessAllowedBubblesCache(): void {
    $config = ['target_block' => ['id' => 'system_branding_block']];
    $target_block = $this->createMock(BlockBase::class);

    $this->targetBlockFactory->method('getTargetBlock')->willReturn($target_block);
    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $target_block->method('access')->willReturn(AccessResult::allowed());
    $target_block->method('build')->willReturn(['#markup' => 'rendered']);

    $this->cacheManager
      ->expects($this->once())
      ->method('bubbleTargetBlockCacheMetadata');

    $result = $this->callRender($config);

    $this->assertEquals(['#markup' => 'rendered'], $result);
  }

  /**
   * @covers ::render
   * @covers ::isLayoutBuilderAdminRoute
   * @covers ::buildLayoutBuilderAdminPreview
   */
  public function testRenderInLayoutBuilderAdminMode(): void {
    $config = ['target_block' => ['id' => 'system_branding_block']];
    $target_block = $this->createMock(BlockBase::class);

    $this->targetBlockFactory->method('getTargetBlock')->willReturn($target_block);
    $this->cacheManager->method('getCacheContexts')->willReturn([]);
    $this->cacheManager->method('getCacheTags')->willReturn([]);
    $this->cacheManager->method('getCacheMaxAge')->willReturn(0);

    $request = $this->createMock(Request::class);
    $request->method('getPathInfo')->willReturn('/admin/structure/types/article/display/default/layout');
    $request->query = new ParameterBag();
    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $this->blockManager
      ->expects($this->once())
      ->method('getDefinition')
      ->with('system_branding_block', FALSE)
      ->willReturn(['admin_label' => 'Site branding']);

    $result = $this->callRender($config);

    $this->assertStringContainsString('Proxy Block:', $result['#markup']);
    $this->assertStringContainsString('Site branding', $result['#markup']);
  }

  /**
   * @covers ::collectCacheContexts
   */
  public function testCollectCacheContexts(): void {
    $config = ['target_block' => ['id' => 'x']];
    $target = $this->createMock(BlockBase::class);
    $this->targetBlockFactory->method('getTargetBlock')->willReturn($target);
    $this->cacheManager
      ->expects($this->once())
      ->method('getCacheContexts')
      ->with($target, ['parent'])
      ->willReturn(['parent', 'route']);

    $this->assertEquals(['parent', 'route'], $this->renderer->collectCacheContexts($config, ['parent']));
  }

  /**
   * @covers ::collectCacheTags
   */
  public function testCollectCacheTagsAppendsProxyTag(): void {
    $config = ['target_block' => ['id' => 'x']];
    $target = $this->createMock(BlockBase::class);
    $this->targetBlockFactory->method('getTargetBlock')->willReturn($target);
    $this->cacheManager
      ->expects($this->once())
      ->method('getCacheTags')
      ->with($target, ['parent'])
      ->willReturn(['parent', 'config:y']);

    $result = $this->renderer->collectCacheTags($config, ['parent'], 'proxy_block_proxy');

    $this->assertContains('proxy_block_proxy:proxy_block_proxy', $result);
  }

  /**
   * @covers ::collectCacheMaxAge
   */
  public function testCollectCacheMaxAge(): void {
    $config = ['target_block' => ['id' => 'x']];
    $target = $this->createMock(BlockBase::class);
    $this->targetBlockFactory->method('getTargetBlock')->willReturn($target);
    $this->cacheManager
      ->expects($this->once())
      ->method('getCacheMaxAge')
      ->with($target, 0)
      ->willReturn(60);

    $this->assertEquals(60, $this->renderer->collectCacheMaxAge($config, 0));
  }

  /**
   * @covers ::resolveTargetPluginDefinition
   */
  public function testResolveTargetPluginDefinitionMemoizes(): void {
    $config = ['target_block' => ['id' => 'x']];

    $this->blockManager
      ->expects($this->once())
      ->method('getDefinition')
      ->with('x', FALSE)
      ->willReturn(['admin_label' => 'X']);

    $first = $this->renderer->resolveTargetPluginDefinition($config);
    $second = $this->renderer->resolveTargetPluginDefinition($config);

    $this->assertSame($first, $second);
    $this->assertEquals(['admin_label' => 'X'], $first);
  }

  /**
   * @covers ::resolveTargetPluginDefinition
   */
  public function testResolveTargetPluginDefinitionWithEmptyTargetReturnsNull(): void {
    $this->blockManager
      ->expects($this->never())
      ->method('getDefinition');

    $this->assertNull($this->renderer->resolveTargetPluginDefinition(['target_block' => ['id' => '']]));
  }

  /**
   * @covers ::resolveTargetPluginDefinition
   */
  public function testResolveTargetPluginDefinitionCachesNullLookup(): void {
    $config = ['target_block' => ['id' => 'stale']];

    $this->blockManager
      ->expects($this->once())
      ->method('getDefinition')
      ->with('stale', FALSE)
      ->willReturn(NULL);

    $this->assertNull($this->renderer->resolveTargetPluginDefinition($config));
    $this->assertNull($this->renderer->resolveTargetPluginDefinition($config));
  }

}
