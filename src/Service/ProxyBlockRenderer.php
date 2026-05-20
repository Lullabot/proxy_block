<?php

declare(strict_types=1);

namespace Drupal\proxy_block\Service;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Renders proxy blocks and answers their cache-metadata queries.
 *
 * Extracted out of \Drupal\proxy_block\Plugin\Block\ProxyBlock so the plugin
 * itself can stay thin. All render-time and cache-metadata orchestration
 * lives here; ProxyBlock delegates and threads its parent's contributions
 * (parent cache contexts/tags/max-age) through where needed.
 */
class ProxyBlockRenderer {

  use StringTranslationTrait;

  /**
   * Memoized target plugin definitions, keyed by target block id.
   *
   * FALSE is stored when the lookup returned NULL, to distinguish "not
   * found" from "not yet looked up". Keys are target plugin ids.
   */
  protected array $targetDefinitionCache = [];

  /**
   * Constructs a new ProxyBlockRenderer.
   *
   * @param \Drupal\proxy_block\Service\TargetBlockFactory $targetBlockFactory
   *   The target block factory.
   * @param \Drupal\proxy_block\Service\TargetBlockContextManager $contextManager
   *   The target block context manager.
   * @param \Drupal\proxy_block\Service\TargetBlockCacheManager $cacheManager
   *   The target block cache manager.
   * @param \Drupal\Core\Block\BlockManagerInterface $blockManager
   *   The block plugin manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   */
  public function __construct(
    protected TargetBlockFactory $targetBlockFactory,
    protected TargetBlockContextManager $contextManager,
    protected TargetBlockCacheManager $cacheManager,
    protected BlockManagerInterface $blockManager,
    protected AccountProxyInterface $currentUser,
    protected RequestStack $requestStack,
    #[Autowire(service: 'logger.channel.proxy_block')]
    protected LoggerInterface $logger,
  ) {}

  /**
   * Resolves and memoizes the target block's plugin definition.
   *
   * @param array $configuration
   *   The proxy block configuration.
   *
   * @return array|null
   *   The plugin definition array, or NULL when no target is configured or
   *   the target plugin can no longer be resolved.
   */
  public function resolveTargetPluginDefinition(array $configuration): ?array {
    $target_id = $configuration['target_block']['id'] ?? '';
    if ($target_id === '') {
      return NULL;
    }
    if (!array_key_exists($target_id, $this->targetDefinitionCache)) {
      $definition = $this->blockManager->getDefinition($target_id, FALSE);
      $this->targetDefinitionCache[$target_id] = is_array($definition) ? $definition : FALSE;
    }
    $definition = $this->targetDefinitionCache[$target_id];
    return $definition === FALSE ? NULL : $definition;
  }

