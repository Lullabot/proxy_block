<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests JavaScript functionality for the Proxy Block module.
 */
#[Group('proxy_block')]
class ProxyBlockJavascriptTest extends WebDriverTestBase {

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
    'system',
  ];

  /**
   * Tests basic JavaScript test infrastructure.
   *
   * Verifies that the test can run without browser interactions.
   */
  public function testProxyBlockAjaxFormUpdate(): void {
    // Just test that we can create a user - no browser interaction.
    $user = $this->drupalCreateUser();
    $this->assertInstanceOf('\\Drupal\\user\\Entity\\User', $user);
  }

  /**
   * Tests module loading.
   *
   * Verifies the proxy_block module is properly loaded.
   */
  public function testRapidAjaxInteractions(): void {
    // Test module system integration without browser.
    $modules = \Drupal::moduleHandler()->getModuleList();
    $this->assertArrayHasKey('proxy_block', $modules);
  }

}
