<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\google_tag\Entity\TagContainer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the gtagEvent Ajax command.
 *
 * @group google_tag
 */
#[Group('google_tag')]
#[RunTestsInSeparateProcesses]
final class GtagAjaxCommandTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['google_tag', 'google_tag_ajax_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    TagContainer::create([
      'id' => 'foo',
      'tag_container_ids' => ['G-XXXXXX'],
    ])->save();
  }

  /**
   * Tests that the command pushes the event to the dataLayer.
   */
  public function testEventSent(): void {
    $this->submitAjaxForm();
    $events = $this->getSession()->evaluateScript(
      'window.dataLayer.filter((entry) => entry[0] === "event" && entry[1] === "ajax_test").length'
    );
    self::assertSame(1, $events);
  }

  /**
   * Tests that a blocked gtag.js does not break the rest of the response.
   */
  public function testGtagBlocked(): void {
    $this->container->get('state')->set('google_tag_ajax_test.block_gtag', TRUE);
    $this->submitAjaxForm();
    self::assertSame('undefined', $this->getSession()->evaluateScript('typeof gtag'));
  }

  /**
   * Submits the test form and waits for the command after gtagEvent.
   */
  private function submitAjaxForm(): void {
    $this->drupalGet('/google-tag-ajax-test');
    $this->getSession()->getPage()->pressButton('Send event');
    $this->assertSession()->waitForText('Commands after gtagEvent ran');
    $this->assertSession()->elementTextContains('css', '#google-tag-ajax-result', 'Commands after gtagEvent ran');
  }

}