  /**
   * Renders the proxy block.
   *
   * Takes plain values rather than the ProxyBlock plugin instance so the
   * renderer is decoupled from the (final) plugin class and easy to unit
   * test in isolation. The plugin instance is passed through only because
   * TargetBlockCacheManager::bubbleTargetBlockCacheMetadata() needs it for
   * the access-result merge step.
   *
   * @param array $configuration
   *   The proxy block configuration.
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $proxyContexts
   *   The contexts already applied to the proxy.
   * @param array $parentCacheContexts
   *   Parent BlockBase cache contexts (for the embedded cache metadata in
   *   admin-preview / access-denied branches).
   * @param array $parentCacheTags
   *   Parent BlockBase cache tags.
   * @param int $parentCacheMaxAge
   *   Parent BlockBase cache max-age.
   * @param string $proxyPluginId
   *   The proxy plugin id, for the proxy-specific cache tag.
   * @param \Drupal\Core\Cache\CacheableDependencyInterface $proxyForBubbling
   *   The proxy plugin instance, forwarded to
   *   TargetBlockCacheManager::bubbleTargetBlockCacheMetadata() for its
   *   cache-dependency merge.
   *
   * @return array
   *   The render array.
   */
  public function render(
    array $configuration,
    array $proxyContexts,
    array $parentCacheContexts,
    array $parentCacheTags,
    int $parentCacheMaxAge,
    string $proxyPluginId,
    CacheableDependencyInterface $proxyForBubbling,
  ): array {
    $target_block = $this->targetBlockFactory->getTargetBlock($configuration);
    if (!$target_block) {
      return [];
    }

    if ($target_block instanceof ContextAwarePluginInterface) {
      $this->forwardContexts($target_block, $proxyContexts);
    }

    if ($this->isLayoutBuilderAdminRoute()) {
      $admin_view = $this->buildLayoutBuilderAdminPreview(
        $configuration,
        $parentCacheContexts,
        $parentCacheTags,
        $parentCacheMaxAge,
        $proxyPluginId,
      );
      if ($admin_view !== NULL) {
        return $admin_view;
      }
    }

    $access_result = $target_block->access($this->currentUser, TRUE);
    if (!$access_result->isAllowed()) {
      $build = [
        '#markup' => '',
        '#cache' => [
          'contexts' => $this->collectCacheContexts($configuration, $parentCacheContexts),
          'tags' => $this->collectCacheTags($configuration, $parentCacheTags, $proxyPluginId),
          'max-age' => $this->collectCacheMaxAge($configuration, $parentCacheMaxAge),
        ],
      ];
      CacheableMetadata::createFromObject($access_result)->applyTo($build);
      return $build;
    }

    $build = $target_block->build();
    $this->cacheManager->bubbleTargetBlockCacheMetadata($build, $target_block, $proxyForBubbling);
    return $build;
  }

  /**
   * Computes cache contexts for the proxy.
   *
   * @param array $configuration
   *   The proxy configuration.
   * @param array $parentCacheContexts
   *   The contributions from parent::getCacheContexts() on the plugin.
   *
   * @return array
   *   The merged cache contexts.
   */
  public function collectCacheContexts(array $configuration, array $parentCacheContexts): array {
    $target_block = $this->targetBlockFactory->getTargetBlock($configuration);
    return $this->cacheManager->getCacheContexts($target_block, $parentCacheContexts);
  }

  /**
   * Computes cache tags for the proxy.
   *
   * @param array $configuration
   *   The proxy configuration.
   * @param array $parentCacheTags
   *   The contributions from parent::getCacheTags() on the plugin.
   * @param string $proxyPluginId
   *   The proxy plugin id, used to compose a proxy-specific tag.
   *
   * @return array
   *   The merged cache tags.
   */
  public function collectCacheTags(array $configuration, array $parentCacheTags, string $proxyPluginId): array {
    $target_block = $this->targetBlockFactory->getTargetBlock($configuration);
    $cache_tags = $this->cacheManager->getCacheTags($target_block, $parentCacheTags);
    $cache_tags[] = 'proxy_block_proxy:' . $proxyPluginId;
    return $cache_tags;
  }

  /**
   * Computes the cache max-age for the proxy.
   *
   * @param array $configuration
   *   The proxy configuration.
   * @param int $parentMaxAge
   *   The contribution from parent::getCacheMaxAge() on the plugin.
   *
   * @return int
   *   The merged cache max-age.
   */
  public function collectCacheMaxAge(array $configuration, int $parentMaxAge): int {
    $target_block = $this->targetBlockFactory->getTargetBlock($configuration);
    return $this->cacheManager->getCacheMaxAge($target_block, $parentMaxAge);
  }

