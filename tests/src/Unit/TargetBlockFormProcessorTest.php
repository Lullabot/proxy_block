<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\Unit;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Plugin\Context\ContextDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\proxy_block\Service\TargetBlockContextManager;
use Drupal\proxy_block\Service\TargetBlockFormProcessor;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Tests the TargetBlockFormProcessor service.
 *
 * @group proxy_block
 *
 * @coversDefaultClass \Drupal\proxy_block\Service\TargetBlockFormProcessor
 */
class TargetBlockFormProcessorTest extends UnitTestCase {

  /**
   * The block manager mock.
   */
  private BlockManagerInterface|MockObject $blockManager;

  /**
   * The context manager mock.
   */
  private TargetBlockContextManager|MockObject $contextManager;

  /**
   * The target block form processor under test.
   */
  private TargetBlockFormProcessor $processor;

  /**
   * The string translation mock.
   */
  private TranslationInterface|MockObject $stringTranslation;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->blockManager = $this->createMock(BlockManagerInterface::class);
    $this->contextManager = $this->createMock(TargetBlockContextManager::class);

    $this->stringTranslation = $this->createMock(TranslationInterface::class);
    $this->stringTranslation->method('translate')
      ->willReturnCallback(function ($string) {
        return new TranslatableMarkup('@string', ['@string' => $string], [], $this->stringTranslation);
      });

    // Create a partial mock to avoid container dependencies in
    // addContextAssignmentElement.
    $this->processor = $this->getMockBuilder(TargetBlockFormProcessor::class)
      ->setConstructorArgs([$this->blockManager, $this->contextManager])
      ->onlyMethods(['addContextAssignmentElement'])
      ->getMock();

    $this->processor->method('addContextAssignmentElement')
      ->willReturn([
        '#type' => 'details',
        '#title' => 'Context Mapping',
        '#open' => TRUE,
        'mock_context_element' => [
          '#type' => 'select',
          '#title' => 'Mock Context',
          '#options' => ['context1' => 'Context 1'],
        ],
      ]);

