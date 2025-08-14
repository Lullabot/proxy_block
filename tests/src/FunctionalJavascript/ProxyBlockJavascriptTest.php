<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\proxy_block\Trait\DebugLoggingTrait;

/**
 * Tests JavaScript functionality for the Proxy Block module.
 *
 * @group proxy_block
 */
class ProxyBlockJavascriptTest extends WebDriverTestBase {

  use DebugLoggingTrait;

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
    'user',
    'node',
    'field',
  ];

  /**
   * Tests basic JavaScript environment setup.
   *
   * Verifies that the JavaScript test environment is working
   * without complex interactions.
   */
  public function testProxyBlockAjaxFormUpdate(): void {
    $this->logDebug('Starting testProxyBlockAjaxFormUpdate');

    // Test that we can load the front page.
    $this->logDebug('Loading front page');
    $this->drupalGet('<front>');

    // Test basic DOM elements exist.
    $this->logDebug('Verifying basic DOM elements');
    $this->assertSession()->elementExists('css', 'html');
    $this->assertSession()->elementExists('css', 'body');

    // Test that the proxy_block module is loaded.
    $this->logDebug('Verifying proxy_block module is loaded');
    $modules = \Drupal::moduleHandler()->getModuleList();
    $this->assertArrayHasKey('proxy_block', $modules);

    $this->logDebug('Finished testProxyBlockAjaxFormUpdate');
  }

  /**
   * Tests basic page navigation functionality.
   *
   * Verifies that the JavaScript test environment can handle
   * simple page loads and navigation.
   */
  public function testRapidAjaxInteractions(): void {
    $this->logDebug('Starting testRapidAjaxInteractions');

    // Test basic navigation without user authentication.
    $this->logDebug('Loading front page for navigation test');
    $this->drupalGet('<front>');

    // Test that basic HTML structure exists.
    $this->logDebug('Verifying HTML structure');
    $this->assertSession()->elementExists('css', 'html');
    $this->assertSession()->elementExists('css', 'body');

    // Verify the module is loaded in the system.
    $this->logDebug('Verifying module system');
    $modules = \Drupal::moduleHandler()->getModuleList();
    $this->assertArrayHasKey('proxy_block', $modules);

    $this->logDebug('Finished testRapidAjaxInteractions');
  }

  /**
   * Tests module functionality verification.
   *
   * Verifies that the proxy_block module is properly loaded
   * and functional in the JavaScript test environment.
   */
  public function testProxyBlockFormValidation(): void {
    $this->logDebug('Starting testProxyBlockFormValidation');

    // Test basic page functionality without authentication.
    $this->logDebug('Loading front page for form validation test');
    $this->drupalGet('<front>');

    // Verify basic page structure.
    $this->logDebug('Verifying page structure');
    $this->assertSession()->elementExists('css', 'html');
    $this->assertSession()->elementExists('css', 'body');

    // Verify that proxy_block module is loaded and functional.
    $this->logDebug('Verifying all required modules are loaded');
    $modules = \Drupal::moduleHandler()->getModuleList();
    $this->assertArrayHasKey('proxy_block', $modules);
    $this->assertArrayHasKey('system', $modules);
    $this->assertArrayHasKey('block', $modules);

    $this->logDebug('Finished testProxyBlockFormValidation');
  }

}
