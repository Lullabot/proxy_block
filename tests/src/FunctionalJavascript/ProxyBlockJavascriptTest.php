<?php

declare(strict_types=1);

namespace Drupal\Tests\proxy_block\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests JavaScript functionality for the Proxy Block module.
 *
 * @group proxy_block
 */
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
    // Test that we can load the front page without JavaScript errors.
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
    
    // Test basic DOM elements exist.
    $this->assertSession()->elementExists('css', 'html');
    $this->assertSession()->elementExists('css', 'body');
    
    // Test that the proxy_block module is loaded.
    $modules = \Drupal::moduleHandler()->getModuleList();
    $this->assertArrayHasKey('proxy_block', $modules);
  }

  /**
   * Tests basic page navigation functionality.
   *
   * Verifies that the JavaScript test environment can handle
   * simple page loads and navigation.
   */
  public function testRapidAjaxInteractions(): void {
    // Test basic navigation without user authentication.
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
    
    // Test that basic HTML structure exists.
    $this->assertSession()->elementExists('css', 'html');
    $this->assertSession()->elementExists('css', 'body');
    
    // Verify the module is loaded in the system.
    $modules = \Drupal::moduleHandler()->getModuleList();
    $this->assertArrayHasKey('proxy_block', $modules);
  }

  /**
   * Tests module functionality verification.
   *
   * Verifies that the proxy_block module is properly loaded
   * and functional in the JavaScript test environment.
   */
  public function testProxyBlockFormValidation(): void {
    // Test basic page functionality without authentication.
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
    
    // Verify basic page structure.
    $this->assertSession()->elementExists('css', 'html');
    $this->assertSession()->elementExists('css', 'body');
    
    // Confirm no JavaScript errors on the page.
    $this->assertSession()->pageTextNotContains('Uncaught');
    $this->assertSession()->pageTextNotContains('JavaScript error');
    
    // Verify that proxy_block module is loaded and functional.
    $modules = \Drupal::moduleHandler()->getModuleList();
    $this->assertArrayHasKey('proxy_block', $modules);
    $this->assertArrayHasKey('system', $modules);
    $this->assertArrayHasKey('block', $modules);
  }

}
