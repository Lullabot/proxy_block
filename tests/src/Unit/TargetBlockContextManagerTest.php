<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\Unit;

use Drupal\Core\Plugin\Context\ContextDefinitionInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests the TargetBlockContextManager service.
 *
 * @group proxy_block
 *
 * @coversDefaultClass \Drupal\proxy_block\Service\TargetBlockContextManager
 */
class TargetBlockContextManagerTest extends ProxyBlockUnitTestBase {

  /**
   * The context repository mock.
   */
  private ContextRepositoryInterface|MockObject $contextRepository;

  /**
   * The context handler mock.
   */
  private ContextHandlerInterface|MockObject $contextHandler;

  /**
   * The target block context manager under test.
   */
  private TestableTargetBlockContextManager $testContextManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->contextRepository = $this->createMock(ContextRepositoryInterface::class);
    $this->contextHandler = $this->createMock(ContextHandlerInterface::class);

    $this->testContextManager = new TestableTargetBlockContextManager(
      $this->contextRepository,
      $this->contextHandler
    );

    $this->testContextManager->setStringTranslation($this->stringTranslation);
  }

  /**
   * Tests getGatheredContexts with available contexts.
   *
   * @covers ::getGatheredContexts
   */
  public function testGetGatheredContextsWithAvailableContexts(): void {
    $available_context_ids = [
      'node' => 'Current node',
      'user' => 'Current user',
      'view_mode' => 'View mode',
    ];

    $runtime_contexts = [
      'node' => $this->createMock(ContextInterface::class),
      'user' => $this->createMock(ContextInterface::class),
      'view_mode' => $this->createMock(ContextInterface::class),
    ];

    $this->contextRepository
      ->expects($this->once())
      ->method('getAvailableContexts')
      ->willReturn($available_context_ids);

    $this->contextRepository
      ->expects($this->once())
      ->method('getRuntimeContexts')
      ->with(['node', 'user', 'view_mode'])
      ->willReturn($runtime_contexts);

    $result = $this->testContextManager->getGatheredContexts();

    $this->assertCount(3, $result);
    $this->assertNotEmpty($result['node']);
    $this->assertNotEmpty($result['user']);
    $this->assertNotEmpty($result['view_mode']);
  }

  /**
   * Tests getGatheredContexts creates default view_mode context when missing.
   *
   * @covers ::getGatheredContexts
   * @covers ::createDefaultViewModeContext
   */
  public function testGetGatheredContextsCreatesDefaultViewModeContext(): void {
    $available_context_ids = [
      'node' => 'Current node',
      'user' => 'Current user',
    ];

    $runtime_contexts = [
      'node' => $this->createMock(ContextInterface::class),
      'user' => $this->createMock(ContextInterface::class),
    ];

    $mock_view_mode_context = $this->createMock(ContextInterface::class);
    $this->testContextManager->setMockViewModeContext($mock_view_mode_context);

    $this->contextRepository
      ->expects($this->once())
      ->method('getAvailableContexts')
      ->willReturn($available_context_ids);

    $this->contextRepository
      ->expects($this->once())
      ->method('getRuntimeContexts')
      ->with(['node', 'user'])
      ->willReturn($runtime_contexts);

    $result = $this->testContextManager->getGatheredContexts();

    $this->assertCount(3, $result);
    $this->assertArrayHasKey('node', $result);
    $this->assertArrayHasKey('user', $result);
    $this->assertArrayHasKey('view_mode', $result);

    // Check that the default view_mode context was created.
    $view_mode_context = $result['view_mode'];
    $this->assertSame($mock_view_mode_context, $view_mode_context);
  }

  /**
   * Tests getGatheredContexts when view_mode context already exists.
   *
   * @covers ::getGatheredContexts
   */
  public function testGetGatheredContextsWithExistingViewModeContext(): void {
    $available_context_ids = [
      'node' => 'Current node',
      'view_mode' => 'View mode',
    ];

    $existing_view_mode_context = $this->createMock(ContextInterface::class);
    $runtime_contexts = [
      'node' => $this->createMock(ContextInterface::class),
      'view_mode' => $existing_view_mode_context,
    ];

    $this->contextRepository
      ->expects($this->once())
      ->method('getAvailableContexts')
      ->willReturn($available_context_ids);

    $this->contextRepository
      ->expects($this->once())
      ->method('getRuntimeContexts')
      ->with(['node', 'view_mode'])
      ->willReturn($runtime_contexts);

    $result = $this->testContextManager->getGatheredContexts();

    $this->assertCount(2, $result);
    $this->assertArrayHasKey('node', $result);
    $this->assertArrayHasKey('view_mode', $result);

    // Verify that the existing view_mode context is preserved.
    $this->assertSame($existing_view_mode_context, $result['view_mode']);
  }

  /**
   * Tests getGatheredContexts with empty contexts.
   *
   * @covers ::getGatheredContexts
   * @covers ::createDefaultViewModeContext
   */
  public function testGetGatheredContextsWithEmptyContexts(): void {
    $available_context_ids = [];
    $runtime_contexts = [];

    $mock_view_mode_context = $this->createMock(ContextInterface::class);
    $this->testContextManager->setMockViewModeContext($mock_view_mode_context);

    $this->contextRepository
      ->expects($this->once())
      ->method('getAvailableContexts')
      ->willReturn($available_context_ids);

    $this->contextRepository
      ->expects($this->once())
      ->method('getRuntimeContexts')
      ->with([])
      ->willReturn($runtime_contexts);

    $result = $this->testContextManager->getGatheredContexts();

    $this->assertCount(1, $result);
    $this->assertArrayHasKey('view_mode', $result);

    // Check that the default view_mode context was created.
    $view_mode_context = $result['view_mode'];
    $this->assertSame($mock_view_mode_context, $view_mode_context);
  }

  /**
   * Tests applyContextsToTargetBlock with block having no context requirements.
   *
   * @covers ::applyContextsToTargetBlock
   */
  public function testApplyContextsToTargetBlockNoContextRequirements(): void {
    $target_block = $this->createMock(ContextAwarePluginInterface::class);
    $target_block->method('getContextDefinitions')
      ->willReturn([]);
    $target_block->method('getContextMapping')
      ->willReturn([]);

    $gathered_contexts = [
      'node' => $this->createMock(ContextInterface::class),
      'user' => $this->createMock(ContextInterface::class),
    ];

    $mock_view_mode_context = $this->createMock(ContextInterface::class);
    $this->testContextManager->setMockViewModeContext($mock_view_mode_context);

    $this->contextRepository
      ->method('getAvailableContexts')
      ->willReturn(['node' => 'Node', 'user' => 'User']);
    $this->contextRepository
      ->method('getRuntimeContexts')
      ->willReturn($gathered_contexts);

    // Should not attempt to apply any contexts since there are no requirements.
    $this->contextHandler
      ->expects($this->never())
      ->method('applyContextMapping');

    $this->testContextManager->applyContextsToTargetBlock($target_block);
  }

  /**
   * Tests applyContextsToTargetBlock with satisfied context mapping.
   *
   * @covers ::applyContextsToTargetBlock
   */
  public function testApplyContextsToTargetBlockWithSatisfiedMapping(): void {
    $context_definition = $this->createMock(ContextDefinitionInterface::class);
    $context_definition->method('isRequired')
      ->willReturn(TRUE);

    $target_block = $this->createMock(ContextAwarePluginInterface::class);
    $target_block->method('getContextDefinitions')
      ->willReturn(['node' => $context_definition]);
    $target_block->method('getContextMapping')
      ->willReturn(['node' => 'current_node']);

    $gathered_contexts = [
      'current_node' => $this->createMock(ContextInterface::class),
      'user' => $this->createMock(ContextInterface::class),
    ];

    $mock_view_mode_context = $this->createMock(ContextInterface::class);
    $this->testContextManager->setMockViewModeContext($mock_view_mode_context);

    $this->contextRepository
      ->method('getAvailableContexts')
      ->willReturn(['current_node' => 'Node', 'user' => 'User']);
    $this->contextRepository
      ->method('getRuntimeContexts')
      ->willReturn($gathered_contexts);

    // The expected contexts will include the view_mode context added by
    // getGatheredContexts().
    $expected_contexts = $gathered_contexts + ['view_mode' => $mock_view_mode_context];

    $this->contextHandler
      ->expects($this->once())
      ->method('applyContextMapping')
      ->with(
        $target_block,
        $expected_contexts,
        ['node' => 'current_node']
      );

    $this->testContextManager->applyContextsToTargetBlock($target_block);
  }

  /**
   * Tests applyContextsToTargetBlock with automatic context mapping generation.
   *
   * @covers ::applyContextsToTargetBlock
   * @covers ::generateAutomaticContextMapping
   */
  public function testApplyContextsToTargetBlockWithAutomaticMapping(): void {
    $context_definition = $this->createMock(ContextDefinitionInterface::class);
    $context_definition->method('isRequired')
      ->willReturn(TRUE);

    $target_block = $this->createMock(ContextAwarePluginInterface::class);
    $target_block->method('getContextDefinitions')
      ->willReturn(['node' => $context_definition]);
    $target_block->method('getContextMapping')
      ->willReturn([]);

    $gathered_contexts = [
      'current_node' => $this->createMock(ContextInterface::class),
      'user' => $this->createMock(ContextInterface::class),
    ];

    $mock_view_mode_context = $this->createMock(ContextInterface::class);
    $this->testContextManager->setMockViewModeContext($mock_view_mode_context);

    $this->contextRepository
      ->method('getAvailableContexts')
      ->willReturn(['current_node' => 'Node', 'user' => 'User']);
    $this->contextRepository
      ->method('getRuntimeContexts')
      ->willReturn($gathered_contexts);

    // The expected contexts will include the view_mode context added by
    // getGatheredContexts().
    $expected_contexts = $gathered_contexts + ['view_mode' => $mock_view_mode_context];

    $this->contextHandler
      ->expects($this->once())
      ->method('getMatchingContexts')
      ->with($expected_contexts, $context_definition)
      ->willReturn(['current_node' => $this->createMock(ContextInterface::class)]);

    $target_block
      ->expects($this->once())
      ->method('setContextMapping')
      ->with(['node' => 'current_node']);

    $this->contextHandler
      ->expects($this->once())
      ->method('applyContextMapping')
      ->with(
        $target_block,
        $expected_contexts,
        ['node' => 'current_node']
      );

    $this->testContextManager->applyContextsToTargetBlock($target_block);
  }

  /**
   * Tests applyContextsToTargetBlock with context resolution using @ prefix.
   *
   * @covers ::applyContextsToTargetBlock
   */
  public function testApplyContextsToTargetBlockWithAtPrefixResolution(): void {
    $context_definition = $this->createMock(ContextDefinitionInterface::class);
    $context_definition->method('isRequired')
      ->willReturn(TRUE);

    $target_block = $this->createMock(ContextAwarePluginInterface::class);
    $target_block->method('getContextDefinitions')
      ->willReturn(['node' => $context_definition]);
    $target_block->method('getContextMapping')
      ->willReturn(['node' => '@current_node']);

    $gathered_contexts = [
      'current_node' => $this->createMock(ContextInterface::class),
      'user' => $this->createMock(ContextInterface::class),
    ];

    $mock_view_mode_context = $this->createMock(ContextInterface::class);
    $this->testContextManager->setMockViewModeContext($mock_view_mode_context);

    $this->contextRepository
      ->method('getAvailableContexts')
      ->willReturn(['current_node' => 'Node', 'user' => 'User']);
    $this->contextRepository
      ->method('getRuntimeContexts')
      ->willReturn($gathered_contexts);

    // The expected contexts will include the view_mode context added by
    // getGatheredContexts().
    $expected_contexts = $gathered_contexts + ['view_mode' => $mock_view_mode_context];

    $this->contextHandler
      ->expects($this->once())
      ->method('applyContextMapping')
      ->with(
        $target_block,
        $expected_contexts,
        ['node' => 'current_node']
      );

    $this->testContextManager->applyContextsToTargetBlock($target_block);
  }

  /**
   * Tests applyContextsToTargetBlock with context resolution fallback.
   *
   * @covers ::applyContextsToTargetBlock
   */
  public function testApplyContextsToTargetBlockWithFallbackResolution(): void {
    $context_definition = $this->createMock(ContextDefinitionInterface::class);
    $context_definition->method('isRequired')
      ->willReturn(TRUE);

    $target_block = $this->createMock(ContextAwarePluginInterface::class);
    $target_block->method('getContextDefinitions')
      ->willReturn(['node' => $context_definition]);
    $target_block->method('getContextMapping')
      ->willReturn(['node' => '@missing_context']);

    $gathered_contexts = [
      '@missing_context' => $this->createMock(ContextInterface::class),
    ];

    $mock_view_mode_context = $this->createMock(ContextInterface::class);
    $this->testContextManager->setMockViewModeContext($mock_view_mode_context);

    $this->contextRepository
      ->method('getAvailableContexts')
      ->willReturn(['@missing_context' => 'Missing Context']);
    $this->contextRepository
      ->method('getRuntimeContexts')
      ->willReturn($gathered_contexts);

    // The expected contexts will include the view_mode context added by
    // getGatheredContexts().
    $expected_contexts = $gathered_contexts + ['view_mode' => $mock_view_mode_context];

    $this->contextHandler
      ->expects($this->once())
      ->method('applyContextMapping')
      ->with(
        $target_block,
        $expected_contexts,
        ['node' => '@missing_context']
      );

    $this->testContextManager->applyContextsToTargetBlock($target_block);
  }

  /**
   * Tests applyContextsToTargetBlock with exception during context gathering.
   *
   * @covers ::applyContextsToTargetBlock
   */
  public function testApplyContextsToTargetBlockExceptionDuringGathering(): void {
    $target_block = $this->createMock(ContextAwarePluginInterface::class);

    $mock_view_mode_context = $this->createMock(ContextInterface::class);
    $this->testContextManager->setMockViewModeContext($mock_view_mode_context);

    $this->contextRepository
      ->method('getAvailableContexts')
      ->willThrowException(new \Exception('Context repository error'));

    // Should catch the exception and continue gracefully.
    $this->contextHandler
      ->expects($this->never())
      ->method('applyContextMapping');

    $this->testContextManager->applyContextsToTargetBlock($target_block);
  }

  /**
   * Tests applyContextsToTargetBlock with exception during context application.
   *
   * @covers ::applyContextsToTargetBlock
   */
  public function testApplyContextsToTargetBlockExceptionDuringApplication(): void {
    $context_definition = $this->createMock(ContextDefinitionInterface::class);
    $context_definition->method('isRequired')
      ->willReturn(TRUE);

    $target_block = $this->createMock(ContextAwarePluginInterface::class);
    $target_block->method('getContextDefinitions')
      ->willReturn(['node' => $context_definition]);
    $target_block->method('getContextMapping')
      ->willReturn(['node' => 'current_node']);

    $gathered_contexts = [
      'current_node' => $this->createMock(ContextInterface::class),
    ];

    $mock_view_mode_context = $this->createMock(ContextInterface::class);
    $this->testContextManager->setMockViewModeContext($mock_view_mode_context);

    $this->contextRepository
      ->method('getAvailableContexts')
      ->willReturn(['current_node' => 'Node']);
    $this->contextRepository
      ->method('getRuntimeContexts')
      ->willReturn($gathered_contexts);

    $this->contextHandler
      ->expects($this->once())
      ->method('applyContextMapping')
      ->willThrowException(new \Exception('Context application error'));

    // Should catch the exception and continue gracefully.
    $this->testContextManager->applyContextsToTargetBlock($target_block);
  }

  /**
   * Tests generateAutomaticContextMapping with matching contexts.
   *
   * @covers ::generateAutomaticContextMapping
   */
  public function testGenerateAutomaticContextMappingWithMatchingContexts(): void {
    $node_context_definition = $this->createMock(ContextDefinitionInterface::class);
    $user_context_definition = $this->createMock(ContextDefinitionInterface::class);

    $target_block = $this->createMock(ContextAwarePluginInterface::class);
    $target_block->method('getContextDefinitions')
      ->willReturn([
        'node' => $node_context_definition,
        'user' => $user_context_definition,
      ]);

    $available_contexts = [
      'current_node' => $this->createMock(ContextInterface::class),
      'current_user' => $this->createMock(ContextInterface::class),
      'other_context' => $this->createMock(ContextInterface::class),
    ];

    $this->contextHandler
      ->expects($this->exactly(2))
      ->method('getMatchingContexts')
      ->willReturnCallback(function ($contexts, $definition) use ($node_context_definition, $user_context_definition, $available_contexts) {
        if ($definition === $node_context_definition) {
          return ['current_node' => $available_contexts['current_node']];
        }
        if ($definition === $user_context_definition) {
          return ['current_user' => $available_contexts['current_user']];
        }
        return [];
      });

    $reflection = new \ReflectionClass($this->testContextManager);
    $method = $reflection->getMethod('generateAutomaticContextMapping');
    $method->setAccessible(TRUE);

    $result = $method->invoke($this->testContextManager, $target_block, $available_contexts);

    $this->assertEquals([
      'node' => 'current_node',
      'user' => 'current_user',
    ], $result);
  }

  /**
   * Tests generateAutomaticContextMapping with no matching contexts.
   *
   * @covers ::generateAutomaticContextMapping
   */
  public function testGenerateAutomaticContextMappingWithNoMatchingContexts(): void {
    $context_definition = $this->createMock(ContextDefinitionInterface::class);

    $target_block = $this->createMock(ContextAwarePluginInterface::class);
    $target_block->method('getContextDefinitions')
      ->willReturn(['node' => $context_definition]);

    $available_contexts = [
      'user' => $this->createMock(ContextInterface::class),
    ];

    $this->contextHandler
      ->expects($this->once())
      ->method('getMatchingContexts')
      ->with($available_contexts, $context_definition)
      ->willReturn([]);

    $reflection = new \ReflectionClass($this->testContextManager);
    $method = $reflection->getMethod('generateAutomaticContextMapping');
    $method->setAccessible(TRUE);

    $result = $method->invoke($this->testContextManager, $target_block, $available_contexts);

    $this->assertEmpty($result);
  }

  /**
   * Tests generateAutomaticContextMapping with multiple matching contexts.
   *
   * @covers ::generateAutomaticContextMapping
   */
  public function testGenerateAutomaticContextMappingWithMultipleMatches(): void {
    $context_definition = $this->createMock(ContextDefinitionInterface::class);

    $target_block = $this->createMock(ContextAwarePluginInterface::class);
    $target_block->method('getContextDefinitions')
      ->willReturn(['node' => $context_definition]);

    $available_contexts = [
      'node1' => $this->createMock(ContextInterface::class),
      'node2' => $this->createMock(ContextInterface::class),
      'node3' => $this->createMock(ContextInterface::class),
    ];

    // Return multiple matching contexts - should use the first one.
    $this->contextHandler
      ->expects($this->once())
      ->method('getMatchingContexts')
      ->with($available_contexts, $context_definition)
      ->willReturn([
        'node1' => $available_contexts['node1'],
        'node2' => $available_contexts['node2'],
        'node3' => $available_contexts['node3'],
      ]);

    $reflection = new \ReflectionClass($this->testContextManager);
    $method = $reflection->getMethod('generateAutomaticContextMapping');
    $method->setAccessible(TRUE);

    $result = $method->invoke($this->testContextManager, $target_block, $available_contexts);

    $this->assertEquals(['node' => 'node1'], $result);
  }

  /**
   * Tests generateAutomaticContextMapping with empty available contexts.
   *
   * @covers ::generateAutomaticContextMapping
   */
  public function testGenerateAutomaticContextMappingWithEmptyAvailableContexts(): void {
    $context_definition = $this->createMock(ContextDefinitionInterface::class);

    $target_block = $this->createMock(ContextAwarePluginInterface::class);
    $target_block->method('getContextDefinitions')
      ->willReturn(['node' => $context_definition]);

    $available_contexts = [];

    $this->contextHandler
      ->expects($this->once())
      ->method('getMatchingContexts')
      ->with($available_contexts, $context_definition)
      ->willReturn([]);

    $reflection = new \ReflectionClass($this->testContextManager);
    $method = $reflection->getMethod('generateAutomaticContextMapping');
    $method->setAccessible(TRUE);

    $result = $method->invoke($this->testContextManager, $target_block, $available_contexts);

    $this->assertEmpty($result);
  }

}
