<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Plugin\Context\ContextInterface as ComponentContextInterface;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * A test stub implementing BlockPluginInterface with configurable capabilities.
 *
 * This class provides a lightweight implementation of block plugin interfaces
 * for unit testing purposes. It's used internally by ProxyBlockUnitTestBase
 * helper methods.
 *
 * @internal This is a testing utility class for proxy_block unit tests.
 */
class TestBlockStub implements BlockPluginInterface, PluginFormInterface, ContextAwarePluginInterface {

  /**
   * The plugin ID.
   */
  private string $pluginId;

  /**
   * The plugin configuration.
   */
  private array $configuration = [];

  /**
   * The context definitions.
   */
  private array $contextDefinitions = [];

  /**
   * The context mapping.
   */
  private array $contextMapping = [];

  /**
   * The configuration form.
   */
  private array $configForm = [];

  public function __construct(string $plugin_id, array $context_definitions = [], array $config_form = []) {
    $this->pluginId = $plugin_id;
    $this->contextDefinitions = $context_definitions;
    $this->configForm = $config_form;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId(): string {
    return $this->pluginId;
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseId(): string {
    return $this->pluginId;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeId(): ?string {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return 'Test Block';
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    $config = $this->configuration;
    if (!empty($this->contextMapping)) {
      $config['context_mapping'] = $this->contextMapping;
    }
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = array_merge($this->configuration, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account, $return_as_object = FALSE) {
    return $return_as_object ? AccessResult::allowed() : TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    return $this->configForm ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * {@inheritdoc}
   */
  public function getContextDefinitions(): array {
    return $this->contextDefinitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getContexts(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getContext($name): ContextInterface {
    throw new \Exception('Not implemented');
  }

  /**
   * {@inheritdoc}
   */
  public function setContextValue($name, $value): static {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasContextValue($name): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getContextValue($name) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getContextMapping(): array {
    return $this->contextMapping;
  }

  /**
   * {@inheritdoc}
   */
  public function setContextMapping(array $context_mapping): static {
    $this->contextMapping = $context_mapping;
    return $this;
  }

  /**
   * Required cache methods.
   */
  public function getCacheContexts(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return 0;
  }

  /**
   * Additional required methods from BlockPluginInterface.
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state): void {
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
  }

  /**
   * {@inheritdoc}
   */
  public function getMachineNameSuggestion(): string {
    return $this->pluginId;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfigurationValue($key, $value): void {
    $this->configuration[$key] = $value;
  }

  /**
   * Additional methods from ContextAwarePluginInterface.
   */
  public function getContextDefinition($name) {
    return $this->contextDefinitions[$name] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getContextValues(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function setContext($name, ComponentContextInterface $context): void {
  }

  /**
   * {@inheritdoc}
   */
  public function validateContexts(): ConstraintViolationListInterface {
    return new ConstraintViolationList();
  }

}