    $this->processor->setStringTranslation($this->stringTranslation);
  }

  /**
   * Tests buildTargetBlockConfigurationForm with a simple block.
   *
   * The block has no context and no config.
   *
   * @covers ::buildTargetBlockConfigurationForm
   */
  public function testBuildTargetBlockConfigurationFormSimpleBlock(): void {
    $plugin_id = 'test_block';
    $configuration = [];

    // Mock a block plugin that implements BlockPluginInterface (which extends
    // PluginFormInterface) but returns empty configuration form to simulate a
    // "simple" block.
    $target_block = $this->createMock(BlockPluginInterface::class);
    $target_block->method('buildConfigurationForm')
      ->willReturn([]);

    $this->blockManager
      ->expects($this->once())
      ->method('createInstance')
      ->with($plugin_id, [])
      ->willReturn($target_block);

    $result = $this->processor->buildTargetBlockConfigurationForm($plugin_id, $configuration);

    $this->assertIsArray($result);
    $this->assertArrayHasKey('no_config', $result);
    $this->assertEquals('details', $result['no_config']['#type']);
    $this->assertInstanceOf(TranslatableMarkup::class, $result['no_config']['#title']);
    $this->assertTrue($result['no_config']['#open']);
    $this->assertArrayHasKey('message', $result['no_config']);
    $this->assertInstanceOf(TranslatableMarkup::class, $result['no_config']['message']['#markup']);
  }

  /**
   * Tests buildTargetBlockConfigurationForm with a configurable block.
   *
   * @covers ::buildTargetBlockConfigurationForm
   */
  public function testBuildTargetBlockConfigurationFormConfigurableBlock(): void {
    $plugin_id = 'configurable_block';
    $configuration = [
      'target_block' => [
        'config' => ['existing_config' => 'value'],
      ],
    ];

    $config_form = [
      'setting1' => [
        '#type' => 'textfield',
        '#title' => 'Setting 1',
      ],
      'setting2' => [
        '#type' => 'select',
        '#title' => 'Setting 2',
        '#options' => ['option1' => 'Option 1'],
      ],
    ];

    // Create a test stub that implements the required interfaces.
    $target_block = new TestBlockStub($plugin_id, [], $config_form);

    $this->blockManager
      ->expects($this->once())
      ->method('createInstance')
      ->with($plugin_id, ['existing_config' => 'value'])
      ->willReturn($target_block);

    $result = $this->processor->buildTargetBlockConfigurationForm($plugin_id, $configuration);

    $this->assertIsArray($result);
    $this->assertArrayHasKey('block_config', $result);
    $this->assertEquals('details', $result['block_config']['#type']);
    $this->assertInstanceOf(TranslatableMarkup::class, $result['block_config']['#title']);
    $this->assertTrue($result['block_config']['#open']);

    // Check that the config form elements are merged.
    $this->assertArrayHasKey('setting1', $result['block_config']);
    $this->assertArrayHasKey('setting2', $result['block_config']);
    $this->assertEquals('textfield', $result['block_config']['setting1']['#type']);
    $this->assertEquals('select', $result['block_config']['setting2']['#type']);
  }

  /**
   * Tests buildTargetBlockConfigurationForm with a context-aware block.
   *
   * @covers ::buildTargetBlockConfigurationForm
   */
  public function testBuildTargetBlockConfigurationFormContextAwareBlock(): void {
    $plugin_id = 'context_aware_block';
    $configuration = [];

    $context_definitions = [
      'node' => $this->createMock(ContextDefinitionInterface::class),
      'user' => $this->createMock(ContextDefinitionInterface::class),
    ];

    // Create a test stub that implements the required interfaces.
    $target_block = new TestBlockStub($plugin_id, $context_definitions);

    $gathered_contexts = [
      'available_context_1' => 'Context 1',
      'available_context_2' => 'Context 2',
    ];

    $this->contextManager
      ->expects($this->once())
      ->method('getGatheredContexts')
      ->willReturn($gathered_contexts);

    $this->blockManager
      ->expects($this->once())
      ->method('createInstance')
      ->with($plugin_id, [])
      ->willReturn($target_block);

    $result = $this->processor->buildTargetBlockConfigurationForm($plugin_id, $configuration);

    $this->assertIsArray($result);
    $this->assertArrayHasKey('no_config', $result);
    $this->assertArrayHasKey('context_mapping', $result);
  }

  /**
   * Test buildTargetBlockConfigurationForm with config and context-aware block.
   *
   * @covers ::buildTargetBlockConfigurationForm
   */
  public function testBuildTargetBlockConfigurationFormConfigurableContextAwareBlock(): void {
    $plugin_id = 'full_featured_block';
    $configuration = [];

    $context_definitions = [
      'required_context' => $this->createMock(ContextDefinitionInterface::class),
    ];

    $config_form = [
      'advanced_setting' => [
        '#type' => 'textarea',
        '#title' => 'Advanced Setting',
      ],
    ];

    // Create a test stub that implements all interfaces.
    $target_block = new TestBlockStub($plugin_id, $context_definitions, $config_form);

    $gathered_contexts = [
      'available_context' => 'Available Context',
    ];

    $this->contextManager
      ->expects($this->once())
      ->method('getGatheredContexts')
      ->willReturn($gathered_contexts);

    $this->blockManager
      ->expects($this->once())
      ->method('createInstance')
      ->with($plugin_id, [])
      ->willReturn($target_block);

    $result = $this->processor->buildTargetBlockConfigurationForm($plugin_id, $configuration);

    $this->assertIsArray($result);
    $this->assertArrayHasKey('block_config', $result);
    $this->assertArrayHasKey('context_mapping', $result);
    $this->assertArrayHasKey('advanced_setting', $result['block_config']);
    $this->assertEquals('textarea', $result['block_config']['advanced_setting']['#type']);
  }

  /**
   * Tests buildTargetBlockConfigurationForm with invalid plugin.
   *
   * @covers ::buildTargetBlockConfigurationForm
   */
  public function testBuildTargetBlockConfigurationFormInvalidPlugin(): void {
    $plugin_id = 'invalid_block';
    $configuration = [];

    $this->blockManager
      ->expects($this->once())
      ->method('createInstance')
      ->with($plugin_id, [])
      ->willThrowException(new PluginException('Plugin not found'));

    $result = $this->processor->buildTargetBlockConfigurationForm($plugin_id, $configuration);

    $this->assertIsArray($result);
    $this->assertArrayHasKey('error', $result);
    $this->assertEquals('details', $result['error']['#type']);
    $this->assertInstanceOf(TranslatableMarkup::class, $result['error']['#title']);
    $this->assertTrue($result['error']['#open']);
    $this->assertArrayHasKey('message', $result['error']);
    $this->assertInstanceOf(TranslatableMarkup::class, $result['error']['message']['#markup']);
  }

  /**
   * Tests validateTargetBlock with valid plugin.
   *
   * @covers ::validateTargetBlock
   */
  public function testValidateTargetBlockValid(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $configuration = [];

    $form_state
      ->expects($this->exactly(2))
      ->method('getValue')
      ->willReturnCallback(function ($key) {
        if ($key === ['target_block', 'id']) {
          return 'valid_block';
        }
        if ($key === ['target_block', 'config']) {
          return ['some_config' => 'value'];
        }
        $this->fail('Unexpected getValue call with key: ' . print_r($key, TRUE));
      });

    $target_block = $this->createMock(BlockPluginInterface::class);

    $this->blockManager
      ->expects($this->once())
      ->method('createInstance')
      ->with('valid_block', ['some_config' => 'value'])
      ->willReturn($target_block);

    $form_state
      ->expects($this->never())
      ->method('setErrorByName');

    $this->processor->validateTargetBlock($form_state, $configuration);

    // Test passes if no exception is thrown and no error is set.
  }

  /**
   * Tests validateTargetBlock with invalid plugin.
   *
   * @covers ::validateTargetBlock
   */
  public function testValidateTargetBlockInvalid(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $configuration = [];

    $form_state
      ->expects($this->exactly(2))
      ->method('getValue')
      ->willReturnCallback(function ($key) {
        if ($key === ['target_block', 'id']) {
          return 'invalid_block';
        }
        if ($key === ['target_block', 'config']) {
          return [];
        }
        $this->fail('Unexpected getValue call with key: ' . print_r($key, TRUE));
      });

    $this->blockManager
      ->expects($this->once())
      ->method('createInstance')
      ->with('invalid_block', [])
      ->willThrowException(new PluginException('Plugin not found'));

    $form_state
      ->expects($this->once())
      ->method('setErrorByName')
      ->with('target_block][id', $this->isInstanceOf(TranslatableMarkup::class));

    $this->processor->validateTargetBlock($form_state, $configuration);

    // Test passes if error is set via setErrorByName.
  }

  /**
   * Tests validateTargetBlock with empty plugin ID.
   *
   * @covers ::validateTargetBlock
   */
  public function testValidateTargetBlockEmpty(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $configuration = [];

    $form_state
      ->expects($this->once())
      ->method('getValue')
      ->with(['target_block', 'id'])
      ->willReturn('');

    $this->blockManager
      ->expects($this->never())
      ->method('createInstance');

    $form_state
      ->expects($this->never())
      ->method('setErrorByName');

    $this->processor->validateTargetBlock($form_state, $configuration);
  }

  /**
   * Tests validateTargetBlock with configuration fallback.
   *
   * @covers ::validateTargetBlock
   */
  public function testValidateTargetBlockConfigurationFallback(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $configuration = [
      'target_block' => [
        'config' => ['fallback_config' => 'value'],
      ],
    ];

    $form_state
      ->expects($this->exactly(2))
      ->method('getValue')
      ->willReturnCallback(function ($key) {
        if ($key === ['target_block', 'id']) {
          return 'test_block';
        }
        if ($key === ['target_block', 'config']) {
          return NULL;
        }
        $this->fail('Unexpected getValue call with key: ' . print_r($key, TRUE));
      });

    $target_block = $this->createMock(BlockPluginInterface::class);

    $this->blockManager
      ->expects($this->once())
      ->method('createInstance')
      ->with('test_block', ['fallback_config' => 'value'])
      ->willReturn($target_block);

    $form_state
      ->expects($this->never())
      ->method('setErrorByName');

    $this->processor->validateTargetBlock($form_state, $configuration);

    // Test passes if no exception is thrown and fallback config is used.
  }

  /**
   * Tests submitTargetBlock with simple block.
   *
   * @covers ::submitTargetBlock
   */
  public function testSubmitTargetBlockSimple(): void {
    $form_state = $this->createMock(FormStateInterface::class);

    $form_state
      ->expects($this->exactly(2))
      ->method('getValue')
      ->willReturnCallback(function ($key) {
        if ($key === ['target_block', 'id']) {
          return 'simple_block';
        }
        if ($key === ['target_block', 'config']) {
          return ['block_setting' => 'value'];
        }
        $this->fail('Unexpected getValue call with key: ' . print_r($key, TRUE));
      });

    $target_block = $this->createMock(BlockPluginInterface::class);
    $target_block
      ->expects($this->exactly(2))
      ->method('getConfiguration')
      ->willReturn(['block_setting' => 'value', 'default_setting' => 'default']);

    $target_block
      ->expects($this->once())
      ->method('setConfiguration')
      ->with(['block_setting' => 'value', 'default_setting' => 'default']);

    $this->blockManager
      ->expects($this->once())
      ->method('createInstance')
      ->with('simple_block', ['block_setting' => 'value'])
      ->willReturn($target_block);

    $result = $this->processor->submitTargetBlock($form_state);

    $this->assertIsArray($result);
    $this->assertEquals('simple_block', $result['target_block']['id']);
    $this->assertEquals([
      'block_setting' => 'value',
      'default_setting' => 'default',
    ], $result['target_block']['config']);
  }

  /**
   * Tests submitTargetBlock with configurable block.
   *
   * @covers ::submitTargetBlock
   */
  public function testSubmitTargetBlockConfigurable(): void {
    $form_state = $this->createMock(FormStateInterface::class);

    $form_state
      ->expects($this->exactly(3))
      ->method('getValue')
      ->willReturnCallback(function ($key) {
        if ($key === ['target_block', 'id']) {
          return 'configurable_block';
        }
        if ($key === ['target_block', 'config']) {
          return ['user_setting' => 'user_value'];
        }
        // @phpstan-ignore-next-line
        if ($key === ['target_block', 'config', 'context_mapping']) {
          return [];
        }
        $this->fail('Unexpected getValue call with key: ' . print_r($key, TRUE));
      });

    // Create a test stub that implements PluginFormInterface.
    $target_block = new TestBlockStub('configurable_block');
    $target_block->setConfiguration(['default_setting' => 'default']);
    $target_block->setConfiguration(['user_setting' => 'user_value']);

    $this->blockManager
      ->expects($this->once())
      ->method('createInstance')
      ->with('configurable_block', ['user_setting' => 'user_value'])
      ->willReturn($target_block);

    $result = $this->processor->submitTargetBlock($form_state);

    $this->assertIsArray($result);
    $this->assertEquals('configurable_block', $result['target_block']['id']);
    $this->assertEquals([
      'user_setting' => 'user_value',
      'default_setting' => 'default',
    ], $result['target_block']['config']);
  }

  /**
   * Tests submitTargetBlock with context-aware block.
   *
   * @covers ::submitTargetBlock
   */
  public function testSubmitTargetBlockContextAware(): void {
    $form_state = $this->createMock(FormStateInterface::class);

    $form_state
      ->expects($this->exactly(3))
      ->method('getValue')
      ->willReturnCallback(function ($key) {
        if ($key === ['target_block', 'id']) {
          return 'context_aware_block';
        }
        if ($key === ['target_block', 'config']) {
          return ['setting' => 'value'];
        }
        // @phpstan-ignore-next-line
        if ($key === ['target_block', 'config', 'context_mapping']) {
          return ['node' => 'current_node', 'user' => 'current_user'];
        }
        $this->fail('Unexpected getValue call with key: ' . print_r($key, TRUE));
      });

    // Create a test stub that implements ContextAwarePluginInterface.
    $target_block = new TestBlockStub('context_aware_block');
    $target_block->setConfiguration(['setting' => 'value']);
    $target_block->setContextMapping(['node' => 'current_node', 'user' => 'current_user']);

    $this->blockManager
      ->expects($this->once())
      ->method('createInstance')
      ->with('context_aware_block', ['setting' => 'value'])
      ->willReturn($target_block);

    $result = $this->processor->submitTargetBlock($form_state);

    $this->assertIsArray($result);
    $this->assertEquals('context_aware_block', $result['target_block']['id']);
    $this->assertEquals([
      'setting' => 'value',
      'context_mapping' => ['node' => 'current_node', 'user' => 'current_user'],
    ], $result['target_block']['config']);
  }

  /**
   * Tests submitTargetBlock with empty plugin ID.
   *
   * @covers ::submitTargetBlock
   */
  public function testSubmitTargetBlockEmpty(): void {
    $form_state = $this->createMock(FormStateInterface::class);

    $form_state
      ->expects($this->once())
      ->method('getValue')
      ->with(['target_block', 'id'])
      ->willReturn('');

    $this->blockManager
      ->expects($this->never())
      ->method('createInstance');

    $result = $this->processor->submitTargetBlock($form_state);

    $this->assertIsArray($result);
    $this->assertEquals('', $result['target_block']['id']);
    $this->assertEquals([], $result['target_block']['config']);
  }

  /**
   * Tests submitTargetBlock with plugin exception.
   *
   * @covers ::submitTargetBlock
   */
  public function testSubmitTargetBlockPluginException(): void {
    $form_state = $this->createMock(FormStateInterface::class);

    $form_state
      ->expects($this->exactly(2))
      ->method('getValue')
      ->willReturnCallback(function ($key) {
        if ($key === ['target_block', 'id']) {
          return 'invalid_block';
        }
        if ($key === ['target_block', 'config']) {
          return ['setting' => 'value'];
        }
        $this->fail('Unexpected getValue call with key: ' . print_r($key, TRUE));
      });

    $this->blockManager
      ->expects($this->once())
      ->method('createInstance')
      ->with('invalid_block', ['setting' => 'value'])
      ->willThrowException(new PluginException('Plugin not found'));

    $result = $this->processor->submitTargetBlock($form_state);

    $this->assertIsArray($result);
    $this->assertEquals('invalid_block', $result['target_block']['id']);
    $this->assertEquals([], $result['target_block']['config']);
  }

  /**
   * Tests getSelectedTargetFromFormState with valid triggering element.
   *
   * @covers ::getSelectedTargetFromFormState
   */
  public function testGetSelectedTargetFromFormStateValid(): void {
    $form_state = $this->createMock(FormStateInterface::class);

    $triggering_element = [
      '#parents' => ['settings', 'target_block', 'id'],
    ];

    $user_input = [
      'settings' => [
        'target_block' => [
          'id' => 'selected_block',
          'config' => ['setting' => 'value'],
        ],
      ],
    ];

    $form_state
      ->expects($this->once())
      ->method('getTriggeringElement')
      ->willReturn($triggering_element);

    $form_state
      ->expects($this->once())
      ->method('getUserInput')
      ->willReturn($user_input);

    $result = $this->processor->getSelectedTargetFromFormState($form_state);

    $this->assertIsArray($result);
    $this->assertEquals('selected_block', $result['id']);
    $this->assertEquals(['setting' => 'value'], $result['config']);
  }

  /**
   * Tests getSelectedTargetFromFormState with no triggering element.
   *
   * @covers ::getSelectedTargetFromFormState
   */
  public function testGetSelectedTargetFromFormStateNoTriggeringElement(): void {
    $form_state = $this->createMock(FormStateInterface::class);

    $form_state
      ->expects($this->once())
      ->method('getTriggeringElement')
      ->willReturn(NULL);

    $form_state
      ->expects($this->never())
      ->method('getUserInput');

    $result = $this->processor->getSelectedTargetFromFormState($form_state);

    $this->assertNull($result);
  }

  /**
   * Tests getSelectedTargetFromFormState with invalid parents.
   *
   * @covers ::getSelectedTargetFromFormState
   */
  public function testGetSelectedTargetFromFormStateInvalidParents(): void {
    $form_state = $this->createMock(FormStateInterface::class);

    // Test with insufficient parents.
    $triggering_element = [
      '#parents' => ['target_block'],
    ];

    $form_state
      ->expects($this->once())
      ->method('getTriggeringElement')
      ->willReturn($triggering_element);

    $form_state
      ->expects($this->never())
      ->method('getUserInput');

    $result = $this->processor->getSelectedTargetFromFormState($form_state);

    $this->assertNull($result);
  }

  /**
   * Tests getSelectedTargetFromFormState with wrong parent structure.
   *
   * @covers ::getSelectedTargetFromFormState
   */
  public function testGetSelectedTargetFromFormStateWrongParents(): void {
    $form_state = $this->createMock(FormStateInterface::class);

    // Test with wrong parent structure.
    $triggering_element = [
      '#parents' => ['settings', 'other_field', 'value'],
    ];

    $form_state
      ->expects($this->once())
      ->method('getTriggeringElement')
      ->willReturn($triggering_element);

    $form_state
      ->expects($this->never())
      ->method('getUserInput');

    $result = $this->processor->getSelectedTargetFromFormState($form_state);

    $this->assertNull($result);
  }

  /**
   * Tests getSelectedTargetFromFormState with missing parents key.
   *
   * @covers ::getSelectedTargetFromFormState
   */
  public function testGetSelectedTargetFromFormStateMissingParents(): void {
    $form_state = $this->createMock(FormStateInterface::class);

    $triggering_element = [
      '#type' => 'select',
    ];

    $form_state
      ->expects($this->once())
      ->method('getTriggeringElement')
      ->willReturn($triggering_element);

    $form_state
      ->expects($this->never())
      ->method('getUserInput');

    $result = $this->processor->getSelectedTargetFromFormState($form_state);

    $this->assertNull($result);
  }

}