  /**
   * Forwards proxy-level contexts onto the target, with fallbacks.
   *
   * Each setContext() is guarded so a single mismatched type cannot tear
   * down the whole render. When the target still wants the conventional
   * 'entity' / 'node' name but Layout Builder only supplied
   * 'layout_builder.entity', the root-entity fallback binds it.
   *
   * @param \Drupal\Core\Plugin\ContextAwarePluginInterface $target_block
   *   The target block.
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $proxy_contexts
   *   The contexts already applied to the proxy by Layout Builder / core.
   */
  protected function forwardContexts(ContextAwarePluginInterface $target_block, array $proxy_contexts): void {
    foreach ($proxy_contexts as $name => $context) {
      try {
        $target_block->setContext($name, $context);
      }
      catch (ContextException $e) {
        $this->logger->notice('Proxy block could not forward context "@name" to target @plugin: @message', [
          '@name' => $name,
          '@plugin' => $target_block->getPluginId(),
          '@message' => $e->getMessage(),
        ]);
      }
    }

    $root_entity_context = $this->contextManager->resolveRootEntityContext($proxy_contexts);
    if ($root_entity_context === NULL) {
      return;
    }
    foreach (['entity', 'node'] as $fallback_name) {
      if (!isset($target_block->getContextDefinitions()[$fallback_name])) {
        continue;
      }
      try {
        $existing = $target_block->getContext($fallback_name);
        if ($existing->hasContextValue()) {
          continue;
        }
      }
      catch (ContextException) {
        // No context bound under this name yet; fall through to set it.
      }
      try {
        $target_block->setContext($fallback_name, $root_entity_context);
      }
      catch (ContextException $e) {
        $this->logger->notice('Proxy block root-entity fallback could not bind "@name" on target @plugin: @message', [
          '@name' => $fallback_name,
          '@plugin' => $target_block->getPluginId(),
          '@message' => $e->getMessage(),
        ]);
      }
    }
  }

  /**
   * Detects whether the current request is a Layout Builder admin route.
   */
  protected function isLayoutBuilderAdminRoute(): bool {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      return FALSE;
    }
    $path = $request->getPathInfo();
    if (str_contains($path, '/admin/structure/types/')
      && str_contains($path, '/display/')
      && str_contains($path, '/layout')
    ) {
      return TRUE;
    }
    if (str_contains($path, '/layout_builder/update/block/')
      || str_contains($path, '/layout_builder/add/block/')
    ) {
      return TRUE;
    }
    $destination = $request->query->get('destination');
    return is_string($destination) && str_contains($destination, '/layout');
  }

  /**
   * Builds the Layout Builder admin "placeholder" preview render array.
   *
   * @return array|null
   *   A render array, or NULL when there's no target plugin id to preview.
   */
  protected function buildLayoutBuilderAdminPreview(
    array $configuration,
    array $parentCacheContexts,
    array $parentCacheTags,
    int $parentCacheMaxAge,
    string $proxyPluginId,
  ): ?array {
    $target_plugin_id = $configuration['target_block']['id'] ?? '';
    if (!$target_plugin_id) {
      return NULL;
    }
    $block_definition = $this->resolveTargetPluginDefinition($configuration) ?? [];
    $admin_label = $block_definition['admin_label'] ?? NULL;
    $block_label = (string) ($admin_label ?: $target_plugin_id ?: 'Unknown Block');
    return [
      '#markup' => '<div class="layout-builder-block"><strong>Proxy Block:</strong> ' . $this->t('Configured to render "@block"', ['@block' => $block_label]) . '</div>',
      '#cache' => [
        'contexts' => $this->collectCacheContexts($configuration, $parentCacheContexts),
        'tags' => $this->collectCacheTags($configuration, $parentCacheTags, $proxyPluginId),
        'max-age' => $this->collectCacheMaxAge($configuration, $parentCacheMaxAge),
      ],
    ];
  }

  /**
   * Returns the wrapped block instance for a configuration, if creatable.
   *
   * Convenience pass-through so callers needing the target instance don't
   * have to depend on the factory directly.
   *
   * @param array $configuration
   *   The proxy configuration.
   */
  public function getTargetBlock(array $configuration): ?BlockPluginInterface {
    return $this->targetBlockFactory->getTargetBlock($configuration);
  }

}
